import importlib.util, pathlib, tempfile, subprocess, os, json, secrets, socket, time, urllib.request, pwd
root=pathlib.Path('/var/www/oneid-uat')
spec=importlib.util.spec_from_file_location('installer',root/'tools/mobile-oidc-hosted/install-provider-staging.py');m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
p=pathlib.Path(tempfile.mkdtemp(prefix='provider-install-check-',dir=root/'.private'))
pg=root/'.private/mobile-oidc-poc/postgres/usr/lib/postgresql/16/bin'; hydra=root/'.private/mobile-oidc-poc/bin/hydra'
log=open(p/'private.log','wb'); procs=[]
def run(a,**kw):subprocess.run([str(x) for x in a],check=True,stdout=log,stderr=log,**kw)
def port():
 with socket.socket() as s:s.bind(('127.0.0.1',0));return s.getsockname()[1]
def wait(n):
 for i in range(100):
  try:
   with socket.create_connection(('127.0.0.1',n),timeout=.2):return
  except OSError:time.sleep(.2)
 raise RuntimeError('port timeout')
try:
 run([pg/'initdb','-D',p/'data','-U',pwd.getpwuid(os.getuid()).pw_name,'--auth-local=peer','--auth-host=reject','--encoding=UTF8','--no-locale'])
 password=secrets.token_hex(32)
 run([pg/'postgres','--single','-D',p/'data','postgres'],input=f"CREATE ROLE oneid_mobile_provider LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE PASSWORD '{password}';\nCREATE DATABASE oneid_mobile_provider OWNER oneid_mobile_provider;\n".encode())
 (p/'data/pg_hba.conf').write_text('local all all peer\nhost oneid_mobile_provider oneid_mobile_provider 127.0.0.1/32 scram-sha-256\n')
 db,public,admin=port(),port(),port()
 procs.append(subprocess.Popen([str(pg/'postgres'),'-D',str(p/'data'),'-h','127.0.0.1','-p',str(db),'-k',str(p)],stdout=log,stderr=log));wait(db)
 c=m.config(password,secrets.token_hex(32),secrets.token_hex(32));c['dsn']=c['dsn'].replace(':24146/',f':{db}/');c['serve']['public']['port']=public;c['serve']['admin']['port']=admin
 cfg=p/'hydra.json';cfg.write_text(json.dumps(c));cfg.chmod(0o600)
 run([hydra,'migrate','sql','up','-e','-y','-c',cfg])
 procs.append(subprocess.Popen([str(hydra),'serve','all','--sqa-opt-out','-c',str(cfg)],stdout=log,stderr=log));wait(admin)
 opener=urllib.request.build_opener(urllib.request.ProxyHandler({}))
 with opener.open(f'http://127.0.0.1:{admin}/health/ready',timeout=5) as r:assert r.status==200
 with opener.open(f'http://127.0.0.1:{admin}/admin/clients',timeout=5) as r:assert json.load(r)==[]
 print('PASS: fresh dedicated PostgreSQL role/database, SCRAM authentication, provider migrations, non-dev HTTPS-issuer configuration, readiness, zero clients')
 print('Private rehearsal directory:',p)
finally:
 for x in reversed(procs):
  x.terminate()
  try:x.wait(timeout=15)
  except subprocess.TimeoutExpired:x.kill();x.wait()
 log.close()
