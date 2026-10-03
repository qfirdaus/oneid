#!/usr/bin/env python3
"""Install/remove fixed-label redirect logging on staging authorization route only."""
import os, pathlib, subprocess, sys, tempfile
ROOT = pathlib.Path('/var/www/oneid-uat')
SNIPPET = pathlib.Path('/etc/nginx/snippets/oneid-mobile-uat-dormant.conf')
CONF = pathlib.Path('/etc/nginx/conf.d/oneid-mobile-redirect-diagnostics.conf')
LINE = '    access_log /var/log/nginx/oneid-mobile-redirect-diagnostics.log oneid_redirect_diag;\n'

def run(*args):
    subprocess.run(args, check=True)

def main():
    if os.geteuid() != 0 or sys.argv[1:] not in [['--apply'], ['--remove']]:
        sys.exit('Usage: sudo python3 tools/mobile-oidc-hosted/redirect-diagnostics.py --apply|--remove')
    if pathlib.Path(__file__).resolve().parents[2] != ROOT:
        sys.exit('STOP: staging path required')
    if SNIPPET.is_symlink() or CONF.is_symlink():
        sys.exit('STOP: unexpected symlink')
    original = SNIPPET.read_text()
    old_conf = CONF.read_bytes() if CONF.exists() else None
    marker = 'location = /oauth2/auth {\n'
    if original.count(marker) != 1:
        sys.exit('STOP: authorization route not unique')
    route = original.split(marker, 1)[1].split('}', 1)[0]
    if 'proxy_pass http://127.0.0.1:24144;' not in route:
        sys.exit('STOP: expected staging provider route missing')
    # Verify staging vhost owns the route; do not modify other virtual hosts.
    sites = list(pathlib.Path('/etc/nginx/sites-enabled').glob('*'))
    owners = [p.read_text() for p in sites if 'include /etc/nginx/snippets/oneid-mobile-uat-dormant.conf;' in p.read_text()]
    if len(owners) != 1 or 'server_name oneid-uat.upnm.edu.my' not in owners[0] or '/var/www/oneid-uat' not in owners[0]:
        sys.exit('STOP: staging vhost ownership not verified')
    updated = original.replace(LINE, '')
    apply = sys.argv[1] == '--apply'
    if apply:
        updated = updated.replace(marker, marker + LINE)
    backup_dir = pathlib.Path('/var/backups/oneid-mobile-uat')
    backup_dir.mkdir(mode=0o700, parents=True, exist_ok=True)
    backup = pathlib.Path(tempfile.mkdtemp(prefix='redirect-diag-', dir=backup_dir))
    (backup/'snippet.conf').write_text(original)
    if old_conf is not None:
        (backup/'diagnostics.conf').write_bytes(old_conf)
    try:
        if apply:
            CONF.write_bytes((ROOT/'deployment/mobile-oidc/diagnostics/redirect-log.conf').read_bytes())
            CONF.chmod(0o644)
        else:
            CONF.unlink(missing_ok=True)
        SNIPPET.write_text(updated)
        run('nginx', '-t')
        run('systemctl', 'reload', 'nginx')
    except Exception:
        SNIPPET.write_text(original)
        if old_conf is None:
            CONF.unlink(missing_ok=True)
        else:
            CONF.write_bytes(old_conf)
        run('nginx', '-t')
        run('systemctl', 'reload', 'nginx')
        sys.exit('STOP: change rolled back')
    print('SUCCESS: redirect diagnostics ' + ('enabled' if apply else 'removed') + '; backup: ' + str(backup))

if __name__ == '__main__':
    main()
