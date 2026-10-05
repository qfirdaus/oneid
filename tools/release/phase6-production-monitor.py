#!/usr/bin/env python3
"""Read-only Phase 6 production snapshot after PHP 8.4 cutovers."""
import collections
import datetime
import json
import re
import socket
import subprocess
from pathlib import Path

START = datetime.datetime.fromisoformat('2026-10-05T08:17:39+08:00')


def service(name):
    output = subprocess.check_output([
        'systemctl', 'show', name, '-p', 'ActiveState', '-p', 'SubState',
        '-p', 'NRestarts', '-p', 'MemoryCurrent', '-p', 'TasksCurrent'
    ], text=True)
    return dict(line.split('=', 1) for line in output.splitlines() if '=' in line)


def nginx_access(path):
    counts = collections.Counter()
    five = []
    pattern = re.compile(r'\[(\d{2})/([A-Za-z]{3})/(\d{4}):(\d{2}):(\d{2}):(\d{2}) [^]]+\].*" (\d{3}) ')
    months = {m: i for i, m in enumerate(['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'], 1)}
    for line in path.read_text(errors='replace').splitlines():
        match = pattern.search(line)
        if not match:
            continue
        day, mon, year, hh, mm, ss, status = match.groups()
        when = datetime.datetime(int(year), months[mon], int(day), int(hh), int(mm), int(ss), tzinfo=START.tzinfo)
        if when < START:
            continue
        counts[status] += 1
        if status.startswith('5'):
            five.append(line[-500:])
    return {'status_counts': dict(sorted(counts.items())), 'http_5xx': sum(v for k,v in counts.items() if k.startswith('5')), 'five_samples': five[-10:]}


def line_time(line):
    for pattern, fmt in [
        (r'^\[(\d{2}-[A-Za-z]{3}-\d{4} \d{2}:\d{2}:\d{2})', '%d-%b-%Y %H:%M:%S'),
        (r'^(\d{4}/\d{2}/\d{2} \d{2}:\d{2}:\d{2})', '%Y/%m/%d %H:%M:%S'),
    ]:
        match = re.search(pattern, line)
        if match:
            return datetime.datetime.strptime(match.group(1), fmt).replace(tzinfo=START.tzinfo)
    return None


def filtered(path, expressions):
    if not path.exists():
        return {'exists': False, 'matches': []}
    matches = []
    for line in path.read_text(errors='replace').splitlines():
        when = line_time(line)
        if when is not None and when < START:
            continue
        low = line.lower()
        if any(expr in low for expr in expressions):
            matches.append(line[-1000:])
    return {'exists': True, 'matches': matches[-30:]}


def tail(path, count=8):
    if not path.exists(): return []
    return path.read_text(errors='replace').splitlines()[-count:]


def main():
    if socket.gethostname() != 'APPSSSOPRODv1':
        raise SystemExit('STOP: wrong host')
    result = {
        'checked_at': datetime.datetime.now().astimezone().isoformat(),
        'since': START.isoformat(),
        'scope': 'read-only Phase 6 snapshot',
        'services': {name: service(name) for name in ['nginx', 'php8.4-fpm', 'php8.3-fpm']},
        'nginx': {
            'access': nginx_access(Path('/var/log/nginx/oneid.access.log')),
            'errors': filtered(Path('/var/log/nginx/oneid.error.log'), ['[crit]', '[alert]', '[emerg]', 'upstream timed out', 'connect() failed']),
        },
        'php_fpm': filtered(Path('/var/log/php8.4-fpm.log'), ['warning', 'error', 'child exited', 'max_children', 'segfault']),
        'application': filtered(Path('/var/www/oneid/storage/logs/php-error.log'), ['fatal error', 'parse error', 'uncaught ', 'permission denied', 'failed opening required']),
        'cron_tails': {
            'external_sync': tail(Path('/var/www/oneid/storage/logs/external-sync-cron.log')),
            'housekeeping': tail(Path('/var/www/oneid/storage/logs/session-housekeeping-cron.log')),
            'mfa': tail(Path('/var/www/oneid/storage/logs/user-mfa-lifecycle-cron.log')),
        },
        'default_php': subprocess.check_output(['php', '-r', 'echo PHP_VERSION;'], text=True),
        'mobile_changed': False,
        'database_changed': False,
    }
    blockers = []
    if result['nginx']['access']['http_5xx']:
        blockers.append('HTTP_5XX_PRESENT')
    for section in [result['nginx']['errors'], result['php_fpm'], result['application']]:
        if section['matches']:
            blockers.append('ERROR_LOG_MATCHES_REQUIRE_REVIEW')
            break
    if any(v.get('NRestarts') not in ('0', 0) for v in result['services'].values()):
        blockers.append('SERVICE_RESTART_PRESENT')
    result['status'] = 'PASS' if not blockers else 'REVIEW'
    result['blockers'] = blockers
    print(json.dumps(result, indent=2))


if __name__ == '__main__':
    main()
