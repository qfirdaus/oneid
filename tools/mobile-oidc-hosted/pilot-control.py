#!/usr/bin/env python3
"""Staging-only login control: bounded pilot or persistent open staging testing."""
import argparse,json,os,shutil,subprocess,time,urllib.request,urllib.error
from pathlib import Path
ROOT=Path('/var/www/oneid-uat');SNIP=Path('/etc/nginx/snippets/oneid-mobile-uat-dormant.conf')
UNIT=Path('/etc/systemd/system/oneid-mobile-pilot.service');DROP=Path('/etc/systemd/system/oneid-mobile-hydra-uat.service.d/50-pilot-hosts.conf')
STATE=Path('/etc/oneid-mobile-uat/pilot-before.conf')
MARKER=Path('/etc/oneid-mobile-uat/open-staging.enabled')
CLIENT='oneid-uat-controlled-browser'
OPENER=urllib.request.build_opener(urllib.request.ProxyHandler({}))
def run(*args):subprocess.run(list(args),check=True)
def wait_https(check=None, pause=time.sleep, attempts=40):
 def probe():
  result=subprocess.run(['curl','--silent','--show-error','--noproxy','*','--resolve','oneid-uat.upnm.edu.my:443:127.0.0.1','--max-time','3','--output','/dev/null','--write-out','%{http_code}','https://oneid-uat.upnm.edu.my/mobile-test/'],capture_output=True,text=True)
  return result.returncode==0 and result.stdout=='200'
 check=check or probe
 for attempt in range(attempts):
  if check():return
  pause(.5)
 raise RuntimeError('Pilot HTTPS readiness timed out; configuration restored')
def wait_service(url, host=None):
 for attempt in range(40):
  try:
   req=urllib.request.Request(url,headers={'Host':host} if host else {})
   with OPENER.open(req,timeout=2) as r:
    if r.status==200:return
  except (OSError,urllib.error.URLError):pass
  time.sleep(.5)
 raise RuntimeError('Private service readiness timed out')
def staging_snippet(open_access=False):
 snippet=(ROOT/'deployment/mobile-oidc/nginx.staging-locations.conf.example').read_text()
 if open_access:
  import re
  def expose(match):
   block=match.group(0)
   if block.startswith('location = /token-hook '):return block
   return block.replace('    allow 127.0.0.1;\n    allow ::1;\n    deny all;\n','')
  snippet=re.sub(r'location = [^ ]+ \{.*?\n\}',expose,snippet,flags=re.S)
  snippet=snippet.replace('# REVIEW ONLY: include inside existing staging HTTPS server; do not create a duplicate server.','# Managed staging test routes; existing HTTPS server only.').replace('# Pilot remains loopback-only until an explicit access policy is configured.','# Open staging: external routes available; token hook remains loopback-only.').replace('# Dedicated FPM socket must exist before installation; hosted enabled=false remains required.','# Feature state is controlled by the private staging adapter configuration.')
 snippet=snippet.replace('client_max_body_size 64k;','client_max_body_size 64k;\n    error_log /dev/null crit;')
 acl='' if open_access else ' allow 127.0.0.1; allow ::1; deny all;\n'
 return snippet+'\nlocation ^~ /mobile-test/ {\n'+acl+' client_max_body_size 4k;\n error_log /dev/null crit;\n access_log /var/log/nginx/oneid-mobile-uat.access.log oneid_safe;\n proxy_pass http://127.0.0.1:24147;\n proxy_set_header Host $host;\n}\n'

def harness_unit(open_access=False):
 dependencies='After=network.target oneid-mobile-hydra-uat.service\nWants=oneid-mobile-hydra-uat.service\n' if open_access else ''
 lifetime='Restart=on-failure\nRestartSec=5\nEnvironment=ONEID_TEST_MAX_SESSIONS=500\n' if open_access else 'RuntimeMaxSec=7200\n'
 return '[Unit]\nDescription=OneID staging browser test\n'+dependencies+'[Service]\nUser=www-data\nGroup=www-data\nExecStart=/usr/bin/python3 /var/www/oneid-uat/tools/mobile-oidc-hosted/pilot-harness.py\n'+lifetime+'NoNewPrivileges=true\nPrivateTmp=true\nProtectSystem=strict\nProtectHome=true\nUMask=0077\n'+('[Install]\nWantedBy=multi-user.target\n' if open_access else '')

