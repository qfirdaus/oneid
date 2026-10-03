#!/usr/bin/env python3
"""Local synthetic DB only; reuse a specified installed MySQL binary, never contact an existing server."""
import argparse,json,os,subprocess,tempfile,time
from pathlib import Path
root=Path(__file__).resolve().parents[2]
p=argparse.ArgumentParser();p.add_argument('--mysql-base',required=True,type=Path);a=p.parse_args()
base=a.mysql_base.resolve();binary=base/'usr/sbin/mysqld'
(root/'.private').mkdir(exist_ok=True,mode=0o700)
run=Path(tempfile.mkdtemp(prefix='release-db-',dir=root/'.private'));run.chmod(0o700)
# UNIX socket path length is limited; place the process directory in /tmp if worktree path is long.
# PHP verifier intentionally pins its own private directory, so use relative socket from cwd.
sock=run/'data/mysql.sock';env={**os.environ,'LD_LIBRARY_PATH':str(base/'usr/lib/x86_64-linux-gnu')}
args=[str(binary),'--no-defaults','--basedir='+str(base/'usr'),'--datadir='+str(run/'data'),'--innodb-buffer-pool-size=32M','--innodb-redo-log-capacity=32M']
proc=None;results=[]
try:
 with (run/'mysql.log').open('ab') as log:
  subprocess.run(args+['--initialize-insecure'],env=env,stdout=log,stderr=log,check=True,timeout=90)
  proc=subprocess.Popen(args+['--skip-networking','--mysqlx=0','--socket=mysql.sock','--pid-file=mysql.pid','--secure-file-priv=NULL'],cwd=run,env=env,stdout=log,stderr=log)
  deadline=time.monotonic()+30
  while not sock.exists():
   if proc.poll() is not None or time.monotonic()>deadline:raise RuntimeError('Private DB not ready')
   time.sleep(.1)
  # mysqld resolves a relative socket under datadir; PDO uses the validated socket directory.
  for php in ('php8.3','php8.4'):
   result=subprocess.run([php,str(root/'tests/release/mobile-schema.php'),str(sock)],cwd=run,text=True,capture_output=True,timeout=60)
   (run/(php+'.txt')).write_text(result.stdout+result.stderr)
   print(php+': '+(result.stdout.strip().splitlines()[-1] if result.stdout else 'FAILED'),flush=True)
   if result.returncode:raise RuntimeError('Fixture failed; inspect private evidence '+str(run))
   results.append({'runtime':php,'status':'PASS','result':result.stdout.strip().splitlines()[-1]})
finally:
 if proc is not None and proc.poll() is None:
  proc.terminate()
  try:proc.wait(timeout=15)
  except subprocess.TimeoutExpired:proc.kill();proc.wait()
report={'results':results,'services_stopped':True,'production_accessed':False,'scope':'Synthetic private MySQL DDL, not production apply wrapper'}
(root/'docs/release/migration-validation.json').write_text(json.dumps(report,indent=2)+'\n')
print(json.dumps(report,indent=2))
