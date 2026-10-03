#!/usr/bin/env python3
"""Run reviewed synthetic tests as temporary FastCGI scripts, outside web roots."""
import json,re,time
from pathlib import Path
from php84_fpm_probe import request_source
ROOT=Path(__file__).resolve().parent.parent
TESTS={
'oneid-web-uat84':['tests/characterization/mydigitalid_f1_foundation.php','tests/characterization/mydigitalid_f3_callback_foundation.php','tests/characterization/mydigitalid_f4b_callback_session.php','tests/characterization/mydigitalid_f7_rejection_logout.php','tests/characterization/user_session_timeout_f1_policy.php'],
'oneid-mobile-uat84':['tests/mobile-oidc/adapter.php','tests/mobile-oidc/mydigitalid.php','tests/mobile-oidc/profile.php','tests/mobile-oidc-hosted/protocol.php','tests/mobile-oidc-hosted/mydigitalid-selection.php']}
def main():
 results=[]
 for pool,tests in TESTS.items():
  for name in tests:
   f=ROOT/name;s=f.read_text()
   # Only the temporary copy bypasses CLI restriction; production test files stay intact.
   s=s.replace("if (PHP_SAPI !== 'cli') {\n    exit(2);\n}", '')
   s=s.replace('__DIR__',repr(str(f.parent))).replace('__FILE__',repr(str(f)))
   # Capture notices even where original application code disables display_errors.
   start=s.index(';',s.index('declare('))+1 if 'declare(' in s else len('<?php')
   s=s[:start]+"\nerror_reporting(E_ALL); set_error_handler(static function($severity,$message,$file,$line){ throw new \\ErrorException($message,0,$severity,$file,$line); });\n"+s[start:]
   t=time.monotonic()
   try:
    output,stderr=request_source('/run/php/'+pool+'.sock',s,include_stderr=True)
    expected=[]
    remaining=stderr
    if name=='tests/characterization/user_session_timeout_f1_policy.php':
     for message in ['User session timeout policy fallback reason=InvalidArgumentException','User portal session expiry audit unavailable']:
      if 'PHP message: '+message in remaining:
       expected.append(message);remaining=remaining.replace('PHP message: '+message,'')
    unexpected=remaining.strip('; \r\n')
    passes=len(re.findall(r'^PASS\b',output,re.M));fail=bool(re.search(r'(^FAIL\b|Fatal error|Warning:|Deprecated:)',output,re.M)) or passes==0 or bool(unexpected) or bool(re.search(r"failed=[1-9]",output))
    result={'test':name,'pool':pool,'status':'FAIL' if fail else 'PASS','pass_lines':passes,'expected_log_messages':expected,'unexpected_stderr':unexpected,'elapsed_seconds':round(time.monotonic()-t,3),'output_tail':output[-600:] if fail else ''}
   except Exception as e:result={'test':name,'pool':pool,'status':'FAIL','error':str(e)[:1000]}
   results.append(result);print(name,result['status'],flush=True)
 report={'date':'2026-10-03','runtime':'FPM 8.4.26','method':'Temporary CLI-guard-removed copies of reviewed synthetic tests; original source unmodified; no real credentials/provider/SMTP/DB','limitations':'Not real browser E2E, real SMTP/ODBC or downstream authentication. Timings are fixture execution, not production latency.','results':results}
 (ROOT/'docs/php84/phase4-fpm-fixtures.json').write_text(json.dumps(report,indent=2)+'\n')
 return 1 if any(x['status']!='PASS' for x in results) else 0
if __name__=='__main__':raise SystemExit(main())
