<?php

if(PHP_SAPI!=='cli'){exit(2);}$root=dirname(__DIR__);$checks=0;$failed=0;$report=static function(bool $ok,string $label)use(&$checks,&$failed):void{$checks++;if(!$ok)$failed++;printf("%s %s\n",$ok?'PASS':'FAIL',$label);};
$files=['lib/session_security.php','app/Admin/SessionHousekeepingPolicy.php','tools/as1_session_housekeeping.php','tests/characterization/as1_idle_heartbeat_policy.php','tests/characterization/as1_session_housekeeping_policy.php'];$source=[];
foreach($files as$file){exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($root.'/'.$file),$out,$code);$report($code===0,'PHP lint '.$file);$source[$file]=file_get_contents($root.'/'.$file);}
$session=$source['lib/session_security.php'];$tool=$source['tools/as1_session_housekeeping.php'];
$report(str_contains($session,'oneid_is_technical_heartbeat_request')&&str_contains($session,'oneid_session_next_activity'),'technical heartbeat is separated from meaningful PHP activity');
$report(str_contains($session,'1800')&&str_contains($session,'28800'),'PHP idle and absolute timeout boundaries remain explicit');
$report(str_contains($tool,"in_array('--check'")&&str_contains($tool,'mutation_statements=0'),'housekeeping exposes a read-only check mode');
$report(str_contains($tool,"getenv('ONEID_SESSION_HOUSEKEEPING_APPLY_ENABLED') !== 'true'"),'housekeeping Apply defaults fail closed');
$report(str_contains($tool,'GET_LOCK')&&str_contains($tool,'FOR UPDATE')&&str_contains($tool,'beginTransaction')&&str_contains($tool,'AS1_RECONCILIATION_FAILED'),'Apply uses an advisory lock, transaction and exact reconciliation');
$report(str_contains($tool,'syslog_record(7')&&str_contains($tool,'change_id='),'successful Apply writes a bounded audit summary');
$report(!str_contains($tool,'DELETE FROM token_tbl'),'housekeeping does not perform retention purge');
exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/tests/characterization/as1_idle_heartbeat_policy.php'),$out1,$code1);
$report($code1===0&&in_array('RESULT checks=9 failed=0',$out1,true),'idle heartbeat characterization passes');
exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/tests/characterization/as1_session_housekeeping_policy.php'),$out2,$code2);
$report($code2===0&&in_array('RESULT checks=6 failed=0',$out2,true),'housekeeping policy characterization passes');
printf("RESULT checks=%d failed=%d\n",$checks,$failed);exit($failed===0?0:1);
