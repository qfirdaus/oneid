#!/usr/bin/env python3
"""Prepare PHP 8.4 only; never edit Nginx, cron, or PHP 8.3 configuration."""
import argparse, hashlib, json, os, re, subprocess
from pathlib import Path
VERSION = '8.4.26-1+ubuntu24.04.1+deb.sury.org+1'
PACKAGES = ['cli','fpm','common','intl','curl','mysql','mbstring','xml','gd','zip','odbc','sybase','opcache','readline']
def run(args):
    return subprocess.check_output(args, text=True, stderr=subprocess.STDOUT)
def snapshot():
    roots = [Path('/etc/nginx'), Path('/etc/php/8.3')]
    return {str(p): hashlib.sha256(p.read_bytes()).hexdigest() for root in roots for p in root.rglob('*') if p.is_file() and (p.suffix in ('.conf', '.ini') or p.parent.name in ('sites-enabled','sites-available'))}
def main():
    parser=argparse.ArgumentParser();parser.add_argument('--apply',action='store_true');args=parser.parse_args()
    if Path('/usr/bin/php').resolve()!=Path('/usr/bin/php8.3'):raise RuntimeError('Default PHP is not 8.3; review before proceeding.')
    # A new FPM service must not already be a configured traffic destination.
    nginx='\n'.join(p.read_text(errors='replace') for p in Path('/etc/nginx').rglob('*') if p.is_file() and (p.suffix == '.conf' or p.parent.name in ('sites-enabled','sites-available')))
    if re.search(r'fastcgi_pass[^;]*(?:8[.]4|84)',nginx):raise RuntimeError('Existing PHP 8.4 routing requires manual review.')
    packages=['php8.4-'+p+'='+VERSION for p in PACKAGES]
    plan=run(['apt-get','-s','--no-install-recommends','install']+packages)
    for line in plan.splitlines():
        if line.startswith('Remv '):raise RuntimeError('Package removal proposed; aborted.')
        if line.startswith('Inst ') and not line.split()[1].startswith('php8.4-'):
            raise RuntimeError('Non-PHP-8.4 package change proposed; aborted: '+line.split()[1])
    print(plan)
    print('CHECK: isolated package plan; default PHP 8.3; no PHP 8.4 routing found.')
    if not args.apply:return
    if os.geteuid()!=0:raise RuntimeError('Run --apply using sudo.')
    before=snapshot()
    backup=Path('/var/backups/oneid-php84-phase3');backup.mkdir(mode=0o700,exist_ok=True)
    record=backup/'before.json'
    if not record.exists():
        record.write_text(json.dumps({'hashes':before,'php_alternatives':run(['update-alternatives','--query','php'])},indent=2));record.chmod(0o600)
    # Pin before installing: auto mode might otherwise switch the server CLI to 8.4.
    run(['update-alternatives','--set','php','/usr/bin/php8.3'])
    try:
        print(run(['apt-get','-y','--no-install-recommends','install']+packages))
    finally:
        run(['update-alternatives','--set','php','/usr/bin/php8.3'])
    if run(['/usr/bin/php8.4','-r','echo PHP_VERSION;']).strip()!='8.4.26':raise RuntimeError('Unexpected installed PHP version.')
    source=Path('/etc/php/8.3/fpm/pool.d/oneid-mobile-uat.conf').read_text()
    mobile=source.replace('[oneid-mobile-uat]','[oneid-mobile-uat84]').replace('/run/php/oneid-mobile-uat.sock','/run/php/oneid-mobile-uat84.sock')
    web='''[oneid-web-uat84]
user = www-data
group = www-data
listen = /run/php/oneid-web-uat84.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660
pm = ondemand
pm.max_children = 4
pm.process_idle_timeout = 10s
pm.max_requests = 500
clear_env = yes
security.limit_extensions = .php
php_admin_flag[display_errors] = off
php_admin_flag[log_errors] = on
php_admin_value[error_reporting] = 32767
'''
    mobile+='\nphp_admin_value[error_reporting] = 32767\n'
    targets={Path('/etc/php/8.4/fpm/pool.d/oneid-web-uat84.conf'):web,Path('/etc/php/8.4/fpm/pool.d/oneid-mobile-uat84.conf'):mobile}
    for p,content in targets.items():
        if p.exists() and p.read_text()!=content:raise RuntimeError('Existing pool differs; refusing overwrite: '+str(p))
    created=[]
    try:
        for p,content in targets.items():
            if not p.exists():p.write_text(content);p.chmod(0o644);created.append(p)
        print(run(['/usr/sbin/php-fpm8.4','-t']))
    except Exception:
        for p in created:p.unlink()
        raise
    run(['systemctl','enable','--now','php8.4-fpm']);run(['systemctl','reload','php8.4-fpm'])
    run(['systemctl','is-active','--quiet','php8.3-fpm'])
    if before!=snapshot():raise RuntimeError('Protected configuration changed; review snapshot. No routing change performed by this script.')
    if Path('/usr/bin/php').resolve()!=Path('/usr/bin/php8.3'):raise RuntimeError('Default CLI drift detected.')
    print('PASS: PHP 8.4.26 prepared; PHP 8.3 default and protected configs unchanged. No traffic or cron switch.')
    print('Next: verify extensions, pool settings and isolated PHP 8.4 tests before routing any request.')
if __name__=='__main__':
    try:main()
    except Exception as exc:raise SystemExit('STOP: '+str(exc))
