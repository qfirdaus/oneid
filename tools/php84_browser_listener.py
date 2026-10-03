#!/usr/bin/env python3
"""Prepare/apply/remove a loopback-only HTTPS clone for OneID UAT PHP 8.4."""
import argparse
import datetime
import hashlib
import json
import os
from pathlib import Path
import re
import socket
import subprocess
import time
from php84_fpm_probe import probe

ROOT = Path(__file__).resolve().parent.parent
VHOST = Path('/etc/nginx/sites-enabled/oneid-uat')
MOBILE = Path('/etc/nginx/snippets/oneid-mobile-uat-dormant.conf')
TARGET = Path('/etc/nginx/conf.d/oneid-php84-browser-test.conf')
MARKER = '# Managed by OneID php84_browser_listener.py; loopback only.\n'
HOST = 'oneid-uat.upnm.edu.my'
PORT = 24484


def render():
    original = VHOST.read_text()
    if original.count('# HTTPS\n') != 1:
        raise RuntimeError('Unexpected vhost structure')
    server = original.split('# HTTPS\n', 1)[1]
    for directive in ['listen 443 ssl http2;', 'listen [::]:443 ssl http2;']:
        if server.count(directive) != 1:
            raise RuntimeError('Unexpected listen structure')
    server = server.replace('listen 443 ssl http2;', f'listen 127.0.0.1:{PORT} ssl http2;')
    server = server.replace('listen [::]:443 ssl http2;', '')
    include = 'include /etc/nginx/snippets/oneid-mobile-uat-dormant.conf;'
    if server.count(include) != 1:
        raise RuntimeError('Unexpected mobile include')
    server = server.replace(include, MOBILE.read_text())
    if server.count('unix:/run/php/php8.3-fpm.sock;') != 1 or server.count('unix:/run/php/oneid-mobile-uat.sock;') != 7:
        raise RuntimeError('Unexpected upstream routing; review source before applying')
    server = server.replace('unix:/run/php/php8.3-fpm.sock;', 'unix:/run/php/oneid-web-uat84.sock;')
    server = server.replace('unix:/run/php/oneid-mobile-uat.sock;', 'unix:/run/php/oneid-mobile-uat84.sock;')
    # Browser uses canonical HTTPS port through an SSH tunnel. Preserve canonical CGI port.
    params = Path('/etc/nginx/fastcgi_params').read_text()
    params, n = re.subn(r'(fastcgi_param\s+SERVER_PORT\s+)\$server_port;', r'\g<1>443;', params)
    if n != 1:
        raise RuntimeError('Unexpected FastCGI parameters')
    server = re.sub(r'include (?:/etc/nginx/)?fastcgi_params;', lambda _: params, server)
    server = re.sub(r'access_log [^;]+;', 'access_log /var/log/nginx/oneid-php84-browser-test.access.log oneid_php84_test;', server)
    # No query strings, cookies, request bodies, Location headers or tokens in this log.
    server = re.sub(r'error_log [^;]+;', 'error_log /dev/null crit;', server)
    server = re.sub(r'(fastcgi_pass unix:/run/php/oneid-(?:web|mobile)-uat84.sock;)',
                    r'\1\n        add_header X-OneID-Test-Runtime "php84-isolated" always;\n        add_header X-Content-Type-Options "nosniff" always;\n        add_header X-Frame-Options "SAMEORIGIN" always;\n        add_header Referrer-Policy "strict-origin-when-cross-origin" always;', server)
    fmt = 'log_format oneid_php84_test escape=json \'{"time":"$time_iso8601","method":"$request_method","status":$status,"upstream":"$upstream_addr","seconds":"$request_time"}\';\n'
    return MARKER + fmt + server


def protected():
    roots = ['/etc/nginx', '/etc/php/8.3']
    return {str(p): hashlib.sha256(p.read_bytes()).hexdigest()
            for root in roots for p in Path(root).rglob('*')
            if p.is_file() and p != TARGET and (p.suffix in ['.conf', '.ini'] or p.parent.name in ['sites-enabled', 'sites-available'])}


