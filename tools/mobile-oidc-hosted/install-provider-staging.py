#!/usr/bin/env python3
"""Fresh, private staging provider installation; public routes remain dormant."""
import argparse
import hashlib
import json
import os
from pathlib import Path
import pwd
import secrets
import shutil
import socket
import subprocess
import time
import urllib.request

ROOT = Path('/var/www/oneid-uat')
ART = ROOT / '.private/mobile-oidc-poc'
OPT = Path('/opt/oneid-mobile-uat')
ETC = Path('/etc/oneid-mobile-uat')
DATA = Path('/var/lib/oneid-mobile-pg')
PGUSER = 'oneid-mobile-pg'
USER = 'oneid-mobile-uat'
PGSERVICE = 'oneid-mobile-postgres-uat'
SERVICE = 'oneid-mobile-hydra-uat'


def config(password, hook, system):
    return {
        'dsn': f'postgres://oneid_mobile_provider:{password}@127.0.0.1:24146/oneid_mobile_provider?sslmode=disable',
        'serve': {'public': {'host': '127.0.0.1', 'port': 24144},
                  'admin': {'host': '127.0.0.1', 'port': 24145},
                  'tls': {'allow_termination_from': ['127.0.0.1/32']}},
        'urls': {'self': {'issuer': 'https://oneid-uat.upnm.edu.my/', 'public': 'https://oneid-uat.upnm.edu.my/'},
                 'login': 'https://oneid-uat.upnm.edu.my/login', 'consent': 'https://oneid-uat.upnm.edu.my/consent'},
        'secrets': {'system': [system]}, 'log': {'level': 'error', 'leak_sensitive_values': False},
        'ttl': {'access_token': '5m', 'refresh_token': '-1', 'auth_code': '1m'},
        'oauth2': {'pkce': {'enforced': True, 'enforced_for_public_clients': True},
                  'grant': {'refresh_token': {'rotation_grace_period': '5s', 'rotation_grace_reuse_count': 2}},
                  'token_hook': {'url': 'https://oneid-uat.upnm.edu.my/token-hook',
                                 'auth': {'type': 'api_key', 'config': {'in': 'header', 'name': 'X-OneID-Hook-Key', 'value': hook}}}},
        'oidc': {'dynamic_client_registration': {'enabled': False}},
    }


