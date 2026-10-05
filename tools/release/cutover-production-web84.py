#!/usr/bin/env python3
import datetime, hashlib, json, os, shutil, subprocess
from pathlib import Path
CONFIG=Path('/etc/nginx/sites-enabled/oneid'); OLD='fastcgi_pass unix:/run/php/php8.3-fpm-oneid.sock;'; NEW='fastcgi_pass unix:/run/php/oneid-web-prod84.sock;'
def run(c): return subprocess.check_output(c,text=True,stderr=subprocess.STDOUT)
def main():
    if os.geteuid()!=0: raise RuntimeError('run with sudo')
    if not CONFIG.is_file(): raise RuntimeError('production Nginx config missing')
    text=CONFIG.read_text()
    if text.count(OLD)!=1 or NEW in text: raise RuntimeError('unexpected Nginx baseline; refusing blind replacement')
    if not Path('/run/php/oneid-web-prod84.sock').exists(): raise RuntimeError('PHP 8.4 web socket missing')
    if run(['readlink','-f','/usr/bin/php']).strip()!='/usr/bin/php8.3': raise RuntimeError('default CLI drift')
    stamp=datetime.datetime.now().strftime('%Y%m%d-%H%M%S'); backup=Path('/var/backups/oneid-nginx-cutover-'+stamp);backup.mkdir(mode=0o700)
    shutil.copy2(CONFIG,backup/'oneid.before.conf');(backup/'baseline.json').write_text(json.dumps({'old_socket':OLD,'new_socket':NEW,'config_sha256':hashlib.sha256(text.encode()).hexdigest()},indent=2))
    temp=CONFIG.with_name('oneid.cutover.tmp');temp.write_text(text.replace(OLD,NEW));shutil.copymode(CONFIG,temp)
    try:
        os.replace(temp,CONFIG)
        run(['nginx','-t']); run(['systemctl','reload','nginx'])
    except Exception:
        if temp.exists(): temp.unlink()
        # Restore known-good baseline only if the replacement was made by this invocation.
        shutil.copy2(backup/'oneid.before.conf',CONFIG); run(['nginx','-t']); raise
    result={'status':'WEB_CUTOVER_PHP84','backup':str(backup),'old_socket':OLD,'new_socket':NEW,'mobile_enabled':False,'cron_changed':False,'default_php':'8.3'}
    (backup/'result.json').write_text(json.dumps(result,indent=2));print(json.dumps(result,indent=2));print(run(['systemctl','is-active','nginx']).strip())
if __name__=='__main__':
    try: main()
    except Exception as e: raise SystemExit('STOP: '+str(e))
