"""No services or real configuration mutated: validate routes and mocked rollout/rollback."""
import importlib.util, re, tempfile, json, sys
from pathlib import Path
source=Path(__file__).resolve().parents[2]/'tools/mobile-oidc-hosted/pilot-control.py'
spec=importlib.util.spec_from_file_location('control',source);m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
pilot=m.staging_snippet(False);opened=m.staging_snippet(True)
blocks=re.findall(r'location = [^ ]+ \{.*?\n\}',opened,re.S)
assert len(blocks)==13
for b in blocks:
 assert ('deny all;' in b)==b.startswith('location = /token-hook ')
assert 'deny all;' not in opened.split('location ^~ /mobile-test/')[1]
assert '/admin/' not in opened and '24145' not in opened
assert 'RuntimeMaxSec' not in m.harness_unit(True) and 'WantedBy=multi-user.target' in m.harness_unit(True)
assert 'Restart=on-failure' in m.harness_unit(True) and 'RuntimeMaxSec=7200' in m.harness_unit(False)
print('PASS: external login routes open; hook private; admin not exposed; persistent unit; bounded pilot retained')
actual=Path('/etc/nginx/snippets/oneid-mobile-uat-dormant.conf')
if actual.exists():
 text=actual.read_text()
 assert text in (pilot,opened) or text.count('return 404;')==12
 print('PASS: installed snippet recognized by migration guard')
class Response:
 status=200
 def __init__(self,client=False):self.client=client
 def __enter__(self):return self
 def __exit__(self,*args):pass
 def read(self):return json.dumps({'client_id':m.CLIENT,'client_name':'Controlled staging browser pilot','grant_types':['authorization_code','refresh_token'],'response_types':['code'],'redirect_uris':['https://oneid-uat.upnm.edu.my/mobile-test/callback'],'scope':'openid profile offline_access mobile:session','audience':['oneid-mobile-session'],'token_endpoint_auth_method':'none'}).encode()
class Opener:
 def open(self,*args,**kwargs):return Response()
template=(m.ROOT/'deployment/mobile-oidc/nginx.staging-locations.conf.example').read_text()
for fail in [False,True]:
 with tempfile.TemporaryDirectory() as directory:
  root=Path(directory);m.ROOT=root;m.__file__=str(root/'tools/mobile-oidc-hosted/pilot-control.py')
  p=root/'deployment/mobile-oidc/nginx.staging-locations.conf.example';p.parent.mkdir(parents=True);p.write_text(template)
  m.SNIP=root/'snippet';m.STATE=root/'before';m.UNIT=root/'unit';m.DROP=root/'drop'/'override';m.MARKER=root/'marker'
  dormant='return 404;\n'*12;m.STATE.write_text(dormant);m.SNIP.write_text(pilot);m.UNIT.write_text('previous')
  # Guard ownership is represented by temporary root-owned fixture files only.
  # Running as non-root: wrap stat result to expose the reviewed owner in this fixture.
  original_stat=Path.stat
  def stat(path,*args,**kw):
   result=original_stat(path,*args,**kw)
   if path in (m.STATE,m.SNIP):
    values=list(result);values[4]=0
    import os
    return os.stat_result(values)
   return result
  Path.stat=stat;m.os.geteuid=lambda:0;m.OPENER=Opener();calls=[];m.run=lambda *args:calls.append(args)
  m.wait_service=lambda *args:None
  def ready():
   if fail:raise RuntimeError('simulated readiness failure')
  m.wait_https=ready
  # Redirect the one fixed private hosts path into the temporary fixture.
  original_write=Path.write_text;original_chmod=Path.chmod
  def write(path,*args,**kw):
   return original_write(root/'hosts' if str(path)=='/etc/oneid-mobile-uat/pilot-hosts' else path,*args,**kw)
  def chmod(path,*args,**kw):
   return original_chmod(root/'hosts' if str(path)=='/etc/oneid-mobile-uat/pilot-hosts' else path,*args,**kw)
  Path.write_text=write;Path.chmod=chmod;m.subprocess.run=lambda *args,**kw:None
  sys.argv=['pilot-control.py','open']
  try:
   try:m.main()
   except RuntimeError:
    assert fail
   if fail:
    assert m.SNIP.read_text()==dormant and not m.MARKER.exists()
    assert any('--stop' in c for c in calls)
   else:
    assert m.SNIP.read_text()==opened and m.MARKER.exists()
    assert any('--open' in c for c in calls)
    assert any(c[:2]==('systemctl','enable') for c in calls)
  finally:Path.stat=original_stat;Path.write_text=original_write;Path.chmod=original_chmod
 print('PASS: mocked '+('failure restores dormant state' if fail else 'open rollout enables persistent staging'))
