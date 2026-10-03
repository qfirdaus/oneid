#!/usr/bin/env python3
"""Real-provider PoC on loopback with synthetic identities; no OneID bootstrap/DB."""
import argparse
import base64
import hashlib
import hmac
from http.cookiejar import CookieJar
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
import json
import os
from pathlib import Path
import secrets
import signal
import socket
import subprocess
import threading
import time
import urllib.error
import urllib.parse as url
import urllib.request as http

from prepare import ROOT, RUNTIME, LOCK, guard


class NoRedirect(http.HTTPRedirectHandler):
    def redirect_request(self, *args, **kwargs):
        return None


def opener():
    return http.build_opener(http.ProxyHandler({}), NoRedirect(),
                             http.HTTPCookieProcessor(CookieJar()))


def request(target, method='GET', data=None, headers=None, client=None):
    """Never include token bodies or exception URLs in console output."""
    parsed = url.urlsplit(target)
    if parsed.scheme != 'http' or parsed.hostname != '127.0.0.1':
        raise RuntimeError('PoC HTTP request must remain on loopback')
    headers = dict(headers or {})
    if isinstance(data, dict):
        if headers.get('Content-Type') == 'application/x-www-form-urlencoded':
            data = url.urlencode(data).encode()
        else:
            headers['Content-Type'] = 'application/json'
            data = json.dumps(data).encode()
    req = http.Request(target, data=data, headers=headers, method=method)
    try:
        response = (client or opener()).open(req, timeout=15)
    except urllib.error.HTTPError as e:
        response = e
    raw = response.read()
    try:
        body = json.loads(raw) if raw else {}
    except json.JSONDecodeError:
        body = {}
    return response.code, dict(response.headers), body


def b64(data):
    return base64.urlsafe_b64encode(data).decode().rstrip('=')


def free_port():
    with socket.socket() as s:
        s.bind(('127.0.0.1', 0))
        return s.getsockname()[1]


def legacy_digest():
    """Hash tracked runtime files only; no runtime secret reads."""
    paths = subprocess.check_output(['git', 'ls-files', '-z'], cwd=ROOT).split(b'\0')
    digest = hashlib.sha256()
    count = 0
    for name in sorted(paths):
        if not name:
            continue
        rel = os.fsdecode(name)
        if rel.startswith(('tools/', 'tests/', 'docs/')):
            continue
        p = ROOT / rel
        if p.is_file():
            digest.update(name + b'\0' + hashlib.sha256(p.read_bytes()).digest())
            count += 1
    return {'files': count, 'sha256': digest.hexdigest()}


