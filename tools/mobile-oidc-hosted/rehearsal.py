#!/usr/bin/env python3
"""Full browser/HTTP rehearsal: isolated PHP + MySQL + Hydra + PostgreSQL, UAT only."""
import base64
import hashlib
import hmac
import json
from pathlib import Path
import re
import secrets
import signal
import struct
import sys
import time
import urllib.error
import urllib.parse as url
import urllib.request as http

sys.dont_write_bytecode = True
ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / 'tools/mobile-oidc-poc'))
from run import Lab, opener, free_port, b64, legacy_digest
from prepare import RUNTIME
from private_mysql import PrivateMysql


def fetch(target, method='GET', data=None, headers=None, browser=None):
    parsed = url.urlsplit(target)
    if parsed.hostname != '127.0.0.1' or parsed.scheme != 'http':
        raise RuntimeError('Loopback only')
    headers = dict(headers or {})
    if isinstance(data, dict):
        data = url.urlencode(data).encode()
        headers.setdefault('Content-Type', 'application/x-www-form-urlencoded')
    try:
        response = (browser or opener()).open(http.Request(target, data=data, method=method, headers=headers), timeout=20)
    except urllib.error.HTTPError as error:
        response = error
    return response.code, dict(response.headers), response.read().decode()


def php_return(value):
    encoded = base64.b64encode(json.dumps(value).encode()).decode()
    return "<?php return json_decode(base64_decode('" + encoded + "'), true);\n"


