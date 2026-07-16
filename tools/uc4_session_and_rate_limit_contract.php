<?php
declare(strict_types=1);$root=dirname(__DIR__);$q=(string)file_get_contents($root.'/lib/q_func.php');$db=(string)file_get_contents($root.'/lib/Database.php');$ui=(string)file_get_contents($root.'/page/dashboard.php');$service=(string)file_get_contents($root.'/app/User/UserPasswordChangeService.php');$n=0;$f=0;$ok=function($v,$d)use(&$n,&$f){$n++;if(!$v)$f++;printf("%s: %s\n",$v?'PASS':'FAIL',$d);};
$ok(str_contains($db,'count_recent_invalid_current_password_attempts')&&str_contains($q,'>=5')&&str_contains($q,"'UC4_RATE_LIMITED'"),'five invalid current-password attempts in 15 minutes are rate limited');
$ok(str_contains($q,'session_regenerate_id(true)')&&str_contains($q,"unset(\$_SESSION['oneid_csrf_token'])")&&str_contains($q,"'csrf_token'") ,'voluntary success rotates PHP session and CSRF');
$ok(str_contains($ui,'response.csrf_token')&&str_contains($ui,"'X-CSRF-Token':response.csrf_token"),'browser adopts the rotated CSRF token');
$ok(str_contains($service,'$keepCurrentSession')&&str_contains($q,'!$wasForced'),'forced change does not create a replacement SSO token');
$ok(str_contains($q,'oneid_clear_sso_cookie()')&&str_contains($q,"\$_SESSION=[]")&&str_contains($ui,'response.reauthentication_required'),'forced change clears authentication and redirects to login');
printf("RESULT: checks=%d failures=%d\n",$n,$f);exit($f===0?0:1);
