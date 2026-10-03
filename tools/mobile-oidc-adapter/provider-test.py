#!/usr/bin/env python3
"""PHP identity adapter -> pinned real Hydra; synthetic accounts and loopback only."""
import base64
import hashlib
import json
import os
from pathlib import Path
import secrets
import subprocess
import sys
import urllib.parse as url

sys.dont_write_bytecode = True
ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / 'tools/mobile-oidc-poc'))
from run import Lab, request, opener, b64, legacy_digest


def scenario(lab, kind):
    browser = opener()
    verifier, state, nonce = [secrets.token_urlsafe(32) for _ in range(3)]
    target = lab.public + '/oauth2/auth?' + url.urlencode({
        'client_id': 'poc-android', 'redirect_uri': lab.callback, 'response_type': 'code',
        'scope': 'openid profile offline_access mobile:session', 'audience': 'oneid-mobile-session',
        'state': state, 'nonce': nonce, 'code_challenge_method': 'S256',
        'code_challenge': b64(hashlib.sha256(verifier.encode()).digest())})
    identity = None
    for _ in range(12):
        status, headers, body = request(target, client=browser)
        location = url.urljoin(target, headers.get('Location', ''))
        params = dict(url.parse_qsl(url.urlsplit(location).query))
        if location.startswith(lab.mock + '/login?'):
            result = subprocess.run(['php', str(ROOT / 'tools/mobile-oidc-adapter/accept-fixture.php')],
                input=json.dumps({'admin': lab.admin, 'issuer': lab.public, 'callback': lab.callback,
                                  'challenge': params['login_challenge'], 'scenario': kind}),
                text=True, capture_output=True, timeout=30, cwd=ROOT, env={'PATH': '/usr/bin:/bin'})
            lab.check(kind + ': PHP adapter completed without bootstrap', result.returncode == 0)
            accepted = json.loads(result.stdout)
            if kind.startswith('wrong_'):
                lab.check(kind + ': denied before provider acceptance', accepted.get('denied') is True)
                return
            identity, profile = accepted['identity'], accepted['profile']
            lab.check(kind + ': adapter session bound to real provider client', profile.get('sub') == identity['sub'])
            lab.sessions[identity['sid']] = {'active': True, 'client_id': 'poc-android'}
            target = accepted['redirect_to']
        elif location.startswith(lab.mock + '/consent?'):
            challenge = params['consent_challenge']
            code, _, consent = request(lab.admin + '/admin/oauth2/auth/requests/consent?' + url.urlencode({'consent_challenge': challenge}))
            lab.check(kind + ': provider receives adapter subject and context', code == 200 and consent['subject'] == identity['sub']
                      and consent['context']['mobile_sid'] == identity['sid'])
            code, _, accepted = request(lab.admin + '/admin/oauth2/auth/requests/consent/accept?' + url.urlencode({'consent_challenge': challenge}), 'PUT', {
                'grant_scope': consent['requested_scope'], 'grant_access_token_audience': consent['requested_access_token_audience'],
                'remember': False, 'session': {'access_token': {'poc_sid': identity['sid'], 'security_version': 1},
                                             'id_token': {'name': profile['name'], 'account_type': profile['account_type']}}})
            lab.check(kind + ': fixture consent accepted', code == 200)
            target = accepted['redirect_to']
        elif location.startswith(lab.callback + '?') and 'code' in params:
            lab.check(kind + ': callback state preserved', params['state'] == state)
            status, _, token = request(lab.public + '/oauth2/token', 'POST', {
                'grant_type': 'authorization_code', 'client_id': 'poc-android', 'code': params['code'],
                'redirect_uri': lab.callback, 'code_verifier': verifier}, {'Content-Type': 'application/x-www-form-urlencoded'})
            lab.check(kind + ': real PKCE token exchange succeeds', status == 200)
            claims = json.loads(base64.urlsafe_b64decode(token['id_token'].split('.')[1] + '==='))
            expected = ['pwd'] if kind == 'staff' else ['pwd', 'otp' if kind == 'totp' else 'email']
            lab.check(kind + ': ID token binds subject nonce and verified assurance', claims['sub'] == identity['sub']
                      and claims['nonce'] == nonce and claims['amr'] == expected)
            return
        elif location.startswith(lab.public + '/') and location != target:
            target = location
        else:
            raise AssertionError('Unexpected provider transition')
    raise AssertionError('Too many redirects')


def main():
    if sys.argv[1:] != ['--uat-only']:
        raise SystemExit('Usage: provider-test.py --uat-only')
    before = legacy_digest()
    lab = Lab('0s')
    failure = None
    try:
        lab.start()
        for kind in ['staff', 'student', 'totp', 'wrong_password', 'wrong_otp']:
            scenario(lab, kind)
    except (Exception, KeyboardInterrupt) as e:
        failure = type(e).__name__
        print('STOPPED: ' + failure)
    finally:
        lab.close()
    after = legacy_digest()
    lab.events += [{'test': 'tracked runtime unchanged during rehearsal', 'passed': before == after},
                   {'test': 'child services stopped', 'passed': all(p.poll() is not None for p in lab.processes)}]
    report = {'phase': 'B', 'scope': 'PHP adapter + real Hydra, synthetic sources, no OneID database or SMTP',
              'provider': 'v26.2.0', 'failure': failure, 'tests': lab.events,
              'legacy_before': before, 'legacy_after': after,
              'limits': ['PdoIdentitySource/PdoStateStore not exercised', 'Consent and token hook remain Phase A fixtures',
                         'No public hosted UI, Flutter or live account verification', 'JWT claims decoded, not signature verifier test']}
    path = lab.run_dir / 'phase-b-report.json'
    path.write_text(json.dumps(report, indent=2) + '\n')
    passed = sum(e['passed'] for e in lab.events)
    print(f'Report: {path}\nResult: {passed}/{len(lab.events)} passed; services stopped')
    return 1 if failure or passed != len(lab.events) else 0


if __name__ == '__main__':
    raise SystemExit(main())
