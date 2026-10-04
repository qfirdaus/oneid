#!/usr/bin/env python3
import json,os,subprocess,sys
from pathlib import Path
def run(c):
 r=subprocess.run(c,text=True,capture_output=True);return {'exit':r.returncode,'out':(r.stdout+r.stderr).strip()[-4000:]}
if os.geteuid()!=0:raise SystemExit('Run with sudo: sudo python3 -B probe-production-php84.py')
out={'scope':'isolated PHP 8.4 FPM/INI probe; no Nginx reload, no alternatives, no cron, no DB writes','checks':{}}
out['checks']['php84_version']=run(['/usr/bin/php8.4','-r','echo PHP_VERSION;'])
out['checks']['php84_modules']=run(['/usr/bin/php8.4','-r','echo json_encode(get_loaded_extensions());'])
out['checks']['php83_default']=run(['/usr/bin/php','-r','echo PHP_VERSION;'])
out['checks']['fpm_config']=run(['/usr/sbin/php-fpm8.4','-t'])
out['checks']['fpm_status']=run(['systemctl','is-active','php8.4-fpm'])
out['checks']['nginx_config']=run(['nginx','-t'])
out['checks']['sockets']={}
for name in ['oneid-web-prod84.sock','oneid-mobile-prod84.sock']:
 p=Path('/run/php')/name;st=p.stat() if p.exists() else None
 out['checks']['sockets'][name]={'exists':p.exists(),'mode':oct(st.st_mode & 0o777) if st else None,'uid':st.st_uid if st else None,'gid':st.st_gid if st else None}
for sapi in ['cli','fpm']:
 out['checks']['ini_'+sapi]=run(['/usr/bin/php8.4','-c','/etc/php/8.4/'+sapi+'/php.ini','-r','echo json_encode(ini_get_all(null,false));'])
for log in ['/var/log/php8.4-fpm.log','/var/log/php8.4-fpm.log']:
 if Path(log).exists():out['checks']['log_tail']=Path(log).read_text(errors='replace').splitlines()[-30:]
out['defaults_unchanged']=Path('/usr/bin/php').resolve()==Path('/usr/bin/php8.3') and Path('/usr/bin/phar').resolve().name in ('phar8.3','phar8.3.phar') and Path('/usr/bin/phar.phar').resolve().name in ('phar.phar8.3','phar.phar8.3.phar')
print(json.dumps(out,indent=2))