class HostedLab(Lab):
    def __init__(self):
        super().__init__('0s')
        self.mysql = PrivateMysql(ROOT, RUNTIME)
        self.hosted_port = free_port()
        self.hosted = f'http://127.0.0.1:{self.hosted_port}'
        self.php_config = self.run_dir / 'hosted.php'
        self.sink = self.run_dir / 'otp.json'
        self.keyring = self.run_dir / 'totp.php'
        self.totp_secret = None

    def start(self):
        self.mysql.start()
        initial = self.mysql.action('init')
        self.check('fixture lifecycle triggers installed and enabled only in private database', initial['ready'] and initial['triggers'] == 28)
        super().start()
        self.provider.terminate()
        self.provider.wait(timeout=10)
        self.keyring.write_text(php_return({'active_version': 'fixture', 'keys': {'fixture': b64(secrets.token_bytes(32)).replace('-', '+').replace('_', '/') + '='}}))
        (self.run_dir / 'sessions').mkdir(mode=0o700)
        self.settings = {'enabled': True, 'environment': 'staging', 'loopback_fixture': True,
            'database_scope_confirmed': True, 'origin': self.hosted, 'admin_url': self.admin,
            'issuer': self.public, 'clients': {'poc-android': [self.callback], 'poc-ios': [self.callback]},
            'dsn': 'mysql:unix_socket=' + str(self.mysql.socket) + ';dbname=oneid_mobile_hosted_fixture;charset=utf8mb4',
            'db_user': 'root', 'db_password': '', 'mfa_runtime_mode': 'ENFORCED', 'mfa_activation_authorized': True,
            'binding_key': secrets.token_hex(32), 'hook_key': self.secret,
            'session_path': str(self.run_dir / 'sessions'), 'totp_keyring': str(self.keyring)}
        self.write_settings()
        self.start_php()
        config = json.loads(self.config_path.read_text())
        config['urls']['login'] = self.hosted + '/login'
        config['urls']['consent'] = self.hosted + '/consent'
        config['ttl']['access_token'] = '120s'
        config['ttl']['refresh_token'] = '-1'
        config['oauth2']['token_hook']['url'] = self.hosted + '/token-hook'
        config['oauth2']['token_hook']['auth']['config']['name'] = 'X-OneID-Hook-Key'
        self.config_path.write_text(json.dumps(config))
        self.start_hydra()

    def write_settings(self):
        encoded = base64.b64encode(json.dumps(self.settings).encode()).decode()
        self.php_config.write_text("<?php\n$c=json_decode(base64_decode('" + encoded + "'),true);\n" +
            "$c['delivery_factory']=static fn()=>new class implements \\OneId\\App\\Auth\\MobileOidc\\OtpDelivery {\n" +
            "public function send(string $destination,string $name,string $otp):bool {\n" +
            "file_put_contents('" + str(self.sink) + "',json_encode(['otp'=>$otp]),LOCK_EX); return true; }};\nreturn $c;\n")
        self.php_config.chmod(0o600)

    def start_php(self):
        self.env['ONEID_MOBILE_CONFIG'] = str(self.php_config)
        self.php = self.spawn(['php', '-d', 'display_errors=0', '-S', f'127.0.0.1:{self.hosted_port}',
                              '-t', ROOT / 'mobile-public', ROOT / 'mobile-public/index.php'], 'hosted')
        self.wait_port(self.hosted_port, self.php)

    def status(self, token, logout=False):
        return fetch(self.hosted + ('/mobile/logout' if logout else '/mobile/session'),
                     'POST' if logout else 'GET', headers={'Authorization': 'Bearer ' + token})

    def csrf(self, body):
        found = re.search(r'name="csrf" value="([a-f0-9]{64})"', body)
        if not found:
            (self.run_dir / 'failed-page.html').write_text(body)
            raise AssertionError('Hosted form missing CSRF')
        return found[1]

    def post(self, browser, body, **fields):
        return fetch(self.hosted + '/login', 'POST', {'csrf': self.csrf(body), **fields}, {'Origin': self.hosted}, browser)

    def login(self, kind='staff', password='Fixture-only!4927', change=None, cancel=False, attacks=False):
        self.mysql.action('clear_rate')  # isolate scenarios; not an application endpoint.
        browser = opener()
        verifier, state, nonce = [secrets.token_urlsafe(32) for _ in range(3)]
        target = self.public + '/oauth2/auth?' + url.urlencode({'client_id': 'poc-android', 'redirect_uri': self.callback,
            'response_type': 'code', 'scope': 'openid profile offline_access mobile:session', 'audience': 'oneid-mobile-session',
            'state': state, 'nonce': nonce, 'code_challenge_method': 'S256', 'code_challenge': b64(hashlib.sha256(verifier.encode()).digest())})
        for _ in range(15):
            status, headers, body = fetch(target, browser=browser)
            if target.startswith(self.hosted + '/login') and status == 200:
                self.check(kind + ': hosted login form is no-store and frame protected', headers.get('Cache-Control') == 'no-store'
                           and 'frame-ancestors' in headers.get('Content-Security-Policy', ''))
                cookie = headers.get('Set-Cookie', '')
                self.check(kind + ': separate HttpOnly mobile cookie, no legacy web cookie', 'oneid_mobile_fixture=' in cookie
                           and 'HttpOnly' in cookie and 'SameSite=Lax' in cookie and 'PHPSESSID' not in cookie and 'sso_cre' not in cookie)
                original_csrf = self.csrf(body)
                status, headers, body = fetch(self.hosted + '/login?lang=en', browser=browser)
                self.check(kind + ': language switches to English without restarting login', status == 200 and 'Staff / student number' in body and self.csrf(body) == original_csrf and headers.get('Content-Language') == 'en')
                status, headers, body = fetch(self.hosted + '/login?lang=ms', browser=browser)
                if attacks:
                    result = fetch(self.hosted + '/login', 'POST', {'csrf': self.csrf(body), 'action': 'password'},
                                   {'Origin': 'https://evil.invalid'}, browser)
                    self.check('foreign Origin cannot submit password form', result[0] == 403)
                    result = fetch(self.hosted + '/login', 'POST', {'csrf': 'wrong', 'action': 'password'}, {'Origin': self.hosted}, browser)
                    self.check('invalid CSRF cannot submit password form', result[0] == 403)
                if cancel:
                    status, headers, body = self.post(browser, body, action='cancel')
                else:
                    status, headers, body = self.post(browser, body, action='password', identifier='0530-09' if kind == 'staff' else 'M123456', password=password)
                    if kind != 'staff':
                        self.check(kind + ': password alone stops at MFA', status == 200 and 'name="factor"' in body and 'name="code"' in body)
                        mfa_csrf = self.csrf(body)
                        status, headers, body = fetch(self.hosted + '/login?lang=en', browser=browser)
                        self.check(kind + ': switching MFA language retains pending verification', status == 200 and 'Verify your identity' in body and self.csrf(body) == mfa_csrf)
                        status, headers, body = fetch(self.hosted + '/login?lang=ms', browser=browser)
                        if kind == 'totp':
                            step = int(time.time()) // 30
                            raw = hmac.new(base64.b32decode(self.totp_secret + '=' * (-len(self.totp_secret) % 8)), struct.pack('>Q', step), hashlib.sha1).digest()
                            offset = raw[-1] & 15
                            otp = f'{(struct.unpack(">I", raw[offset:offset+4])[0] & 0x7fffffff) % 1000000:06d}'
                            status, headers, body = self.post(browser, body, action='verify', factor='totp', code=otp)
                        else:
                            status, headers, body = self.post(browser, body, action='send_email')
                            self.check(kind + ': email delivery response contains no OTP', status == 200 and 'Kod telah dihantar' in body)
                            otp = json.loads(self.sink.read_text())['otp']
                            status, headers, body = self.post(browser, body, action='verify', factor='email', code=otp)
                    if change:
                        self.check('mandatory password change follows completed MFA', status == 200 and 'Tukar kata laluan' in body)
                        status, headers, body = self.post(browser, body, action='change', new_password='short', confirmation='short')
                        self.check('hosted password quality validation refuses weak password', status == 200 and 'Tukar kata laluan' in body)
                        status, headers, body = self.post(browser, body, action='change', new_password=password, confirmation=password)
                        self.check('hosted password reuse validation refuses current password', status == 200 and 'belum digunakan' in body)
                        status, headers, body = self.post(browser, body, action='change', new_password=change, confirmation=change)
                    self.check(kind + ': completed hosted authentication redirects to provider', status == 303)
            location = url.urljoin(target, headers.get('Location', ''))
            if location.startswith(self.callback + '?'):
                args = dict(url.parse_qsl(url.urlsplit(location).query))
                self.check('callback binds original state', args.get('state') == state)
                if cancel:
                    self.check('cancel returns access_denied without authorization code', args.get('error') == 'access_denied' and 'code' not in args)
                    return None
                status, _, raw = fetch(self.public + '/oauth2/token', 'POST', {'grant_type': 'authorization_code',
                    'client_id': 'poc-android', 'code': args.get('code', ''), 'redirect_uri': self.callback, 'code_verifier': verifier})
                token = json.loads(raw)
                if status != 200:
                    (self.run_dir / 'token-failure.json').write_text(json.dumps({'status': status, 'error': token.get('error'), 'description': token.get('error_description')}))
                self.check(kind + ': actual authenticated token hook permits PKCE exchange', status == 200)
                claims = json.loads(base64.urlsafe_b64decode(token['id_token'].split('.')[1] + '==='))
                self.check(kind + ': token claims preserve nonce and actual MFA assurance', claims['nonce'] == nonce and claims['amr'] == (['pwd'] if kind == 'staff' else ['pwd', 'otp' if kind == 'totp' else 'email']))
                self.check(kind + ': online session accepts issued access token', self.status(token['access_token'])[0] == 200)
                return token
            if location == target or not location.startswith((self.public + '/', self.hosted + '/')):
                (self.run_dir / 'failed-page.html').write_text(body)
                raise AssertionError('Unexpected hosted browser transition')
            target = location
        raise AssertionError('Hosted redirect limit')

    def close(self):
        super().close()
        self.mysql.close()