class Lab:
    def __init__(self, grace='0s'):
        guard()
        self.run_dir = RUNTIME / ('run-' + time.strftime('%Y%m%d-%H%M%S') + '-' + secrets.token_hex(3))
        self.run_dir.mkdir(mode=0o700)
        self.pg_bin = RUNTIME / 'postgres/usr/lib/postgresql/16/bin'
        self.hydra = RUNTIME / 'bin/hydra'
        if hashlib.sha256(self.hydra.read_bytes()).hexdigest() != LOCK['hydra_binary_sha256']:
            raise RuntimeError('Pinned Hydra binary verification failed')
        ports = set()
        while len(ports) < 4:
            ports.add(free_port())
        self.pg_port, self.public_port, self.admin_port, self.mock_port = sorted(ports)
        self.public = f'http://127.0.0.1:{self.public_port}'
        self.admin = f'http://127.0.0.1:{self.admin_port}'
        self.mock = f'http://127.0.0.1:{self.mock_port}'
        self.callback = self.mock + '/callback'
        self.secret = secrets.token_hex(32)
        self.account = {'active': True, 'version': 1, 'password_change_required': False}
        self.sessions = {}
        self.hook_calls = []
        self.hook_failure = False
        self.events = []
        self.processes = []
        self.server = None
        self.grace = grace
        # Do not inherit database/provider/proxy/OneID credentials from the shell.
        self.env = {'PATH': '/usr/bin:/bin', 'LANG': 'C.UTF-8', 'LC_ALL': 'C.UTF-8',
                    'TMPDIR': str(self.run_dir)}
        self.config_path = self.run_dir / 'hydra.json'

    def check(self, label, passed, detail=None):
        event = {'test': label, 'passed': bool(passed)}
        if detail is not None:
            event['detail'] = detail
        self.events.append(event)
        print(('PASS ' if passed else 'FAIL ') + label, flush=True)
        if not passed:
            raise AssertionError(label)

    def execute(self, args, label, timeout=90):
        with (self.run_dir / (label + '.log')).open('ab') as log:
            r = subprocess.run([str(x) for x in args], stdout=log, stderr=log,
                               cwd=self.run_dir, env=self.env, timeout=timeout)
        if r.returncode:
            raise RuntimeError(label + ' failed; inspect private log')

    def spawn(self, args, label):
        with (self.run_dir / (label + '.log')).open('ab') as log:
            p = subprocess.Popen([str(x) for x in args], stdout=log, stderr=log,
                                 cwd=self.run_dir, env=self.env)
        self.processes.append(p)
        return p

    def start(self):
        password = secrets.token_hex(24)
        pwfile = self.run_dir / 'pg-password'
        pwfile.write_text(password)
        datadir = self.run_dir / 'pgdata'
        self.execute([self.pg_bin / 'initdb', '-D', datadir, '-U', 'poc',
                      '--auth-local=scram-sha-256', '--auth-host=scram-sha-256',
                      '--pwfile=' + str(pwfile), '--no-locale', '--encoding=UTF8'], 'initdb')
        self.postgres = self.spawn([self.pg_bin / 'postgres', '-D', datadir,
                                   '-h', '127.0.0.1', '-p', str(self.pg_port),
                                   '-k', str(self.run_dir), '-c', 'max_connections=20',
                                   '-c', 'shared_buffers=32MB'], 'postgres')
        self.wait_port(self.pg_port, self.postgres)
        dsn = f'postgres://poc:{password}@127.0.0.1:{self.pg_port}/postgres?sslmode=disable'
        config = {
            'dsn': dsn,
            'serve': {'public': {'host': '127.0.0.1', 'port': self.public_port},
                      'admin': {'host': '127.0.0.1', 'port': self.admin_port}},
            'urls': {'self': {'issuer': self.public + '/', 'public': self.public + '/'},
                     'login': self.mock + '/login', 'consent': self.mock + '/consent'},
            'secrets': {'system': [secrets.token_hex(32)]},
            'log': {'level': 'error', 'leak_sensitive_values': False},
            'ttl': {'access_token': '5s', 'refresh_token': '1h', 'auth_code': '30s'},
            'oauth2': {'pkce': {'enforced': True, 'enforced_for_public_clients': True},
                       'grant': {'refresh_token': {'rotation_grace_period': self.grace,
                                                   # Hydra counts the initial use: 2 = one initial use + one retry.
                                                   'rotation_grace_reuse_count': 2 if self.grace != '0s' else 0}},
                       'token_hook': {'url': self.mock + '/token-hook',
                                      'auth': {'type': 'api_key', 'config': {
                                          'in': 'header', 'name': 'X-PoC-Hook-Key', 'value': self.secret}}}},
        }
        self.config_path.write_text(json.dumps(config))
        self.start_mock()
        self.execute([self.hydra, 'migrate', 'sql', 'up', '-e', '-y', '-c', self.config_path], 'migration')
        self.start_hydra()
        for client_id in ['poc-android', 'poc-ios']:
            status, _, _ = request(self.admin + '/admin/clients', 'POST', {
                'client_id': client_id, 'client_name': 'SYNTHETIC UAT ONLY',
                'redirect_uris': [self.callback], 'grant_types': ['authorization_code', 'refresh_token'],
                'response_types': ['code'], 'scope': 'openid profile offline_access mobile:session',
                'audience': ['oneid-mobile-session'], 'token_endpoint_auth_method': 'none',
            })
            self.check('register public client ' + client_id, status == 201)

    def wait_port(self, port, proc):
        deadline = time.monotonic() + 25
        while time.monotonic() < deadline:
            if proc.poll() is not None:
                raise RuntimeError('Isolated service exited; inspect private log')
            try:
                with socket.create_connection(('127.0.0.1', port), timeout=.2):
                    return
            except OSError:
                time.sleep(.1)
        raise RuntimeError('Isolated service start timeout')

    def start_hydra(self):
        self.provider = self.spawn([self.hydra, 'serve', 'all', '--dev', '--sqa-opt-out',
                                    '-c', self.config_path], 'hydra')
        self.wait_port(self.public_port, self.provider)
        deadline = time.monotonic() + 20
        while time.monotonic() < deadline:
            try:
                if request(self.admin + '/health/ready')[0] == 200:
                    return
            except OSError:
                pass
            time.sleep(.1)
        raise RuntimeError('Hydra not ready')

    def restart_provider(self):
        self.provider.terminate()
        self.provider.wait(timeout=10)
        self.start_hydra()

    def start_mock(self):
        lab = self

        class Handler(BaseHTTPRequestHandler):
            def log_message(self, *args):
                pass  # Never log bearer/header/body/query material.

            def send(self, status, body=None):
                data = json.dumps(body or {}).encode()
                self.send_response(status)
                self.send_header('Content-Type', 'application/json')
                self.send_header('Cache-Control', 'no-store')
                self.send_header('Content-Length', str(len(data)))
                self.end_headers()
                self.wfile.write(data)

            def do_POST(self):
                if self.path != '/token-hook':
                    self.send(404)
                    return
                if not hmac.compare_digest(self.headers.get('X-PoC-Hook-Key', ''), lab.secret):
                    self.send(403)
                    return
                if lab.hook_failure:
                    self.send(503)
                    return
                length = int(self.headers.get('Content-Length', '0'))
                if length > 65536:
                    self.send(413)
                    return
                payload = json.loads(self.rfile.read(length))
                context = payload.get('session', {}).get('extra', {})
                sid = context.get('poc_sid')
                grant = payload.get('request', {}).get('grant_types', [])
                client_id = payload.get('request', {}).get('client_id')
                record = lab.sessions.get(sid)
                permitted = bool(record and record['active'] and lab.account['active']
                                 and not lab.account['password_change_required']
                                 and record['client_id'] == client_id
                                 and context.get('security_version') == lab.account['version'])
                lab.hook_calls.append({'grant': grant, 'permitted': permitted,
                                       'session_context_present': sid is not None})
                self.send(204 if permitted else 403)

            def do_GET(self):
                if self.path != '/mobile/session':
                    self.send(404)
                    return
                header = self.headers.get('Authorization', '')
                token = header[7:] if header.startswith('Bearer ') else ''
                if not token:
                    self.send(401, {'error': 'SESSION_INVALID'})
                    return
                result = lab.introspect(token)
                extra = result.get('ext', {})
                record = lab.sessions.get(extra.get('poc_sid'))
                if not (result.get('active') and 'oneid-mobile-session' in result.get('aud', [])
                        and 'mobile:session' in result.get('scope', '').split()
                        and result.get('client_id') in ['poc-android', 'poc-ios']
                        and result.get('sub') == 'POC_STAFF_001'
                        and record and record['active']
                        and record['client_id'] == result.get('client_id')):
                    self.send(401, {'error': 'SESSION_INVALID'})
                elif not lab.account['active']:
                    self.send(403, {'error': 'ACCOUNT_UNAVAILABLE'})
                elif lab.account['password_change_required']:
                    self.send(403, {'error': 'PASSWORD_CHANGE_REQUIRED'})
                elif extra.get('security_version') != lab.account['version']:
                    self.send(401, {'error': 'SESSION_INVALID'})
                else:
                    self.send(200, {'session_status': 'active', 'sub': result['sub']})

        self.server = ThreadingHTTPServer(('127.0.0.1', self.mock_port), Handler)
        self.server.daemon_threads = True
        threading.Thread(target=self.server.serve_forever, daemon=True).start()

    def code(self, client_id='poc-android', pkce='S256', callback=None):
        browser = opener()
        verifier = secrets.token_urlsafe(48)
        state = secrets.token_urlsafe(24)
        nonce = secrets.token_urlsafe(24)
        sid = secrets.token_hex(16)
        self.sessions[sid] = {'active': True, 'client_id': client_id}
        params = {'client_id': client_id, 'redirect_uri': callback or self.callback,
                  'response_type': 'code', 'scope': 'openid profile offline_access mobile:session',
                  'audience': 'oneid-mobile-session', 'state': state, 'nonce': nonce}
        if pkce:
            params.update(code_challenge=b64(hashlib.sha256(verifier.encode()).digest()) if pkce == 'S256' else verifier,
                          code_challenge_method=pkce)
        target = self.public + '/oauth2/auth?' + url.urlencode(params)
        for _ in range(12):
            status, headers, body = request(target, client=browser)
            location = headers.get('Location', '')
            if not location:
                return {'error': body.get('error', 'http_' + str(status)), 'status': status}
            location = url.urljoin(target, location)
            parsed = url.urlsplit(location)
            args = dict(url.parse_qsl(parsed.query))
            if args.get('error'):
                return {'error': args['error'], 'status': status}
            if location.startswith(self.callback + '?'):
                if args.get('state') != state:
                    raise AssertionError('Callback state mismatch')
                return {'code': args['code'], 'verifier': verifier, 'nonce': nonce, 'sid': sid,
                        'client_id': client_id}
            if location.startswith(self.mock + '/login?'):
                challenge = args['login_challenge']
                code, _, payload = request(self.admin + '/admin/oauth2/auth/requests/login?' +
                                            url.urlencode({'login_challenge': challenge}))
                if code != 200 or payload['client']['client_id'] != client_id:
                    raise AssertionError('Login challenge/client mismatch')
                code, _, accepted = request(self.admin + '/admin/oauth2/auth/requests/login/accept?' +
                                             url.urlencode({'login_challenge': challenge}), 'PUT', {
                    'subject': 'POC_STAFF_001', 'remember': False, 'acr': 'poc-fixture',
                    'amr': ['pwd'], 'context': {'poc_sid': sid}})
                if code != 200:
                    raise AssertionError('Fake login acceptance failed')
                target = accepted['redirect_to']
            elif location.startswith(self.mock + '/consent?'):
                challenge = args['consent_challenge']
                code, _, consent = request(self.admin + '/admin/oauth2/auth/requests/consent?' +
                                           url.urlencode({'consent_challenge': challenge}))
                if code != 200:
                    raise AssertionError('Consent read failed')
                extra = {'poc_sid': sid, 'security_version': self.account['version']}
                code, _, accepted = request(self.admin + '/admin/oauth2/auth/requests/consent/accept?' +
                                             url.urlencode({'consent_challenge': challenge}), 'PUT', {
                    'grant_scope': consent['requested_scope'],
                    'grant_access_token_audience': consent['requested_access_token_audience'],
                    'remember': False, 'session': {'access_token': extra, 'id_token': {
                        'name': 'Synthetic PoC Staff', 'account_type': 'staff'}}})
                if code != 200:
                    raise AssertionError('Consent accept failed')
                target = accepted['redirect_to']
            elif location.startswith(self.public + '/'):
                target = location
            else:
                raise AssertionError('Unexpected redirect destination')
        raise AssertionError('Redirect count exceeded')

    def exchange(self, authorization, **overrides):
        data = {'grant_type': 'authorization_code', 'client_id': authorization['client_id'],
                'code': authorization['code'], 'redirect_uri': self.callback,
                'code_verifier': authorization['verifier']}
        data.update(overrides)
        return request(self.public + '/oauth2/token', 'POST', data,
                       {'Content-Type': 'application/x-www-form-urlencoded'})

    def login(self, client_id='poc-android'):
        authorization = self.code(client_id)
        if 'code' not in authorization:
            raise AssertionError('Authorization failed: ' + authorization.get('error', 'unknown'))
        status, _, token = self.exchange(authorization)
        self.check('code exchange ' + client_id, status == 200 and 'refresh_token' in token,
                   {'http_status': status, 'error': token.get('error')})
        return authorization, token

    def refresh(self, token, client_id='poc-android'):
        return request(self.public + '/oauth2/token', 'POST', {
            'grant_type': 'refresh_token', 'client_id': client_id, 'refresh_token': token},
            {'Content-Type': 'application/x-www-form-urlencoded'})

    def introspect(self, token):
        status, _, body = request(self.admin + '/admin/oauth2/introspect', 'POST', {'token': token},
                                  {'Content-Type': 'application/x-www-form-urlencoded'})
        if status != 200:
            raise AssertionError('Provider introspection failed')
        return body

    def mobile_status(self, token):
        return request(self.mock + '/mobile/session', headers={'Authorization': 'Bearer ' + token})[0]

    def close(self):
        if self.server:
            self.server.shutdown()
            self.server.server_close()
        for p in reversed(self.processes):
            if p.poll() is None:
                if p is getattr(self, 'postgres', None):
                    p.send_signal(signal.SIGINT)  # PostgreSQL fast, clean shutdown.
                else:
                    p.terminate()
                try:
                    p.wait(timeout=12)
                except subprocess.TimeoutExpired:
                    p.kill()
                    p.wait(timeout=5)


