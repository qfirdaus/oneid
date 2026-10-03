#!/usr/bin/env python3
"""Read-only post-cutover snapshot; no raw log lines, tokens or identities emitted."""
import argparse,collections,datetime,json,re,subprocess
from pathlib import Path
parser=argparse.ArgumentParser()
parser.add_argument('--since',default='2026-10-03T14:10:22',help='Malaysia local time ISO timestamp')
a=parser.parse_args();since=datetime.datetime.fromisoformat(a.since)
report={'checked_at':datetime.datetime.now().astimezone().isoformat(),'since_local':a.since,'scope':'Point-in-time snapshot, not continuous monitoring','services':{},'access_logs':{},'error_logs':{}}
for service in ['nginx','php8.3-fpm','php8.4-fpm']:
 r=subprocess.run(['systemctl','show',service,'-p','ActiveState','-p','SubState','-p','NRestarts','-p','MemoryCurrent','-p','TasksCurrent'],capture_output=True,text=True,check=True)
 report['services'][service]=dict(line.split('=',1) for line in r.stdout.splitlines() if '=' in line)
for name in ['oneid-uat.access.log','oneid-mobile-uat.access.log']:
 path=Path('/var/log/nginx')/name;counts=collections.Counter();unparsed=0
 try:
  with path.open(errors='replace') as f:
   for line in f:
    m=re.search(r'\[(\d{2}/[A-Za-z]{3}/\d{4}:\d{2}:\d{2}:\d{2}) [+-]\d{4}\].*?"\s+(\d{3})\s+',line)
    if not m:unparsed+=1;continue
    stamp=datetime.datetime.strptime(m[1],'%d/%b/%Y:%H:%M:%S')
    if stamp>=since:counts[m[2]]+=1
  report['access_logs'][name]={'status_counts':dict(sorted(counts.items())),'http_5xx':sum(v for k,v in counts.items() if k.startswith('5')),'unparsed_lines_whole_file':unparsed}
 except PermissionError:report['access_logs'][name]={'readable':False}
for path in [Path('/var/log/nginx/oneid-uat.error.log'),Path('/var/log/php8.4-fpm.log')]:
 counts=collections.Counter();recent=0
 try:
  with path.open(errors='replace') as f:
   for line in f:
    stamp=None
    m=re.match(r'(\d{4}/\d{2}/\d{2} \d{2}:\d{2}:\d{2})',line)
    if m:stamp=datetime.datetime.strptime(m[1],'%Y/%m/%d %H:%M:%S')
    m2=re.match(r'\[(\d{2}-[A-Za-z]{3}-\d{4} \d{2}:\d{2}:\d{2})\]',line)
    if m2:stamp=datetime.datetime.strptime(m2[1],'%d-%b-%Y %H:%M:%S')
    if stamp is None or stamp<since:continue
    recent+=1
    for label,pattern in {'integration_audit':r'oneid_integration.*api_access','php_fatal':r'PHP (?:Fatal|Parse) error','php_warning':r'PHP Warning','php_deprecated':r'PHP Deprecated','upstream_timeout':r'upstream timed out','upstream_connect_failure':r'connect\(\).*failed','pool_capacity_warning':r'max_children','worker_exit_signal':r'exited on signal'}.items():
     if re.search(pattern,line,re.I):counts[label]+=1
  report['error_logs'][path.name]={'readable':True,'timestamped_entries':recent,'categories':dict(counts)}
 except PermissionError:report['error_logs'][path.name]={'readable':False,'limitation':'Requires root or permitted log group; no absence-of-errors claim'}
report['default_php']=str(Path('/usr/bin/php').resolve())
report['limitations']=['Current log files only; no rotated logs','Counts include unauthenticated smoke checks and normal rejected requests','Mobile Nginx routes suppress error logs; FPM log visibility matters','No claim of native SDK compatibility or production capacity']
print(json.dumps(report,indent=2))
