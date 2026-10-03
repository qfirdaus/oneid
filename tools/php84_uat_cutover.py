#!/usr/bin/env python3
"""Narrow OneID UAT FPM switch with backup, health checks and rollback."""
import argparse,datetime,hashlib,json,os,re,subprocess,time
from pathlib import Path
from php84_fpm_probe import probe
TARGETS=[(Path('/etc/nginx/sites-enabled/oneid-uat').resolve(),'php8.3-fpm.sock','oneid-web-uat84.sock',1),
         (Path('/etc/nginx/snippets/oneid-mobile-uat-dormant.conf'),'oneid-mobile-uat.sock','oneid-mobile-uat84.sock',7)]
HOST='oneid-uat.upnm.edu.my'
def digest(data):return hashlib.sha256(data).hexdigest()
def protected():
 excluded={t[0].resolve() for t in TARGETS}
 return {str(p):digest(p.read_bytes()) for root in ['/etc/nginx','/etc/php/8.3','/etc/php/8.4'] for p in Path(root).rglob('*') if p.is_file() and p.resolve() not in excluded and (p.suffix in ['.conf','.ini'] or p.parent.name in ['sites-enabled','sites-available'])}
def reload():
 subprocess.run(['/usr/sbin/nginx','-t'],check=True)
 subprocess.run(['systemctl','reload','nginx'],check=True)
def smoke():
 results=[]
 for path,expected in [('/',200),('/api.php',400),('/login.css',200),('/mobile/session',401),('/.well-known/openid-configuration',200),('/mobile-test/',200)]:
  r=subprocess.run(['curl','--silent','--show-error','--noproxy','*','--max-time','20','--resolve',HOST+':443:127.0.0.1','-o','/dev/null','-w','%{http_code}','https://'+HOST+path],capture_output=True,text=True,check=True)
  status=int(r.stdout);results.append({'path':path,'status':status})
  if status!=expected:raise RuntimeError('Health check failed: '+path+' '+str(status))
 return results
def main():
 parser=argparse.ArgumentParser();group=parser.add_mutually_exclusive_group();group.add_argument('--apply',action='store_true');group.add_argument('--rollback',type=Path);args=parser.parse_args()
 if args.rollback:
  if os.geteuid()!=0:raise RuntimeError('sudo required')
  backup=args.rollback.resolve()
  if backup.parent!=Path('/var/backups') or not re.fullmatch(r'oneid-php84-cutover-\d{8}-\d{6}',backup.name) or backup.stat().st_uid!=0:raise RuntimeError('Invalid backup directory')
  manifest=json.loads((backup/'manifest.json').read_text())
  originals={}
  for i,(path,_,_,_) in enumerate(TARGETS):
   row=manifest[str(path)];current=digest(path.read_bytes())
   if current not in [row['before'],row['after']]:raise RuntimeError('Configuration drift; rollback stopped')
   data=(backup/str(i)).read_bytes()
   if digest(data)!=row['before']:raise RuntimeError('Backup checksum mismatch')
   originals[path]=data
  before=protected();active={p:p.read_bytes() for p in originals}
  try:
   for p,data in originals.items():p.write_bytes(data)
   reload();time.sleep(1);smoke()
   if protected()!=before:raise RuntimeError('Unrelated config drift')
  except Exception:
   for p,data in active.items():p.write_bytes(data)
   reload();raise
  print('ROLLED BACK: OneID UAT web/mobile routing restored to 8.3; code/database not rolled back.');return
 changes={}
 for path,old,new,count in TARGETS:
  original=path.read_bytes();text=original.decode();oldroute='unix:/run/php/'+old+';';newroute='unix:/run/php/'+new+';'
  if text.count(oldroute)!=count or newroute in text:raise RuntimeError('Unexpected/already switched routing: '+str(path))
  changes[path]=(original,text.replace(oldroute,newroute).encode())
 for pool in ['php8.3-fpm','oneid-mobile-uat','oneid-web-uat84','oneid-mobile-uat84']:
  state=probe('/run/php/'+pool+'.sock')
  if pool.endswith('84') and state['version']!='8.4.26':raise RuntimeError('Wrong target version')
 if Path('/usr/bin/php').resolve()!=Path('/usr/bin/php8.3'):raise RuntimeError('Default CLI no longer 8.3')
 print('CHECK PASS: exact 1 web + 7 mobile handlers; old/new FPM reachable; default PHP remains 8.3.')
 print('Scope: OneID UAT only. Remaining limits: native-device 8.4 E2E, remote runtime/ODBC inventory, performance acceptance not fully closed.')
 if not args.apply:return
 if os.geteuid()!=0:raise RuntimeError('sudo required')
 before=protected();backup=Path('/var/backups/oneid-php84-cutover-'+datetime.datetime.now().strftime('%Y%m%d-%H%M%S'));backup.mkdir(mode=0o700)
 manifest={}
 for i,(path,(original,updated)) in enumerate(changes.items()):
  (backup/str(i)).write_bytes(original);manifest[str(path)]={'before':digest(original),'after':digest(updated)}
 (backup/'manifest.json').write_text(json.dumps(manifest,indent=2));(backup/'protected.json').write_text(json.dumps(before,indent=2))
 try:
  for p,(_,updated) in changes.items():p.write_bytes(updated)
  reload();time.sleep(1);results=smoke()
  if protected()!=before:raise RuntimeError('Unrelated config drift')
 except Exception:
  for p,(original,_) in changes.items():p.write_bytes(original)
  reload();print('Automatic rollback completed.');raise
 (backup/'smoke.json').write_text(json.dumps(results,indent=2))
 print(json.dumps(results,indent=2));print('SWITCHED: OneID UAT web/mobile to PHP 8.4.26; other config/default PHP unchanged.')
 print('Rollback: sudo python3 -B tools/php84_uat_cutover.py --rollback '+str(backup))
if __name__=='__main__':
 try:main()
 except Exception as e:raise SystemExit('STOP: '+str(e))
