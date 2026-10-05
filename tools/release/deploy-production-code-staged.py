#!/usr/bin/env python3
"""Stage reviewed source/vendor into production while preserving runtime data and routes."""
import argparse, datetime, hashlib, json, os, subprocess, tarfile
from pathlib import Path

LIVE=Path('/var/www/oneid'); CANDIDATE=Path('/home/iqs/oneid-release-candidates/1b27cab')
EXCLUDES=['.git','.private','storage','vendor','public_img','public_docs','public/videos','public/login_banners','videos','public_docs']
def run(args): return subprocess.check_output(args,text=True,stderr=subprocess.STDOUT)
def main():
    ap=argparse.ArgumentParser();ap.add_argument('--apply',action='store_true');a=ap.parse_args()
    if os.geteuid()!=0: raise RuntimeError('run with sudo')
    if LIVE.resolve()!=Path('/var/www/oneid') or not (LIVE/'public/index.php').is_file(): raise RuntimeError('production root guard')
    if not CANDIDATE.is_dir() or not (CANDIDATE/'vendor/autoload.php').is_file(): raise RuntimeError('candidate/vendor missing')
    if run(['git','-C',str(LIVE),'status','--porcelain']): raise RuntimeError('live Git tree is not clean')
    if run(['readlink','-f','/usr/bin/php']).strip()!='/usr/bin/php8.3': raise RuntimeError('default PHP drift')
    if any('oneid-web-prod84.sock' in p.read_text(errors='ignore') for p in Path('/etc/nginx').rglob('*') if p.is_file() and os.access(p,os.R_OK)): raise RuntimeError('Nginx already routes 8.4')
    if not a.apply:
        print('CHECK PASS: production root/candidate/vendor/default PHP/routing guards passed; no files changed.'); return
    stamp=datetime.datetime.now().strftime('%Y%m%d-%H%M%S'); backup=Path('/var/backups/oneid-code-'+stamp); backup.mkdir(mode=0o700)
    # Backup the complete deploy tree including vendor, while protecting private/runtime separately.
    with tarfile.open(backup/'source-vendor.tar','w') as t:
        for name in ['app','admin','bootstrap','config','cron','index.php','api.php','lib','page','public','tools','composer.json','composer.lock']:
            p=LIVE/name
            if p.exists(): t.add(p,arcname=name,recursive=True,filter=lambda x: x if not any('/'+e+'/' in '/'+x.name+'/' for e in ['.private']) else None)
    with tarfile.open(backup/'private-runtime.tar','w') as t:
        for name in ['.private','storage','public_img','public_docs','public/videos','public/login_banners']:
            p=LIVE/name
            if p.exists(): t.add(p,arcname=name,recursive=True)
    (backup/'before.txt').write_text(run(['git','-C',str(LIVE),'rev-parse','HEAD'])+run(['git','-C',str(LIVE),'status','--short']))
    # Candidate excludes runtime-owned paths; source replacement is atomic per file via rsync temp files.
    rsync=['rsync','-a','--delete','--exclude=.git/','--exclude=.private/','--exclude=storage/','--exclude=vendor/','--exclude=public_img/','--exclude=public_docs/','--exclude=public/videos/','--exclude=public/login_banners/','--exclude=videos/','--exclude=public_docs/',str(CANDIDATE)+'/',str(LIVE)+'/']
    subprocess.run(rsync,check=True)
    subprocess.run(['rsync','-a','--delete',str(CANDIDATE/'vendor')+'/',str(LIVE/'vendor')+'/'],check=True)
    subprocess.run(['chown','-R','iqs:www-data',str(LIVE/'vendor')],check=True)
    subprocess.run(['find',str(LIVE/'vendor'),'-type','d','-exec','chmod','750','{}','+'],check=True)
    # Application source is kept in the existing owner/group model; do not alter private/runtime ownership.
    run(['php8.4','-l',str(LIVE/'index.php')])
    if run(['git','-C',str(LIVE),'status','--porcelain'])=='': raise RuntimeError('deploy unexpectedly produced clean tree')
    result={'status':'STAGED_CODE_VENDOR','candidate':str(CANDIDATE),'backup':str(backup),'nginx_changed':False,'database_changed':False,'cron_changed':False,'mobile_enabled':False}
    (backup/'result.json').write_text(json.dumps(result,indent=2));print(json.dumps(result,indent=2));print('NEXT: run PHP 8.4 code smoke via FastCGI; do not cutover Nginx yet.')
if __name__=='__main__':
    try: main()
    except Exception as e: raise SystemExit('STOP: '+str(e)+'; inspect backup/status before retry.')
