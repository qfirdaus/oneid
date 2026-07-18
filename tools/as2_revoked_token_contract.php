<?php

if(PHP_SAPI!=='cli'){exit(2);}$root=dirname(__DIR__);$checks=0;$failed=0;$report=static function(bool $ok,string $label)use(&$checks,&$failed):void{$checks++;if(!$ok)$failed++;printf("%s %s\n",$ok?'PASS':'FAIL',$label);};
$files=['lib/auth_security.php','lib/request_security.php','lib/Database.php','lib/q_func.php','admin/dashboard.php','admin/user_list.php','page/dashboard.php','tests/characterization/as2_revoked_token_enforcement.php'];$source=[];
foreach($files as$file){$source[$file]=(string)file_get_contents($root.'/'.$file);exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($root.'/'.$file),$out,$code);$report($code===0,'PHP lint '.$file);$out=[];}
$guard=$source['lib/request_security.php'];$db=$source['lib/Database.php'];
$report(str_contains($db,'function is_specific_token_active')&&str_contains($db,'status=1')&&str_contains($db,'oneid_token_hash'),'token validation binds user, active status and hashed token');
$report(str_contains($guard,"\$matchedLevel !== 'public'")&&str_contains($guard,'oneid_authenticated_sso_token_is_active($operation)'),'every protected q_func action validates the active SSO token');
$report(str_contains($guard,"oneid_json_deny(401, 'SSO session token is no longer active')")&&str_contains($guard,'oneid_clear_local_authenticated_session'),'revoked AJAX token clears local state and returns 401');
$pageGuardCount=substr_count($source['admin/dashboard.php'].$source['admin/user_list.php'].$source['page/dashboard.php'],'oneid_require_active_sso_page($operation)');
$report($pageGuardCount===3,'all three protected page entries enforce the active SSO token');
$report(str_contains($source['lib/auth_security.php'],'session_regenerate_id(true)')&&str_contains($source['lib/auth_security.php'],'oneid_clear_sso_cookie'),'local invalidation clears the SSO cookie and rotates the PHP session');
exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/tests/characterization/as2_revoked_token_enforcement.php'),$out,$code);
$report($code===0&&in_array('RESULT checks=6 failed=0',$out,true),'multiple-browser revoked-token characterization passes');
printf("RESULT checks=%d failed=%d\n",$checks,$failed);exit($failed===0?0:1);
