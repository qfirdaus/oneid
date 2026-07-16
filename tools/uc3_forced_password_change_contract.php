<?php
declare(strict_types=1);$root=dirname(__DIR__);$guard=(string)file_get_contents($root.'/lib/request_security.php');$db=(string)file_get_contents($root.'/lib/Database.php');$q=(string)file_get_contents($root.'/lib/q_func.php');$n=0;$f=0;$ok=function($v,$d)use(&$n,&$f){$n++;if(!$v)$f++;printf("%s: %s\n",$v?'PASS':'FAIL',$d);};
$ok(str_contains($q,'oneid_guard_q_func_request($_POST,$operation)'),'request guard receives persistence for authoritative state');
$ok(str_contains($db,'get_password_change_requirement')&&str_contains($db,'SELECT password_change_required,avail_status'),'database exposes minimal forced-change state');
$ok(str_contains($guard,"['check_default_password','action_change_password']")&&str_contains($guard,"'UC3_PASSWORD_CHANGE_REQUIRED'"),'only status and password change actions remain available while forced');
$ok(str_contains($guard,"\$_SESSION['password_change_required']=(int)")&&str_contains($guard,"avail_status']??0") ,'session flag is refreshed and inactive accounts fail closed');
$ok(!str_contains($guard,"'go_to_service_provider'],true") ,'SSO launch is not exempted from forced-change enforcement');
printf("RESULT: checks=%d failures=%d\n",$n,$f);exit($f===0?0:1);