def run_tests(lab):
    status, _, discovery = request(lab.public + '/.well-known/openid-configuration')
    lab.check('discovery issuer and public code flow', status == 200 and discovery['issuer'] == lab.public + '/')
    status, _, jwks = request(discovery['jwks_uri'])
    lab.check('JWKS publishes public keys', status == 200 and bool(jwks.get('keys')) and
              all('d' not in k for k in jwks['keys']))
    lab.check('unauthenticated hook rejected', request(lab.mock + '/token-hook', 'POST', {})[0] == 403)
    lab.check('authenticated hook rejects missing session binding', request(
        lab.mock + '/token-hook', 'POST', {}, {'X-PoC-Hook-Key': lab.secret})[0] == 403)
    lab.check('missing PKCE rejected', 'error' in lab.code(pkce=None))
    lab.check('plain PKCE rejected', 'error' in lab.code(pkce='plain'))
    lab.check('unregistered redirect rejected', 'error' in lab.code(callback=lab.mock + '/wrong'))
    bad = lab.code()
    lab.check('wrong PKCE verifier rejected', lab.exchange(bad, code_verifier=secrets.token_urlsafe(48))[0] == 400)
    bad_client = lab.code()
    lab.check('code cannot be exchanged by another public client', lab.exchange(bad_client, client_id='poc-ios')[0] == 400)
    authorization, first = lab.login()
    claims = json.loads(base64.urlsafe_b64decode(first['id_token'].split('.')[1] + '==='))
    # Decoding is used for assertions only; NOT a production JWT verifier.
    lab.check('ID token has bound issuer subject audience nonce',
              claims['iss'] == lab.public + '/' and claims['sub'] == 'POC_STAFF_001'
              and 'poc-android' in claims['aud'] and claims['nonce'] == authorization['nonce'])
    status, _, user = request(lab.public + '/userinfo', headers={'Authorization': 'Bearer ' + first['access_token']})
    lab.check('userinfo returns fake identity only', status == 200 and user.get('sub') == 'POC_STAFF_001')
    lab.check('session endpoint validates access token', lab.mobile_status(first['access_token']) == 200)
    lab.check('ID token cannot replace access token', lab.mobile_status(first['id_token']) == 401)
    time.sleep(6)
    lab.check('expired access token rejected', not lab.introspect(first['access_token']).get('active'))
    status, _, rotated = lab.refresh(first['refresh_token'])
    lab.check('refresh restores access without interactive login', status == 200 and
              rotated.get('refresh_token') != first['refresh_token'])
    lab.check('refresh hook receives trusted session context', any(
        'refresh_token' in c['grant'] and c['session_context_present'] and c['permitted'] for c in lab.hook_calls))
    if lab.grace == '0s':
        status, _, error = lab.refresh(first['refresh_token'])
        lab.check('used refresh token replay rejected', status == 400 and error.get('error') == 'invalid_grant')
        lab.check('refresh replay invalidates successor', lab.refresh(rotated['refresh_token'])[0] != 200)
    else:
        status, _, retry = lab.refresh(first['refresh_token'])
        lab.check('lost-response retry allowed once within configured grace', status == 200)
        lab.check('retry response refresh token remains usable', lab.refresh(retry['refresh_token'])[0] == 200)
        status, _, error = lab.refresh(first['refresh_token'])
        lab.check('grace reuse count prevents unlimited old-token retries', status != 200)
        _, timed = lab.login()
        lab.check('initial refresh before grace expiry succeeds', lab.refresh(timed['refresh_token'])[0] == 200)
        time.sleep(6)
        lab.check('old refresh rejected after grace window', lab.refresh(timed['refresh_token'])[0] != 200)

    _, persistent = lab.login()
    lab.restart_provider()
    status, _, restored = lab.refresh(persistent['refresh_token'])
    lab.check('refresh survives provider restart with persistent database and keys', status == 200)
    # Two separate authorizations on the SAME client simulate two installations.
    _, device_a = lab.login()
    _, device_b = lab.login()
    revoke, _, _ = request(lab.public + '/oauth2/revoke', 'POST', {
        'client_id': 'poc-android', 'token': device_a['refresh_token'], 'token_type_hint': 'refresh_token'},
        {'Content-Type': 'application/x-www-form-urlencoded'})
    lab.check('per-device refresh revocation accepted', revoke == 200)
    lab.check('revocation invalidates device access token', not lab.introspect(device_a['access_token']).get('active'))
    lab.check('logged-out device cannot refresh', lab.refresh(device_a['refresh_token'])[0] != 200)
    status, _, device_b = lab.refresh(device_b['refresh_token'])
    lab.check('logout leaves second device on same client active', status == 200)
    _, ios = lab.login('poc-ios')
    lab.check('refresh token bound to original client', lab.refresh(ios['refresh_token'], 'poc-android')[0] != 200)
    lab.check('original iOS client can still refresh', lab.refresh(ios['refresh_token'], 'poc-ios')[0] == 200)

    _, disabled = lab.login()
    lab.account['active'] = False
    lab.check('disabled account denied by session status', lab.mobile_status(disabled['access_token']) == 403)
    status, _, error = lab.refresh(disabled['refresh_token'])
    lab.check('disabled account blocked during refresh hook', status != 200)
    lab.account['version'] += 1
    lab.account['active'] = True
    lab.check('reactivation cannot resurrect pre-disable session', lab.refresh(disabled['refresh_token'])[0] != 200)
    _, reset = lab.login()
    lab.account['password_change_required'] = True
    lab.check('forced password change blocks session status', lab.mobile_status(reset['access_token']) == 403)
    lab.check('forced password change blocks refresh', lab.refresh(reset['refresh_token'])[0] != 200)
    lab.account['password_change_required'] = False
    lab.account['version'] += 1
    lab.check('security version invalidates pre-reset session', lab.refresh(reset['refresh_token'])[0] != 200)

    _, outage = lab.login()
    lab.hook_failure = True
    status, _, error = lab.refresh(outage['refresh_token'])
    lab.check('hook outage fails closed', status != 200)
    lab.hook_failure = False
    lab.check('transient hook outage does not consume refresh credential', lab.refresh(outage['refresh_token'])[0] == 200)
    replay_code, replay_tokens = lab.login()
    lab.check('authorization code replay rejected', lab.exchange(replay_code)[0] == 400)
    lab.check('code replay invalidates issued access token', not lab.introspect(replay_tokens['access_token']).get('active'))


