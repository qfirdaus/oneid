#!/usr/bin/env python3
"""Unprivileged isolated Nginx render/HTTP smoke, temporary TLS certificate only."""
import json
from pathlib import Path
import re
import socket
import subprocess
import tempfile
import time
from php84_browser_listener import render, HOST, ROOT

config = render()
assert re.findall(r'^\s*listen\s+([^;]+);', config, re.M) == ['127.0.0.1:24484 ssl http2']
assert config.count('fastcgi_pass unix:/run/php/oneid-web-uat84.sock;') == 1
assert config.count('fastcgi_pass unix:/run/php/oneid-mobile-uat84.sock;') == 7
assert 'php8.3-fpm.sock' not in config and 'oneid-mobile-uat.sock' not in config
assert config.count('X-OneID-Test-Runtime') == 8
with tempfile.TemporaryDirectory(prefix='oneid-nginx-check-') as directory:
    p = Path(directory)
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0)); port = sock.getsockname()[1]
    subprocess.run(['openssl', 'req', '-x509', '-newkey', 'rsa:2048', '-nodes', '-days', '1',
        '-subj', '/CN=' + HOST, '-addext', 'subjectAltName=DNS:' + HOST,
        '-keyout', str(p/'key.pem'), '-out', str(p/'cert.pem')],
        stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, check=True)
    config = config.replace('127.0.0.1:24484 ssl', f'127.0.0.1:{port} ssl')
    config = re.sub(r'ssl_certificate\s+[^;]+;', f'ssl_certificate {p}/cert.pem;', config)
    config = re.sub(r'ssl_certificate_key\s+[^;]+;', f'ssl_certificate_key {p}/key.pem;', config)
    config = re.sub(r'access_log [^;]+;', 'access_log off;', config)
    prefix = f'error_log stderr;\npid {p}/nginx.pid;\nevents {{}}\nhttp {{\n'
    for name in ['client_body', 'proxy', 'fastcgi', 'uwsgi', 'scgi']:
        prefix += f'{name}_temp_path {p}/{name};\n'
    (p/'nginx.conf').write_text(prefix + config + '\n}\n')
    command = ['/usr/sbin/nginx', '-p', directory, '-c', str(p/'nginx.conf')]
    subprocess.run(command + ['-t'], check=True, capture_output=True)
    process = subprocess.Popen(command + ['-g', 'daemon off;'], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    try:
        time.sleep(1)
        if process.poll() is not None:
            raise RuntimeError('Temporary Nginx failed to start')
        results = []
        for path, allowed, php in [('/', {200}, True), ('/login.css', {200}, True),
                                   ('/mobile/session', {401,403}, True),
                                   ('/.well-known/openid-configuration', {200}, False)]:
            result = subprocess.run(['curl', '--silent', '--show-error', '--noproxy', '*', '--max-time', '20',
                '--cacert', str(p/'cert.pem'), '--resolve', f'{HOST}:{port}:127.0.0.1', '-H', 'Host: ' + HOST,
                '-D', '-', '-o', '/dev/null', f'https://{HOST}:{port}{path}'], capture_output=True, text=True, check=True)
            code = int(re.findall(r'^HTTP/\S+ (\d+)', result.stdout, re.M)[-1])
            marker = 'x-oneid-test-runtime: php84-isolated' in result.stdout.lower()
            results.append({'path': path, 'status': code, 'php84_marker': marker,
                            'pass': code in allowed and (not php or marker)})
        report = {'scope': 'Real UAT unauthenticated HTTP bootstrap through temporary loopback Nginx and FPM 8.4; temporary certificate trusted explicitly. No login or real provider flow tested.', 'results': results}
        (ROOT/'docs/php84/phase4-http-smoke.json').write_text(json.dumps(report, indent=2)+'\n')
        print(json.dumps(report, indent=2))
        if not all(r['pass'] for r in results):
            raise SystemExit(1)
    finally:
        process.terminate()
        try:
            process.wait(timeout=10)
        except subprocess.TimeoutExpired:
            process.kill(); process.wait()
