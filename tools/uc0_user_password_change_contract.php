<?php
declare(strict_types=1);
$root=dirname(__DIR__);$ui=(string)file_get_contents($root.'/page/dashboard.php');$q=(string)file_get_contents($root.'/lib/q_func.php');$db=(string)file_get_contents($root.'/lib/Database.php');$guard=(string)file_get_contents($root.'/lib/request_security.php');$auth=(string)file_get_contents($root.'/lib/auth_security.php');$session=(string)file_get_contents($root.'/lib/session_security.php');$service=(string)file_get_contents($root.'/app/User/UserPasswordChangeService.php');
$n=0;$f=0;$check=function(bool $ok,string $d)use(&$n,&$f){$n++;if(!$ok)$f++;printf("%s: %s\n",$ok?'PASS':'FAIL',$d);};
$check(str_contains($guard,"'check_default_password'")&&str_contains($guard,"'action_change_password'")&&str_contains($guard,'oneid_require_csrf();'),'password actions are authenticated-user and CSRF guarded');
$check(str_contains($ui,"data: {check_default_password:\"\"}")&&str_contains($ui,"$('#modal_change_first_time_password').modal('show')"),'forced-change baseline is driven by a frontend modal');
$check(str_contains($service,'oneid_password_verify($current,$stored)')&&str_contains($q,'UserPasswordChangeService'),'backend service verifies current password for the session user');
$check(str_contains($auth,'strlen($password) < 12')&&str_contains($auth,"preg_match('/[A-Z]/'")&&str_contains($q,'oneid_validate_new_password'),'12-character composition policy is enforced server-side');
$check(str_contains($db,'oneid_password_hash($password)')&&str_contains($db,'password_hash($password, PASSWORD_DEFAULT)')===false,'database writer delegates to centralized modern password hash');
$check(str_contains($service,'update_whole_token_status')&&str_contains($service,'add_new_token')&&str_contains($q,'oneid_set_sso_cookie'),'success revokes tokens and creates a browser replacement when allowed');
$check(str_contains($q,'syslog_record(20')&&str_contains($service,'syslog_record(21'),'attempt and success audit events are present');
$changeStart=strpos($q,"if(isset( \$_POST['action_change_password']))");$changeEnd=strpos($q,'return;',($changeStart?:0));$block=$changeStart!==false?substr($q,$changeStart,($changeEnd?:strlen($q))-$changeStart):'';
$check(str_contains($service,'beginTransaction')&&str_contains($service,'commit()'),'password change workflow is now atomic');
$check(str_contains($q,'session_regenerate_id(true)')&&str_contains($q,"unset(\$_SESSION['oneid_csrf_token'])"),'success rotates PHP session and CSRF');
$check(str_contains($service,'otp_invalidate_active'),'success invalidates active recovery OTP');
$check(!str_contains($db,'public function action_change_password($user_id,$password)'),'legacy direct password writer has been retired');
$check(str_contains($session,"\$_SESSION['password_change_required']")&&str_contains($guard,'UC3_PASSWORD_CHANGE_REQUIRED'),'forced-change flag is session-visible and backend-enforced');
printf("RESULT: checks=%d failures=%d\n",$n,$f);exit($f===0?0:1);