def main():
    p = argparse.ArgumentParser(description=__doc__)
    p.add_argument('--uat-only', action='store_true', required=True)
    p.add_argument('--grace', choices=['0s', '5s'], default='0s')
    args = p.parse_args()
    before = legacy_digest()
    lab = Lab(args.grace)
    failure = None
    # Ensure cleanup on interruption; no daemon/service left running.
    def interrupted(*_):
        raise KeyboardInterrupt()
    signal.signal(signal.SIGTERM, interrupted)
    try:
        lab.start()
        run_tests(lab)
    except (Exception, KeyboardInterrupt) as e:
        failure = type(e).__name__
        print('RUN STOPPED: ' + failure + '; private run evidence retained.', flush=True)
    finally:
        lab.close()
    after = legacy_digest()
    lab.events.append({'test': 'tracked legacy runtime files unchanged', 'passed': before == after})
    lab.events.append({'test': 'all child services stopped', 'passed': all(p.poll() is not None for p in lab.processes)})
    report = {'provider': LOCK['hydra_version'], 'provider_commit': LOCK['hydra_commit'],
              'scope': 'UAT loopback only; fake identity; isolated PostgreSQL',
              'grace': args.grace, 'access_token_ttl_seconds': 5, 'refresh_ttl': '1h (test only)',
              'rotation_grace_reuse_count': 2 if args.grace != '0s' else 0,
              'failure': failure, 'tests': lab.events, 'hook_calls': lab.hook_calls,
              'legacy_before': before, 'legacy_after': after,
              'limits': ['No real OneID DB/credentials/MFA used', 'No Flutter/real-device/TLS validation',
                         'Refresh only simulated short TTL; no infinite-session policy established',
                         'Token claims decoded for assertions; no client-side signature-verifier certification']}
    report_path = lab.run_dir / 'report.json'
    report_path.write_text(json.dumps(report, indent=2) + '\n')
    print('Report: ' + str(report_path), flush=True)
    passed = sum(e['passed'] for e in lab.events)
    print(f'Result: {passed}/{len(lab.events)} checks passed; services stopped.', flush=True)
    return 1 if failure or passed != len(lab.events) else 0


if __name__ == '__main__':
    raise SystemExit(main())
