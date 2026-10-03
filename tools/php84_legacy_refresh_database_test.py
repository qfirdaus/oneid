#!/usr/bin/env python3
import sys,json,subprocess
from pathlib import Path
sys.dont_write_bytecode=True
ROOT=Path(__file__).resolve().parent.parent
sys.path.insert(0,str(ROOT/'tools/mobile-oidc-hosted'))
from private_mysql import PrivateMysql
mysql=PrivateMysql(ROOT,ROOT/'.private/mobile-oidc-poc')
results=[]
try:
 mysql.start()
 for version in ['8.3','8.4']:
  r=subprocess.run(['/usr/bin/php'+version,str(ROOT/'tests/characterization/php84_legacy_refresh_database.php'),str(mysql.socket)],capture_output=True,text=True,timeout=60)
  if r.stderr or r.returncode:
   print('FAIL runtime '+version+'; '+r.stderr[:600]);
  results.append({'version':version,'exit_code':r.returncode,'stderr_empty':not r.stderr,'result':json.loads(r.stdout) if r.stdout else None})
finally:mysql.close()
report={'scope':'Private MySQL actual Database refresh method; CLI 8.3/8.4, FPM API branches tested separately','results':results,'private_mysql_stopped':mysql.process is None or mysql.process.poll() is not None}
(ROOT/'docs/php84/phase4-legacy-refresh-database.json').write_text(json.dumps(report,indent=2)+'\n')
print(json.dumps(report,indent=2))
raise SystemExit(0 if all(r['exit_code']==0 and r['stderr_empty'] for r in results) else 1)
