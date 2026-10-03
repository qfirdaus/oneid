#!/usr/bin/env python3
"""Raise only the OneID UAT PHP 8.4 web worker cap from 4 to 8."""
import argparse,datetime,hashlib,json,os,re,subprocess,time
from pathlib import Path
from php84_fpm_probe import probe
TARGET=Path('/etc/php/8.4/fpm/pool.d/oneid-web-uat84.conf')
def protected():
 return {str(p):hashlib.sha256(p.read_bytes()).hexdigest() for root in ['/etc/php/8.3','/etc/php/8.4','/etc/nginx'] for p in Path(root).rglob('*') if p.is_file() and p.resolve()!=TARGET and (p.suffix in ['.conf','.ini'] or p.parent.name in ['sites-enabled','sites-available'])}
def reload():
 subprocess.run(['/usr/sbin/php-fpm8.4','-t'],check=True)
 subprocess.run(['systemctl','reload','php8.4-fpm'],check=True)
def main():
 parser=argparse.ArgumentParser();parser.add_argument('--apply',action='store_true');a=parser.parse_args()
 original=TARGET.read_bytes();text=original.decode()
 if len(re.findall(r'^pm.max_children\s*=\s*4\s*$',text,re.M))!=1 or '[oneid-web-uat84]' not in text:raise RuntimeError('Unexpected pool configuration; review required')
 updated=re.sub(r'^pm.max_children\s*=\s*4\s*$', 'pm.max_children = 8',text,flags=re.M).encode()
 available=int(re.search(r'^MemAvailable:\s+(\d+)',Path('/proc/meminfo').read_text(),re.M)[1])
 if available<4*1024*1024:raise RuntimeError('Less than 4 GiB available; manual capacity review required')
 if probe('/run/php/oneid-web-uat84.sock')['version']!='8.4.26':raise RuntimeError('Unexpected FPM version')
 print('CHECK PASS: web pool 4 -> 8 workers; available RAM %.1f GiB. No changes to mobile/8.3/Nginx/default CLI.'%(available/1024/1024))
 if not a.apply:return
 if os.geteuid()!=0:raise RuntimeError('sudo required')
 before=protected();backup=Path('/var/backups/oneid-php84-web-capacity-'+datetime.datetime.now().strftime('%Y%m%d-%H%M%S'));backup.mkdir(mode=0o700)
 (backup/TARGET.name).write_bytes(original)
 (backup/'protected.json').write_text(json.dumps(before,indent=2))
 try:
  TARGET.write_bytes(updated);reload();time.sleep(1)
  if probe('/run/php/oneid-web-uat84.sock')['version']!='8.4.26':raise RuntimeError('Worker verification failed')
  if protected()!=before:raise RuntimeError('Protected configuration changed')
 except Exception:
  TARGET.write_bytes(original);reload();print('Automatic rollback completed.');raise
 print('APPLIED: OneID UAT web max_children=8. This is a capacity adjustment, not a load-test certification.')
 print('Backup: '+str(backup))
if __name__=='__main__':
 try:main()
 except Exception as e:raise SystemExit('STOP: '+str(e))
