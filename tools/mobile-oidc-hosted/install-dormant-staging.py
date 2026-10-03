#!/usr/bin/env python3
"""Install dormant routing/FPM only. No database, provider or public activation."""
import argparse
import hashlib
import os
from pathlib import Path
import shutil
import subprocess
import tempfile
import time

ROOT = Path('/var/www/oneid-uat')
SITE = Path('/etc/nginx/sites-available/oneid-uat')
POOL = Path('/etc/php/8.3/fpm/pool.d/oneid-mobile-uat.conf')
SNIPPET = Path('/etc/nginx/snippets/oneid-mobile-uat-dormant.conf')
BASE_HASH = 'c47c24c9eb344074f9d8f4435b667c71dc7b549cbede92d79c5d7bc96cd75120'
INCLUDE = '    include /etc/nginx/snippets/oneid-mobile-uat-dormant.conf;\n'
MARKER = '    # Main application routing.\n'
ROUTES = ['/login', '/consent', '/login.css', '/mobile/session', '/mobile/logout',
          '/token-hook', '/.well-known/openid-configuration', '/.well-known/jwks.json',
          '/oauth2/auth', '/oauth2/token', '/oauth2/revoke', '/userinfo']


def run(args):
    subprocess.run(args, check=True)


def render(source, pool):
    if hashlib.sha256(source.encode()).hexdigest() != BASE_HASH:
        raise RuntimeError('Nginx staging changed since review; stop and review again.')
    if source.count(MARKER) != 1:
        raise RuntimeError('Cannot identify reviewed HTTPS insertion point.')
    # Return directly from Nginx: provider absent must not produce a 502, and
    # accidentally enabling PHP config cannot expose this dormant installation.
    snippet = '# Dormant staging only. Every mobile endpoint deliberately returns 404.\n'
    snippet += ''.join('location = ' + route + ' { return 404; }\n' for route in ROUTES)
    return source.replace(MARKER, INCLUDE + MARKER), pool, snippet


def atomic_write(path, data, mode=0o644):
    fd, temporary = tempfile.mkstemp(prefix='.oneid-mobile-', dir=path.parent)
    try:
        with os.fdopen(fd, 'wb') as stream:
            stream.write(data)
        os.chmod(temporary, mode)
        os.replace(temporary, path)
    finally:
        if os.path.exists(temporary):
            os.unlink(temporary)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--apply', action='store_true', help='requires root; default is read-only preflight')
    args = parser.parse_args()
    if Path(__file__).resolve().parents[2] != ROOT:
        raise RuntimeError('Only /var/www/oneid-uat is supported.')
    if Path('/etc/nginx/sites-enabled/oneid-uat').resolve() != SITE:
        raise RuntimeError('Unexpected enabled staging site.')
    for path in (SITE, POOL, SNIPPET):
        if path.is_symlink():
            raise RuntimeError('Unexpected symlink: ' + str(path))
    if POOL.exists() or SNIPPET.exists():
        raise RuntimeError('Mobile installation already exists; will not overwrite it.')
    source = SITE.read_text()
    rendered = render(source, (ROOT / 'deployment/mobile-oidc/php-fpm.conf.example').read_text())
    # Inspect dormant flag without loading legacy bootstrap or opening any DB.
    run(['php', '-r', '$c=require $argv[1]; exit(($c["enabled"]??null)===false && ($c["environment"]??null)==="staging" ? 0 : 1);',
         str(ROOT / '.private/mobile-oidc-hosted.php')])
    for binary in ('nginx', 'php-fpm8.3', 'systemctl', 'curl'):
        if not shutil.which(binary):
            raise RuntimeError('Missing prerequisite: ' + binary)
    if not SNIPPET.parent.is_dir():
        raise RuntimeError('Nginx snippets directory missing.')
    print('Preflight OK: reviewed staging site; feature OFF; no existing mobile pool/routes.', flush=True)
    if not args.apply:
        print('Read-only check complete. No files or services changed.')
        return
    if os.geteuid() != 0:
        raise RuntimeError('Run --apply with sudo in your administrator terminal.')
    # Refuse to reload a service already in a failed/invalid state.
    run(['systemctl', 'is-active', '--quiet', 'nginx'])
    run(['systemctl', 'is-active', '--quiet', 'php8.3-fpm'])
    run(['nginx', '-t'])
    run(['php-fpm8.3', '-t'])
    backup_root = Path('/var/backups/oneid-mobile-uat')
    if backup_root.is_symlink():
        raise RuntimeError('Unexpected backup directory symlink.')
    backup_root.mkdir(mode=0o700, exist_ok=True)
    if backup_root.stat().st_uid != 0 or backup_root.stat().st_mode & 0o022:
        raise RuntimeError('Backup directory must be root-owned and not group/world writable.')
    backup = Path(tempfile.mkdtemp(prefix=time.strftime('%Y%m%d-%H%M%S-'), dir=backup_root))
    shutil.copy2(SITE, backup / 'oneid-uat.conf')
    print('Backup: ' + str(backup), flush=True)
    attempted_reload = False
    try:
        for path, content in zip((SITE, POOL, SNIPPET), rendered):
            atomic_write(path, content.encode())
        run(['php-fpm8.3', '-t'])
        run(['nginx', '-t'])
        attempted_reload = True
        run(['systemctl', 'reload', 'php8.3-fpm'])
        run(['systemctl', 'reload', 'nginx'])
        run(['systemctl', 'is-active', '--quiet', 'php8.3-fpm'])
        run(['systemctl', 'is-active', '--quiet', 'nginx'])
        for route in ROUTES:
            result = subprocess.check_output([
                'curl', '--silent', '--show-error', '--noproxy', '*', '--max-time', '10',
                '--resolve', 'oneid-uat.upnm.edu.my:443:127.0.0.1',
                '--output', '/dev/null', '--write-out', '%{http_code}',
                'https://oneid-uat.upnm.edu.my' + route], text=True)
            if result != '404':
                raise RuntimeError('Dormant route did not return 404: ' + route)
        (backup / 'SUCCESS.txt').write_text('Dormant staging Nginx routes and FPM pool installed. No DB/provider activation.\n')
    except BaseException:
        print('Installation failed; restoring previous configuration.', flush=True)
        atomic_write(SITE, (backup / 'oneid-uat.conf').read_bytes(), (backup / 'oneid-uat.conf').stat().st_mode & 0o777)
        POOL.unlink(missing_ok=True)
        SNIPPET.unlink(missing_ok=True)
        run(['php-fpm8.3', '-t'])
        run(['nginx', '-t'])
        if attempted_reload:
            run(['systemctl', 'reload', 'php8.3-fpm'])
            run(['systemctl', 'reload', 'nginx'])
        raise
    print('SUCCESS: dormant staging installed; all 12 mobile routes return 404. No database changes.')


if __name__ == '__main__':
    try:
        main()
    except Exception as error:
        print('STOP: ' + str(error), flush=True)
        raise SystemExit(1)
