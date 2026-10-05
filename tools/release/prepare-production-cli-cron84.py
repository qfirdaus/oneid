#!/usr/bin/env python3
"""Prepare a reversible production CLI/cron PHP 8.4 migration; never switches runtime."""
import argparse
import datetime
import hashlib
import json
import os
import shutil
import socket
import subprocess
from pathlib import Path

ROOT = Path('/var/www/oneid')
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


def resolved(name):
    return str(Path('/usr/bin/' + name).resolve())


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--apply', action='store_true', help='Create backup only; no runtime switch')
    args = parser.parse_args()
    if socket.gethostname() != 'APPSSSOPRODv1' or not (ROOT / 'public/index.php').is_file():
        raise RuntimeError('wrong production host/root')
    expected = {'php': '/usr/bin/php8.3', 'phar': '/usr/bin/phar8.3.phar', 'phar.phar': '/usr/bin/phar8.3.phar'}
    current = {name: resolved(name) for name in expected}
    if current != expected:
        raise RuntimeError('unexpected existing alternatives: ' + json.dumps(current))
    versions = {
        '8.3': run(['php8.3', '-r', 'echo PHP_VERSION;'])['stdout'],
        '8.4': run(['php8.4', '-r', 'echo PHP_VERSION;'])['stdout'],
    }
    if versions != {'8.3': '8.3.33', '8.4': '8.4.26'}:
        raise RuntimeError('unexpected PHP versions: ' + json.dumps(versions))
    checks = {}
    for script in SCRIPTS:
        checks[str(script)] = run(['php8.4', '-l', str(script)])
    checks['housekeeping_read_only'] = run(['sudo', '-u', 'iqs', 'php8.4', str(SCRIPTS[1]), '--check'])
    checks['mfa_read_only'] = run(['sudo', '-u', 'iqs', 'php8.4', str(SCRIPTS[2]), '--check'])
    plan = {
        'status': 'READY_FOR_BACKUP' if not args.apply else 'BACKUP_CREATED_NO_SWITCH',
        'scope': 'production CLI/phar/cron/timers preparation only',
        'versions': versions,
        'alternatives': current,
        'checks': checks,
        'mobile_changed': False,
        'database_changed': False,
        'runtime_switched': False,
    }
    if not args.apply:
        print(json.dumps(plan, indent=2))
        return
    if os.geteuid() != 0:
        raise RuntimeError('--apply must run with sudo')
    os.umask(0o077)
    stamp = datetime.datetime.now().strftime('%Y%m%d-%H%M%S')
    backup = Path('/var/backups/oneid-cli-cron-pre84-' + stamp)
    backup.mkdir(mode=0o700)
    records = {
        **plan,
        'created_at': datetime.datetime.now().astimezone().isoformat(),
        'alternatives_query': {n: run(['update-alternatives', '--query', n]) for n in expected},
        'crontabs': {u: run(['crontab', '-u', u, '-l'], check=False) for u in ['iqs', 'root']},
        'timers': run(['systemctl', 'list-timers', '--all', '--no-pager']),
        'services': {s: run(['systemctl', 'is-active', s], check=False) for s in ['nginx', 'php8.3-fpm', 'php8.4-fpm']},
        'script_sha256': {str(p): hashlib.sha256(p.read_bytes()).hexdigest() for p in SCRIPTS},
    }
    (backup / 'before.json').write_text(json.dumps(records, indent=2))
    for user in ['iqs', 'root']:
        (backup / f'crontab-{user}.txt').write_text(records['crontabs'][user]['stdout'])
    for path in ['/etc/crontab', '/etc/cron.d', '/etc/systemd/system', '/etc/php/8.3/cli', '/etc/php/8.4/cli']:
        source = Path(path)
        if source.exists():
            target = backup / source.relative_to('/')
            target.parent.mkdir(parents=True, exist_ok=True)
            if source.is_dir():
                shutil.copytree(source, target, symlinks=True)
            else:
                shutil.copy2(source, target)
    plan['backup'] = str(backup)
    (backup / 'result.json').write_text(json.dumps(plan, indent=2))
    print(json.dumps(plan, indent=2))
    print('PASS: backup created; PHP alternatives, cron, timers, mobile, Nginx and database unchanged.')


if __name__ == '__main__':
    try:
        main()
    except Exception as exception:
        raise SystemExit('STOP: ' + str(exception))
