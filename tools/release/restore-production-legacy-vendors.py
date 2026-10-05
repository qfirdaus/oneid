#!/usr/bin/env python3
import datetime, json, os, shutil, subprocess
from pathlib import Path
LIVE=Path('/var/www/oneid'); ARCHIVE=Path('/home/iqs/oneid-backups/oneid-app-pre-2.12.0-20260908-000757.tar.gz')
def main():
    if os.geteuid()!=0: raise RuntimeError('run with sudo')
    if LIVE.resolve()!=Path('/var/www/oneid') or not ARCHIVE.is_file(): raise RuntimeError('production root/archive guard')
    for rel in ['vendors/spyc-master','vendors/device-detector-master']:
        if (LIVE/rel).exists() and any((LIVE/rel).iterdir()): raise RuntimeError('current vendor directory not empty; review before restore: '+rel)
    stamp=datetime.datetime.now().strftime('%Y%m%d-%H%M%S');backup=Path('/var/backups/oneid-legacy-vendors-'+stamp);backup.mkdir(mode=0o700)
    for rel in ['vendors/spyc-master','vendors/device-detector-master']:
        p=LIVE/rel
        if p.exists(): shutil.copytree(p,backup/rel,dirs_exist_ok=True)
    subprocess.run(['tar','-xzf',str(ARCHIVE),'-C','/var/www','--no-same-owner','oneid/vendors/spyc-master','oneid/vendors/device-detector-master'],check=True)
    for rel in ['vendors/spyc-master','vendors/device-detector-master']:
        p=LIVE/rel
        if not p.is_dir() or not any(p.iterdir()): raise RuntimeError('restore incomplete: '+rel)
    subprocess.run(['chown','-R','iqs:www-data',str(LIVE/'vendors')],check=True)
    result={'status':'LEGACY_VENDORS_RESTORED','archive':str(ARCHIVE),'backup':str(backup),'paths':['vendors/spyc-master','vendors/device-detector-master'],'nginx_changed':False,'database_changed':False}
    (backup/'result.json').write_text(json.dumps(result,indent=2));print(json.dumps(result,indent=2))
if __name__=='__main__':
    try:main()
    except Exception as e: raise SystemExit('STOP: '+str(e))