def nginx_reload():
    subprocess.run(['/usr/sbin/nginx', '-t'], check=True)
    subprocess.run(['systemctl', 'reload', 'nginx'], check=True)


def smoke():
    result = []
    for path, expected, marker in [('/', {200}, True), ('/login.css', {200}, True),
                                   ('/mobile/session', {401, 403}, True),
                                   ('/.well-known/openid-configuration', {200}, False)]:
        # Discard response body (may include CSRF state); report only status and marker.
        response = subprocess.run(['curl', '--silent', '--show-error', '--max-time', '20',
            '--noproxy', '*', '--connect-to', f'{HOST}:443:127.0.0.1:{PORT}',
            '-D', '-', '-o', '/dev/null', f'https://{HOST}{path}'], capture_output=True, text=True, check=True)
        codes = re.findall(r'^HTTP/\S+ (\d+)', response.stdout, re.M)
        code = int(codes[-1]) if codes else 0
        marked = 'x-oneid-test-runtime: php84-isolated' in response.stdout.lower()
        result.append({'path': path, 'status': code, 'php84_marker': marked})
        if code not in expected or (marker and not marked):
            raise RuntimeError(f'Smoke failed for {path}: status={code}, marker={marked}')
    return result


def main():
    parser = argparse.ArgumentParser()
    group = parser.add_mutually_exclusive_group()
    group.add_argument('--apply', action='store_true')
    group.add_argument('--remove', action='store_true')
    args = parser.parse_args()
    if not args.remove:
        config = render()
        for pool in ['oneid-web-uat84', 'oneid-mobile-uat84']:
            state = probe('/run/php/' + pool + '.sock')
            if state['version'] != '8.4.26' or state['sapi'] != 'fpm-fcgi':
                raise RuntimeError('Unexpected FPM runtime')
        if not args.apply:
            dest = ROOT / 'docs/php84/oneid-php84-browser-test.conf.preview'
            dest.write_text(config)
            print(f'CHECK PASS: HTTPS listener 127.0.0.1:{PORT}; preview: {dest}')
            print('No system changes. Shared UAT data; browser/front-channel isolation only. Hydra hook and downstream back-channel remain on existing routes.')
            return
    if os.geteuid() != 0:
        raise RuntimeError('Root required for --apply/--remove; run with sudo')
    before = protected()
    if args.remove:
        if not TARGET.exists():
            print('Already absent'); return
        previous = TARGET.read_text()
        if not previous.startswith(MARKER):
            raise RuntimeError('Refusing to remove an unmanaged config')
        TARGET.unlink()
        try:
            nginx_reload()
        except Exception:
            TARGET.write_text(previous); nginx_reload(); raise
        if before != protected():
            raise RuntimeError('Protected configuration drift')
        print('REMOVED: isolated listener; existing routes unchanged.'); return
    if TARGET.exists():
        raise RuntimeError('Listener config already exists; review/remove explicitly before reapplying')
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', PORT))
    backup = Path('/var/backups/oneid-php84-browser-' + datetime.datetime.now().strftime('%Y%m%d-%H%M%S'))
    backup.mkdir(mode=0o700)
    (backup / 'protected-hashes.json').write_text(json.dumps(before, indent=2))
    (backup / 'listener.conf').write_text(config)
    try:
        TARGET.write_text(config)
        nginx_reload()
        time.sleep(1)
        results = smoke()
        if protected() != before:
            raise RuntimeError('Protected configuration drift')
        (backup / 'smoke.json').write_text(json.dumps(results, indent=2))
    except Exception:
        TARGET.unlink(missing_ok=True)
        nginx_reload()
        raise
    print(json.dumps(results, indent=2))
    print(f'PASS: loopback listener ready on {PORT}. Public routing/PHP 8.3 unchanged. Backup: {backup}')
    print('Browser tests and real integration remain pending; no E2E claim.')


if __name__ == '__main__':
    try:
        main()
    except Exception as exc:
        raise SystemExit('STOP: ' + str(exc))