def tests(lab):
    lab.check('unauthenticated token hook rejected', fetch(lab.hosted + '/token-hook', 'POST', b'{}', {'Content-Type': 'application/json'})[0] == 403)
    lab.check('session without bearer rejected', fetch(lab.hosted + '/mobile/session')[0] == 401)
    staff = lab.login(attacks=True)
    lab.check('ID token cannot be used as session access token', lab.status(staff['id_token'])[0] == 401)
    lab.check('refresh credential cannot be used as session access token', lab.status(staff['refresh_token'])[0] == 401)
    before = lab.mysql.action('inspect')
    lab.check('ordinary mobile login leaves web tokens active', all(r['status'] == 1 for r in before['web_tokens']))
    rehashed = lab.mysql.action('legacy_rehash')
    lab.check('legacy password algorithm rehash does not invalidate mobile session', rehashed['epochs'] == before['epochs']
              and lab.status(staff['access_token'])[0] == 200)
    stale = lab.mysql.action('stale_rehash')
    lab.check('stale legacy rehash cannot overwrite a concurrently changed password', stale['epochs'] == rehashed['epochs']
              and lab.status(staff['access_token'])[0] == 200)
    lab.login(cancel=True)
    _, _, rotated = lab.refresh(staff['refresh_token'])
    lab.check('real hook allows refresh without browser login', 'access_token' in rotated)
    lab.php.terminate(); lab.php.wait(timeout=10); lab.start_php()
    lab.restart_provider()
    lab.check('full adapter and provider restart preserves SQL mobile session', lab.status(rotated['access_token'])[0] == 200)
    lab.mysql.action('rollback_disable')
    lab.check('rolled-back account update also rolls back invalidation', lab.status(rotated['access_token'])[0] == 200)
    lab.mysql.action('disable_enable')
    lab.check('disable-enable between requests invalidates previous session', lab.status(rotated['access_token'])[0] == 401)
    lab.check('disable-enable blocks old refresh through real hook', lab.refresh(rotated['refresh_token'])[0] != 200)
    device_a = lab.login(); device_b = lab.login()
    lab.check('hosted logout revokes only this installation', lab.status(device_a['access_token'], True)[0] == 204
              and lab.status(device_b['access_token'])[0] == 200)
    lab.check('logged-out installation cannot refresh', lab.refresh(device_a['refresh_token'])[0] != 200)
    lab.mysql.action('maintenance_on')
    lab.check('maintenance returns retryable status without granting access', lab.status(device_b['access_token'])[0] == 503)
    lab.check('maintenance refuses refresh', lab.refresh(device_b['refresh_token'])[0] != 200)
    lab.mysql.action('maintenance_off')
    code, _, after_maintenance = lab.refresh(device_b['refresh_token'])
    lab.check('temporary maintenance does not consume valid refresh token', code == 200)
    lab.mysql.action('policy_cycle')
    lab.check('temporary category-policy changes permanently retire prior assurance', lab.refresh(after_maintenance['refresh_token'])[0] != 200)
    old = lab.login()
    old_sub = json.loads(lab.status(old['access_token'])[2])['sub']
    lab.mysql.action('delete_recreate')
    lab.check('delete-recreate cannot resurrect old subject session', lab.status(old['access_token'])[0] == 401)
    recreated = lab.login()
    lab.check('recreated user ID receives a new stable subject', json.loads(lab.status(recreated['access_token'])[2])['sub'] != old_sub)
    lab.mysql.action('observer_cycle')
    lab.check('observer OFF-ON retires prior sessions', lab.status(recreated['access_token'])[0] == 401)
    student = lab.login('student')
    lab.mysql.action('force_change')
    changed = lab.login('student', change='Changed!8Raven#2026')
    state = lab.mysql.action('inspect')
    lab.check('password change saves history and invalidates reset OTPs', state['history_count'] == 1 and state['active_reset_otps'] == 0)
    lab.check('password change revokes only affected user web sessions', state['web_tokens'] == [{'user_id':'STAFF_FIXTURE','status':1},{'user_id':'STUDENT_FIXTURE','status':0}])
    lab.check('password change invalidates pre-change mobile refresh', lab.refresh(student['refresh_token'])[0] != 200)
    lab.totp_secret = lab.mysql.action('enroll_totp', keyring=str(lab.keyring))['totp_secret']
    totp = lab.login('totp', password='Changed!8Raven#2026')
    lab.check('TOTP last-used counter does not invalidate its own successful login', lab.status(totp['access_token'])[0] == 200)
    lab.mysql.action('factor_revoke')
    lab.check('factor reset invalidates existing mobile refresh', lab.refresh(totp['refresh_token'])[0] != 200)
    final_staff = lab.login()
    lab.mysql.action('drop_guard')
    lab.check('missing lifecycle trigger fails closed', lab.status(final_staff['access_token'])[0] == 503)
    lab.settings['enabled'] = False; lab.write_settings()
    # PHP development server has opcache disabled; each request reloads this trusted private config.
    lab.check('dormant switch hides every operational endpoint', all(fetch(lab.hosted + p)[0] == 404 for p in ['/login','/consent','/mobile/session']))