def unit(user, command, writable, after='network.target', extra=''):
    return f'''[Unit]
Description=OneID private mobile staging ({user})
After={after}
{extra}
[Service]
Type=simple
User={user}
Group={user}
ExecStart={command}
Restart=on-failure
RestartSec=5
TimeoutStopSec=90
KillSignal=SIGINT
NoNewPrivileges=true
PrivateTmp=true
ProtectSystem=strict
ProtectHome=true
ReadWritePaths={writable}
UMask=0077
[Install]
WantedBy=multi-user.target
'''


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--apply', action='store_true')
    parser.add_argument('--resume-before-initdb', action='store_true', help='root only; resume reviewed failure before any cluster/config exists')
    args = parser.parse_args()
    if Path(__file__).resolve().parents[2] != ROOT:
        raise RuntimeError('Unexpected workspace')
    lock = json.loads((ROOT / 'tools/mobile-oidc-poc/provider-lock.json').read_text())
    pgdeb = ART / ('postgresql-16_' + lock['postgres_package'].split('=')[1] + '_amd64.deb')
    for path, digest in [(ART / 'bin/hydra', lock['hydra_binary_sha256']), (pgdeb, lock['postgres_deb_sha256'])]:
        if hashlib.sha256(path.read_bytes()).hexdigest() != digest:
            raise RuntimeError('Pinned artifact mismatch: ' + path.name)
    dormant = Path('/etc/nginx/snippets/oneid-mobile-uat-dormant.conf').read_text()
    if dormant.count('return 404;') != 12 or 'proxy_pass' in dormant or 'fastcgi_pass' in dormant:
        raise RuntimeError('Dormant routing guard is not installed')
    subprocess.run(['php', '-r', '$c=require $argv[1];exit(($c["enabled"]??null)===false?0:1);', str(ROOT / '.private/mobile-oidc-hosted.php')], check=True)
    targets = [OPT, ETC, DATA, Path('/var/lib/oneid-mobile-uat')]
    targets += [Path('/etc/systemd/system/' + n + '.service') for n in [PGSERVICE, SERVICE]]
    if args.resume_before_initdb:
        if os.geteuid() != 0:
            raise RuntimeError('Resume preflight requires sudo to inspect protected directories')
        for path in targets[:4]:
            if path.is_symlink() or not path.is_dir():
                raise RuntimeError('Unexpected partial installation directory: ' + str(path))
        for path in [DATA / 'data', ETC / 'hydra.json', ETC / 'INSTALL_SUCCESS', *targets[4:]]:
            if path.exists() or path.is_symlink():
                raise RuntimeError('Resume refused: cluster/config/service already exists: ' + str(path))
        if any(DATA.iterdir()) or any(Path('/var/lib/oneid-mobile-uat').iterdir()):
            raise RuntimeError('Resume requires empty service data directories; nothing will be deleted')
        if OPT.stat().st_uid != 0 or ETC.stat().st_uid != 0:
            raise RuntimeError('Installation directories must remain root-owned')
    for path in targets:
        if not args.resume_before_initdb and (path.exists() or path.is_symlink()):
            raise RuntimeError('Fresh install only; existing target requires review: ' + str(path))
    for name in [PGUSER, USER]:
        try:
            pwd.getpwnam(name)
        except KeyError:
            if args.resume_before_initdb:
                raise RuntimeError('Missing expected service account: ' + name)
        else:
            if not args.resume_before_initdb:
                raise RuntimeError('Existing service account requires review: ' + name)
            account = pwd.getpwnam(name)
            if account.pw_shell != '/usr/sbin/nologin' or account.pw_dir != '/var/lib/' + name or account.pw_uid == 0:
                raise RuntimeError('Unexpected service account properties')
    for port in [24144, 24145, 24146]:
        with socket.socket() as check:
            check.bind(('127.0.0.1', port))
    print('Preflight OK: pinned artifacts, fresh or verified pre-initdb targets, loopback ports free, public routes OFF.', flush=True)
    if not args.apply:
        return
    if os.geteuid() != 0:
        raise RuntimeError('--apply requires sudo')
    os.umask(0o077)
    ETC.mkdir(mode=0o750, exist_ok=args.resume_before_initdb)
    os.chmod(ETC, 0o750)  # mkdir modes are filtered by restrictive umask.
    log = open(ETC / 'installation.log', 'ab', buffering=0)

    def run(command, **kwargs):
        subprocess.run([str(x) for x in command], stdout=log, stderr=log, check=True, **kwargs)

    try:
        for name in [PGUSER, USER]:
            if not args.resume_before_initdb:
                run(['useradd', '--system', '--user-group', '--home-dir', '/var/lib/' + name, '--no-create-home', '--shell', '/usr/sbin/nologin', name])
        pguid = pwd.getpwnam(PGUSER)
        uid = pwd.getpwnam(USER)
        os.chown(ETC, 0, uid.pw_gid)
        OPT.mkdir(mode=0o755, exist_ok=args.resume_before_initdb)
        os.chmod(OPT, 0o755)
        shutil.copyfile(ART / 'bin/hydra', OPT / 'hydra')
        os.chmod(OPT / 'hydra', 0o755)
        run(['dpkg-deb', '-x', pgdeb, OPT / 'postgres'])
        # Extracted package is root-owned, never execute the writable fixture tree.
        for directory, dirs, files in os.walk(OPT / 'postgres'):
            os.chmod(directory, 0o755)
        pg = OPT / 'postgres/usr/lib/postgresql/16/bin'
        for path, account in [(DATA, pguid), (Path('/var/lib/oneid-mobile-uat'), uid)]:
            path.mkdir(mode=0o700, exist_ok=args.resume_before_initdb)
            os.chown(path, account.pw_uid, account.pw_gid)
        run(['runuser', '-u', PGUSER, '--', pg / 'initdb', '-D', DATA / 'data', '-U', PGUSER,
             '--auth-local=peer', '--auth-host=reject', '--encoding=UTF8', '--no-locale'])
        password = secrets.token_hex(32)
        sql = f"CREATE ROLE oneid_mobile_provider LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE PASSWORD '{password}';\nCREATE DATABASE oneid_mobile_provider OWNER oneid_mobile_provider;\n"
        run(['runuser', '-u', PGUSER, '--', pg / 'postgres', '--single', '-D', DATA / 'data', 'postgres'], input=sql.encode())
        with (DATA / 'data/postgresql.conf').open('a') as stream:
            stream.write("\nlisten_addresses='127.0.0.1'\nport=24146\nunix_socket_directories='/var/lib/oneid-mobile-pg'\nmax_connections=30\nshared_buffers=64MB\npassword_encryption='scram-sha-256'\n")
        (DATA / 'data/pg_hba.conf').write_text('local all all peer\nhost oneid_mobile_provider oneid_mobile_provider 127.0.0.1/32 scram-sha-256\n')
        os.chown(DATA / 'data/pg_hba.conf', pguid.pw_uid, pguid.pw_gid)
        cfg = ETC / 'hydra.json'
        cfg.write_text(json.dumps(config(password, secrets.token_hex(32), secrets.token_hex(32)), indent=2) + '\n')
        os.chmod(cfg, 0o640)
        os.chown(cfg, 0, uid.pw_gid)
        pgunit = unit(PGUSER, f'{pg}/postgres -D {DATA}/data', str(DATA))
        hydraunit = unit(USER, f'{OPT}/hydra serve all --sqa-opt-out -c {cfg}', '/var/lib/oneid-mobile-uat',
                         PGSERVICE + '.service', 'Requires=' + PGSERVICE + '.service')
        for name, content in [(PGSERVICE, pgunit), (SERVICE, hydraunit)]:
            path = Path('/etc/systemd/system/' + name + '.service')
            path.write_text(content)
            os.chmod(path, 0o644)
        run(['systemd-analyze', 'verify', '/etc/systemd/system/' + PGSERVICE + '.service', '/etc/systemd/system/' + SERVICE + '.service'])
        run(['systemctl', 'daemon-reload'])
        run(['systemctl', 'start', PGSERVICE])
        for attempt in range(40):
            try:
                with socket.create_connection(('127.0.0.1', 24146), timeout=1):
                    break
            except OSError:
                time.sleep(.5)
        else:
            raise RuntimeError('Dedicated PostgreSQL did not start')
        run(['runuser', '-u', USER, '--', OPT / 'hydra', 'migrate', 'sql', 'up', '-e', '-y', '-c', cfg])
        run(['systemctl', 'start', SERVICE])
        opener = urllib.request.build_opener(urllib.request.ProxyHandler({}))
        for attempt in range(60):
            try:
                with opener.open('http://127.0.0.1:24145/health/ready', timeout=2) as response:
                    if response.status == 200:
                        break
            except (OSError, urllib.error.URLError):
                time.sleep(.5)
        else:
            raise RuntimeError('Hydra readiness failed')
        run(['systemctl', 'enable', PGSERVICE, SERVICE])
        (ETC / 'INSTALL_SUCCESS').write_text('Private provider ready. Public routes OFF. OneID migration is a separate command.\n')
        print('SUCCESS: private Hydra and dedicated PostgreSQL ready on loopback. Public endpoints remain OFF. No OneID database changes.')
    except BaseException:
        subprocess.run(['systemctl', 'stop', SERVICE, PGSERVICE], stdout=log, stderr=log)
        print('STOP: installation incomplete; dedicated services stopped. Data retained. Administrator log: /etc/oneid-mobile-uat/installation.log', flush=True)
        raise
    finally:
        log.close()


if __name__ == '__main__':
    try:
        main()
    except Exception as error:
        # Subprocess commands never contain raw passwords; do not print config/log contents.
        print('FAILED: ' + type(error).__name__ + ': ' + str(error))
        raise SystemExit(1)
