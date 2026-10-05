#!/usr/bin/env python3
"""Switch production CLI alternatives to PHP 8.4; keep PHP 8.3 installed for rollback."""
import argparse
import datetime
import hashlib
import json
import os
import socket
import subprocess
from pathlib import Path

ROOT = Path('/var/www/oneid')
NAMES = ['php', 'phar', 'phar.phar']
OLD = {'php': '/usr/bin/php8.3', 'phar': '/usr/bin/phar8.3', 'phar.phar': '/usr/bin/phar.phar8.3'}
NEW = {'php': '/usr/bin/php8.4', 'phar': '/usr/bin/phar8.4', 'phar.phar': '/usr/bin/phar.phar8.4'}
SCRIPTS = [
    ROOT / 'cron/run_conditional_external_sync.php',
    ROOT / 'tools/as1_session_housekeeping.php',
    ROOT / 'tools/user_mfa_lifecycle_worker.php',
]


def run(args, check=True):
    result = subprocess.run(args, text=True, capture_output=True)
    if check and result.returncode:
        raise RuntimeError(f"command failed ({result.returncode}): {' '.join(args)}\n{result.stdout}{result.stderr}")
    return {'exit': result.returncode, 'stdout': result.stdout, 'stderr': result.stderr}


def version(binary):
    return run([binary, '-r', 'echo PHP_VERSION;'])['stdout']


def resolved(name):
    return str(Path('/usr/bin/' + name).resolve())


def set_alternatives(targets):
    for name in ['phar', 'phar.phar', 'php']:
        run(['update-alternatives', '--set', name, targets[name]])


def rollback(reason):
    set_alternatives(OLD)
    print('ROLLED BACK to PHP 8.3 alternatives: ' + reason)


def guard_host():
    if os.geteuid() != 0:
        raise RuntimeError('run with sudo')
    if socket.gethostname() != 'APPSSSOPRODv1' or not (ROOT / 'public/index.php').is_file():
        raise RuntimeError('wrong production host/root')
    if version('php8.3') != '8.3.33' or version('php8.4') != '8.4.26':
        raise RuntimeError('unexpected PHP versions')
    for service in ['nginx', 'php8.3-fpm', 'php8.4-fpm']:
        run(['systemctl', 'is-active', '--quiet', service])


def checks():
    result = {'lint': {str(p): run(['php8.4', '-l', str(p)]) for p in SCRIPTS}}
    result['housekeeping'] = run(['sudo', '-u', 'iqs', 'php8.4', str(SCRIPTS[1]), '--check'])
    result['mfa'] = run(['sudo', '-u', 'iqs', 'php8.4', str(SCRIPTS[2]), '--check'])
    required = {'PDO', 'pdo_mysql', 'odbc', 'PDO_ODBC', 'pdo_dblib', 'curl', 'mbstring', 'intl', 'openssl'}
    loaded = set(run(['php8.4', '-r', 'echo implode("\\n",get_loaded_extensions());'])['stdout'].splitlines())
    missing = sorted(required - loaded)
    if missing:
        raise RuntimeError('PHP 8.4 missing extensions: ' + ','.join(missing))
    result['required_extensions'] = sorted(required)
    result['timezone'] = run(['php8.4', '-r', 'echo date_default_timezone_get();'])['stdout']
    return result


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--apply', action='store_true')
    parser.add_argument('--rollback', metavar='BACKUP')
    args = parser.parse_args()
    guard_host()
    if args.rollback:
        backup = Path(args.rollback)
        if not backup.is_dir() or not (backup / 'before.json').is_file():
            raise RuntimeError('invalid rollback backup')
        rollback('operator requested backup ' + str(backup))
        print(json.dumps({'status': 'ROLLED_BACK', 'php': resolved('php'), 'backup': str(backup)}, indent=2))
        return
    current = {name: resolved(name) for name in NAMES}
    if current['php'] != '/usr/bin/php8.3' or '8.3' not in current['phar'] or '8.3' not in current['phar.phar']:
        raise RuntimeError('unexpected pre-cutover alternatives: ' + json.dumps(current))
    pre = checks()
    print('CHECK PASS: PHP 8.4 CLI compatibility and read-only cron checks; web/mobile/database unchanged.', flush=True)
    if not args.apply:
        print(json.dumps({'status': 'READY', 'current': current, 'checks': pre}, indent=2))
        return
    os.umask(0o077)
    stamp = datetime.datetime.now().strftime('%Y%m%d-%H%M%S')
    backup = Path('/var/backups/oneid-cli-cron-cutover-' + stamp)
    backup.mkdir(mode=0o700)
    cron = {u: run(['crontab', '-u', u, '-l'], check=False) for u in ['iqs', 'root']}
    before = {
        'created_at': datetime.datetime.now().astimezone().isoformat(),
        'alternatives': {n: run(['update-alternatives', '--query', n]) for n in NAMES},
        'resolved': current,
        'crontabs': cron,
        'script_sha256': {str(p): hashlib.sha256(p.read_bytes()).hexdigest() for p in SCRIPTS},
        'checks': pre,
    }
    (backup / 'before.json').write_text(json.dumps(before, indent=2))
    for user in cron:
        (backup / ('crontab-' + user + '.txt')).write_text(cron[user]['stdout'])
    try:
        set_alternatives(NEW)
        if version('php') != '8.4.26':
            raise RuntimeError('default PHP version mismatch after switch')
        after_resolved = {name: resolved(name) for name in NAMES}
        if after_resolved['php'] != '/usr/bin/php8.4' or '8.4' not in after_resolved['phar'] or '8.4' not in after_resolved['phar.phar']:
            raise RuntimeError('alternative mismatch after switch: ' + json.dumps(after_resolved))
        post = checks()
        after_cron = {u: run(['crontab', '-u', u, '-l'], check=False)['stdout'] for u in ['iqs', 'root']}
        if any(after_cron[u] != cron[u]['stdout'] for u in cron):
            raise RuntimeError('crontab drift detected')
        for service in ['nginx', 'php8.3-fpm', 'php8.4-fpm']:
            run(['systemctl', 'is-active', '--quiet', service])
    except Exception as exception:
        rollback(str(exception))
        raise
    result = {
        'status': 'CLI_CRON_PHP84_CUTOVER',
        'backup': str(backup),
        'default_php': version('php'),
        'alternatives': after_resolved,
        'cron_content_changed': False,
        'timer_content_changed': False,
        'php83_removed': False,
        'web_routing_changed': False,
        'mobile_changed': False,
        'database_changed': False,
        'post_checks': post,
    }
    (backup / 'result.json').write_text(json.dumps(result, indent=2))
    print(json.dumps(result, indent=2))
    print('Rollback: sudo python3 -B /home/iqs/cutover-production-cli-cron84.py --rollback ' + str(backup))


if __name__ == '__main__':
    try:
        main()
    except Exception as exception:
        raise SystemExit('STOP: ' + str(exception))