def main():
    if sys.argv[1:] != ['--uat-only']:
        raise SystemExit('Usage: rehearsal.py --uat-only')
    before = legacy_digest()
    lab = HostedLab()
    failure = None
    def interrupt(*_): raise KeyboardInterrupt()
    signal.signal(signal.SIGTERM, interrupt)
    try:
        lab.start(); tests(lab)
    except (Exception, KeyboardInterrupt) as error:
        failure = type(error).__name__
        print('STOPPED: ' + failure + '; private diagnostics retained', flush=True)
    finally:
        lab.close()
    after = legacy_digest()
    lab.events += [{'test':'tracked runtime files unchanged during rehearsal (not a comparison with Git HEAD)','passed':before == after},
                   {'test':'all temporary services stopped','passed':all(p.poll() is not None for p in lab.processes)
                    and (lab.mysql.process is None or lab.mysql.process.poll() is not None)}]
    report = {'phase':'C', 'scope':'full hosted HTTP flow against private MySQL/Hydra/PostgreSQL fixtures',
        'failure':failure,'tests':lab.events,'legacy_before':before,'legacy_after':after,
        'refresh_ttl':'-1 (provider non-expiring setting; not a months-long elapsed-time test)',
        'limits':['No application database or production writes','No real account or SMTP delivery',
                  'HTTP loopback fixture; approved HTTPS hostname and real Flutter not tested',
                  'ID token claims inspected; Flutter SDK cryptographic verification remains separate']}
    path = lab.run_dir / 'phase-c-report.json'; path.write_text(json.dumps(report,indent=2)+'\n')
    passed = sum(e['passed'] for e in lab.events)
    print(f'Report: {path}\nResult: {passed}/{len(lab.events)} checks passed; services stopped')
    return 1 if failure or passed != len(lab.events) else 0


if __name__ == '__main__':
    raise SystemExit(main())
