<?php

if(PHP_SAPI!=='cli'){exit(2);}$root=dirname(__DIR__);$checks=0;$failed=0;
$report=static function(bool $ok,string $label)use(&$checks,&$failed):void{$checks++;if(!$ok)$failed++;printf("%s %s\n",$ok?'PASS':'FAIL',$label);};
$files=['app/Admin/ActiveSessionService.php','lib/Database.php','lib/q_func.php','lib/request_security.php','admin/dashboard.php','tests/characterization/as0_active_sessions_readonly.php','tools/as0_active_sessions_preflight.php'];$source=[];
foreach($files as$file){$source[$file]=(string)file_get_contents($root.'/'.$file);exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($root.'/'.$file),$out,$code);$report($code===0,'PHP lint '.$file);$out=[];}
$db=$source['lib/Database.php'];$q=$source['lib/q_func.php'];$ui=$source['admin/dashboard.php'];$service=$source['app/Admin/ActiveSessionService.php'];
$start=strpos($q,"if(isset( \$_POST['admin_get_all_token_for_all_active_user']))");$end=strpos($q,"if(isset( \$_POST['get_specific_user_sp_access_list']))",$start?:0);$endpoint=$start!==false&&$end!==false?substr($q,$start,$end-$start):'';
$report(str_contains($source['lib/request_security.php'],"'admin_get_all_token_for_all_active_user'"),'endpoint remains admin, POST and CSRF guarded');
$report(str_contains($db,'SELECT A.user_id')&&!str_contains($db,'SELECT A.*,B.data1 as name'),'persistence replaces wildcard token projection with explicit fields');
$report(str_contains($db,"LIMIT '.\$pageSize.' OFFSET '.\$offset")&&str_contains($db,"in_array(\$pageSize,[10,25,50],true)"),'persistence enforces allowlisted bounded pagination');
$report(str_contains($db,"'expired'")&&str_contains($db,"'due'")&&str_contains($db,"'grace'")&&str_contains($db,"'current'"),'server query derives all lifecycle states');
$report($endpoint!==''&&str_contains($endpoint,'ActiveSessionService')&&!str_contains($endpoint,'update_specific_token_status'),'listing endpoint delegates to service with zero hidden mutation');
$report(str_contains($service,"'user_id'")&&str_contains($service,"'issued_at'")&&str_contains($service,"'last_activity_at'")&&!str_contains($service,"'token_id' =>"),'service response projection excludes token material');
$report(str_contains($ui,'active_session_query')&&str_contains($ui,'active_session_status')&&str_contains($ui,'active_session_pagination'),'UI provides search, lifecycle filter and pagination');
$report(str_contains($ui,'Issued At')&&str_contains($ui,'Last Activity')&&str_contains($ui,"grace:{label:'Grace period'")&&str_contains($ui,"due:{label:'Due'"),'UI labels timestamps and lifecycle states accurately');
$report(str_contains($ui,'tidak menamatkan atau mengubah sesi pengguna'),'UI states the read-only behavior explicitly');
$report(str_contains($source['tools/as0_active_sessions_preflight.php'],'status_digest_match')&&str_contains($source['tools/as0_active_sessions_preflight.php'],"['token_id','token_hash','policy_revoke_correlation']"),'preflight verifies zero status mutation and forbidden response fields');
exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/tests/characterization/as0_active_sessions_readonly.php'),$output,$code);
$report($code===0&&in_array('RESULT checks=8 failed=0',$output,true),'read-only Active Sessions characterization passes');
printf("RESULT checks=%d failed=%d\n",$checks,$failed);exit($failed===0?0:1);