def stop():
 subprocess.run(['systemctl','disable','oneid-mobile-pilot'],check=False,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
 if MARKER.exists():MARKER.unlink()
 run('php',str(ROOT/'tools/mobile-oidc-hosted/pilot-state.php'),'--stop')
 if STATE.exists():shutil.copyfile(STATE,SNIP)
 run('nginx','-t');run('systemctl','reload','nginx')
 subprocess.run(['systemctl','stop','oneid-mobile-pilot'],check=False)
 if DROP.exists():DROP.unlink();run('systemctl','daemon-reload');run('systemctl','restart','oneid-mobile-hydra-uat')
 print('STOPPED: hosted/observer OFF, dormant routes restored. Pilot client retained for review; no sessions deleted.')
def main():
 p=argparse.ArgumentParser(description=__doc__);p.add_argument('action',choices=['start','retry','stop','open']);a=p.parse_args()
 if os.geteuid()!=0 or Path(__file__).resolve().parents[2]!=ROOT:raise RuntimeError('Run with sudo in staging workspace')
 if a.action=='stop':stop();return
 open_access=a.action=='open'
 if not open_access and MARKER.exists():raise RuntimeError('Use open for persistent staging or stop to disable it')
 retry=a.action in ['retry','open']
 if open_access:
  if not STATE.is_file() or STATE.is_symlink() or STATE.stat().st_uid!=0 or STATE.read_text().count('return 404;')!=12:raise RuntimeError('Reviewed dormant rollback configuration required')
  if SNIP.is_symlink() or SNIP.stat().st_uid!=0 or SNIP.read_text() not in [STATE.read_text(),staging_snippet(False),staging_snippet(True)]:raise RuntimeError('Unrecognized staging route configuration')
 elif retry:
  if not STATE.is_file() or not UNIT.is_file() or DROP.exists():raise RuntimeError('Retry requires previous rolled-back pilot; no active override')
  if STATE.read_bytes()!=SNIP.read_bytes():raise RuntimeError('Dormant configuration differs from saved backup')
  if STATE.stat().st_uid!=0 or UNIT.stat().st_uid!=0 or STATE.is_symlink() or UNIT.is_symlink():raise RuntimeError('Unexpected pilot file ownership')
 elif STATE.exists() or UNIT.exists() or DROP.exists():raise RuntimeError('Existing pilot files require review; use retry only after rollback')
 if not open_access and SNIP.read_text().count('return 404;')!=12:raise RuntimeError('Dormant guard changed')
 run('/usr/bin/python3','-c','import cryptography')
 # Fetch only readiness, not configuration/secrets.
 with OPENER.open('http://127.0.0.1:24145/health/ready',timeout=5) as r:
  if r.status!=200:raise RuntimeError('Provider not ready')
 os.umask(0o077)
 if not retry:shutil.copyfile(SNIP,STATE)
 try:
  client={'client_id':CLIENT,'client_name':'Controlled staging browser pilot','grant_types':['authorization_code','refresh_token'],'response_types':['code'],'redirect_uris':['https://oneid-uat.upnm.edu.my/mobile-test/callback'],'scope':'openid profile offline_access mobile:session','audience':['oneid-mobile-session'],'token_endpoint_auth_method':'none'}
  if retry:
   with OPENER.open('http://127.0.0.1:24145/admin/clients/'+CLIENT,timeout=10) as r:existing=json.load(r)
   for key,value in client.items():
    actual=existing.get(key)
    matches=sorted(actual)==sorted(value) if isinstance(value,list) and isinstance(actual,list) else actual==value
    if not matches:raise RuntimeError('Existing pilot client differs; refusing changes')
  else:
   req=urllib.request.Request('http://127.0.0.1:24145/admin/clients',data=json.dumps(client).encode(),headers={'Content-Type':'application/json'})
   with OPENER.open(req,timeout=10) as r:
    if r.status!=201:raise RuntimeError('Client registration failed')
  # Namespace-specific hosts mapping for Hydra hook; system-wide DNS is unchanged.
  hosts=Path('/etc/oneid-mobile-uat/pilot-hosts')
  lines=[x for x in Path('/etc/hosts').read_text().splitlines() if 'oneid-uat.upnm.edu.my' not in x.split('#')[0].split()]
  hosts.write_text('\n'.join(lines)+'\n127.0.0.1 oneid-uat.upnm.edu.my\n');hosts.chmod(0o644)
  DROP.parent.mkdir(exist_ok=True,mode=0o755);DROP.parent.chmod(0o755)
  DROP.write_text('[Service]\nBindReadOnlyPaths=/etc/oneid-mobile-uat/pilot-hosts:/etc/hosts\n');DROP.chmod(0o644)
  UNIT.write_text(harness_unit(open_access));UNIT.chmod(0o644)
  run('systemctl','daemon-reload');run('systemctl','restart','oneid-mobile-hydra-uat');run('systemctl','restart' if open_access else 'start','oneid-mobile-pilot')
  wait_service('http://127.0.0.1:24145/health/ready')
  wait_service('http://127.0.0.1:24147/mobile-test/','oneid-uat.upnm.edu.my')
  run('php',str(ROOT/'tools/mobile-oidc-hosted/pilot-state.php'),'--open' if open_access else ('--resume' if retry else '--start'))
  snippet=staging_snippet(open_access)
  SNIP.write_text(snippet);SNIP.chmod(0o644)
  run('nginx','-t');run('systemctl','reload','nginx')
  run('systemctl','is-active','--quiet','oneid-mobile-pilot','oneid-mobile-hydra-uat')
  wait_https()
  if open_access:
   run('systemctl','enable','oneid-mobile-pilot','oneid-mobile-hydra-uat','oneid-mobile-postgres-uat')
   MARKER.write_text('open-staging\n')
   print('SUCCESS: all eligible staging accounts enabled; no two-hour deadline. Services enabled at boot. Open https://oneid-uat.upnm.edu.my/mobile-test/ without SSH tunnel.')
   return
  print('SUCCESS: loopback-only pilot ready for 0530-09. Two-hour adapter/harness limit. Run this script with stop after testing.')
 except BaseException:
  print('Pilot failed; restoring dormant state. No token/secret output.')
  stop();raise
if __name__=='__main__':
 try:main()
 except Exception as e:print('STOP: '+type(e).__name__+(': '+str(e) if isinstance(e,RuntimeError) else ''));raise SystemExit(1)
