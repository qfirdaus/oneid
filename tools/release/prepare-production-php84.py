#!/usr/bin/env python3
"""Production phase 2 only. No application deployment, DB migration or Nginx cutover."""
import argparse, datetime, hashlib, json, os, re, shutil, socket, subprocess
from pathlib import Path
VERSION='8.4.26-1+ubuntu24.04.1+deb.sury.org+1'
NAMES=['php8.4-'+x for x in ['cli','fpm','common','intl','curl','mysql','mbstring','xml','gd','zip','odbc','sybase','opcache','readline']]
def run(args, **kw):
    return subprocess.check_output(args,text=True,stderr=subprocess.STDOUT,**kw)
def hashes():
    out={}
    for root in ['/etc/nginx','/etc/php/8.3']:
        for p in Path(root).rglob('*'):
            if p.is_file():
                try: out[str(p)]=hashlib.sha256(p.read_bytes()).hexdigest()
                except PermissionError: out[str(p)]='UNREADABLE:'+str(p.stat().st_size)+':'+str(p.stat().st_mtime_ns)
    return out
def main():
    a=argparse.ArgumentParser();a.add_argument('--apply',action='store_true');args=a.parse_args()
    if socket.gethostname()!='APPSSSOPRODv1' or not Path('/var/www/oneid/public/index.php').exists():raise RuntimeError('Wrong production host/root')
    for name in ['php','phar','phar.phar']:
        if Path('/usr/bin/'+name).resolve()!=Path('/usr/bin/'+name+'8.3').resolve():raise RuntimeError('Unexpected default '+name)

    nginx_parts=[]
    for p in Path('/etc/nginx').rglob('*'):
        if p.is_file():
            try: nginx_parts.append(p.read_text(errors='replace'))
            except PermissionError: continue
    nginx='\n'.join(nginx_parts)
    if re.search(r'fastcgi_pass[^;]*(?:8\.4|prod84)',nginx):raise RuntimeError('Existing 8.4 routing: stop for review')
    if Path('/usr/bin/php8.4').exists():raise RuntimeError('8.4 CLI already exists; inspect prior installation before retry')
    packages=[n+'='+VERSION for n in NAMES]
    plan=run(['apt-get','-s','--no-install-recommends','install']+packages)
    for line in plan.splitlines():
        if line.startswith('Remv ') or (line.startswith('Inst ') and line.split()[1] not in NAMES):raise RuntimeError('Unexpected package change: '+line)
    print(plan,flush=True)
    print('CHECK PASS: exact PHP 8.4.26 package plan; public routing and defaults still 8.3.',flush=True)
    if not args.apply:return
    if os.geteuid()!=0:raise RuntimeError('Run --apply using sudo locally')
    before=hashes();umask=os.umask(0o077)
    backup=Path('/var/backups/oneid-prod-php84-phase2-'+datetime.datetime.now().strftime('%Y%m%d-%H%M%S'));backup.mkdir(mode=0o700)
    for root in ['php','nginx'] :shutil.copytree('/etc/'+root,backup/root,symlinks=True)
    records={'hashes':before,'packages':run(['dpkg-query','-W']),
             'alternatives':{n:run(['update-alternatives','--query',n]) for n in ['php','phar','phar.phar']}}
    for user in ['iqs','root']:
        r=subprocess.run(['crontab','-u',user,'-l'],capture_output=True,text=True)
        records['crontab_'+user]={'exit':r.returncode,'content':r.stdout,'error':r.stderr}
    (backup/'before.json').write_text(json.dumps(records,indent=2));print('Backup: '+str(backup),flush=True)
    # Pin all alternatives before apt so recurring jobs never transiently select 8.4.
    for n in ['php','phar','phar.phar']:run(['update-alternatives','--set',n,'/usr/bin/'+n+'8.3'])
    env={**os.environ,'DEBIAN_FRONTEND':'noninteractive','NEEDRESTART_MODE':'l'}
    try:
        subprocess.run(['apt-get','-y','--no-install-recommends','install']+packages,env=env,check=True)
    finally:
        for n in ['php','phar','phar.phar']:run(['update-alternatives','--set',n,'/usr/bin/'+n+'8.3'])
    for n in NAMES:
        if run(['dpkg-query','-W','-f=${Version}',n]).strip()!=VERSION:raise RuntimeError('Version mismatch '+n)
    if run(['php8.4','-r','echo PHP_VERSION;']).strip()!='8.4.26':raise RuntimeError('Runtime mismatch')
    # Preserve existing INI behavior, but use the new package extension conf.d files.
    for sapi in ['cli','fpm']:
        shutil.copy2('/etc/php/8.4/'+sapi+'/php.ini',backup/('php84-'+sapi+'-package.ini'))
        shutil.copy2('/etc/php/8.3/'+sapi+'/php.ini','/etc/php/8.4/'+sapi+'/php.ini')
    web=Path('/etc/php/8.3/fpm/pool.d/oneid.conf').read_text()
    if web.count('[oneid]')!=1 or web.count('/run/php/php8.3-fpm-oneid.sock')!=1:raise RuntimeError('Unexpected production pool shape')
    web=web.replace('[oneid]','[oneid-web-prod84]').replace('/run/php/php8.3-fpm-oneid.sock','/run/php/oneid-web-prod84.sock')
    mobile='''[oneid-mobile-prod84]
user = iqs
group = www-data
listen = /run/php/oneid-mobile-prod84.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660
pm = ondemand
pm.max_children = 4
pm.process_idle_timeout = 10s
pm.max_requests = 500
clear_env = yes
security.limit_extensions = .php
env[ONEID_RUNTIME_FILE] = /var/www/oneid/.private/runtime.php
env[ONEID_MOBILE_CONFIG] = /var/www/oneid/.private/mobile-oidc-hosted.php
php_admin_flag[display_errors] = off
php_admin_flag[log_errors] = on
php_admin_value[post_max_size] = 64K
php_admin_value[upload_max_filesize] = 64K
php_admin_value[max_execution_time] = 30
request_terminate_timeout = 35s
'''
    for name,body in [('oneid-web-prod84',web),('oneid-mobile-prod84',mobile)]:
        p=Path('/etc/php/8.4/fpm/pool.d/'+name+'.conf')
        if p.exists():raise RuntimeError('Existing pool: '+str(p))
        p.write_text(body);p.chmod(0o644)
    print(run(['/usr/sbin/php-fpm8.4','-t']))
    run(['systemctl','enable','--now','php8.4-fpm']);run(['systemctl','reload','php8.4-fpm'])
    for service in ['nginx','php8.3-fpm','php8.4-fpm']:run(['systemctl','is-active','--quiet',service])
    extensions={}
    for version in ['8.3','8.4']:
        extensions[version]=json.loads(run(['php'+version,'-r','echo json_encode(get_loaded_extensions());']))
    missing=set(extensions['8.3'])-set(extensions['8.4'])
    if missing:raise RuntimeError('Missing extensions: '+','.join(sorted(missing)))
    if hashes()!=before:raise RuntimeError('Protected configuration drift; inspect backup')
    for n in ['php','phar','phar.phar']:
        if Path('/usr/bin/'+n).resolve()!=Path('/usr/bin/'+n+'8.3').resolve():raise RuntimeError('Default drift '+n)
    result={'status':'INSTALLED_PENDING_FASTCGI_PROBE','version':'8.4.26','default_php':'8.3',
            'routing_unchanged':True,'mobile_routes_added':False,'extensions':extensions,'backup':str(backup)}
    (backup/'result.json').write_text(json.dumps(result,indent=2))
    print(json.dumps(result,indent=2))
    print('NEXT: isolated FastCGI and INI probes. No code/DB deployment or traffic cutover performed.')
if __name__=='__main__':
    try:main()
    except Exception as e:raise SystemExit('STOP: '+str(e)+'; installation may be partial. Do not rerun blindly; inspect backup and package status.')
