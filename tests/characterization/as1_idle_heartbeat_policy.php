<?php

if(PHP_SAPI!=='cli'){exit(2);}require_once dirname(__DIR__,2).'/lib/session_security.php';
$checks=0;$failed=0;$report=static function(bool $ok,string $label)use(&$checks,&$failed):void{$checks++;if(!$ok)$failed++;printf("%s %s\n",$ok?'PASS':'FAIL',$label);};
$_SERVER['REQUEST_METHOD']='POST';
$report(oneid_is_technical_heartbeat_request(['update_specific_token_datetime'=>'1']),'exact heartbeat action is classified as technical');
$report(!oneid_is_technical_heartbeat_request(['update_specific_token_datetime'=>'1','other'=>'x']),'mixed request cannot masquerade as a technical heartbeat');
$_SERVER['REQUEST_METHOD']='GET';
$report(!oneid_is_technical_heartbeat_request(['update_specific_token_datetime'=>'1']),'non-POST request is not a technical heartbeat');
$report(!oneid_session_is_expired(1800,0,0),'idle session remains valid at the exact 30-minute boundary');
$report(oneid_session_is_expired(1801,0,0),'idle session expires one second after 30 minutes');
$report(!oneid_session_is_expired(28800,0,28799),'active session remains valid at the exact 8-hour boundary');
$report(oneid_session_is_expired(28801,0,28800),'active session expires one second after the 8-hour absolute boundary');
$report(oneid_session_next_activity(300,100,true)===100,'technical heartbeat does not advance human idle activity');
$report(oneid_session_next_activity(300,100,false)===300,'meaningful request advances human idle activity');
printf("RESULT checks=%d failed=%d\n",$checks,$failed);exit($failed===0?0:1);
