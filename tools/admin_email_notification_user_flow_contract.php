<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
$root=dirname(__DIR__);
$files=[
 'dispatcher'=>(string)file_get_contents($root.'/app/Notification/AdminEmailNotificationDispatcher.php'),
 'composer'=>(string)file_get_contents($root.'/app/Notification/AdminEmailNotificationComposer.php'),
 'pdo'=>(string)file_get_contents($root.'/app/Notification/AdminEmailNotificationPdoComposer.php'),
 'password'=>(string)file_get_contents($root.'/app/User/UserPasswordChangeService.php'),
 'initial'=>(string)file_get_contents($root.'/app/User/InitialPasswordSetupService.php'),
 'manual'=>(string)file_get_contents($root.'/app/User/ManualUserCreator.php'),
 'profile'=>(string)file_get_contents($root.'/app/User/UserProfilePolicyService.php'),
 'route'=>(string)file_get_contents($root.'/lib/q_func.php'),
 'mydid'=>(string)file_get_contents($root.'/app/Auth/MyDigitalId/MyDigitalIdAccountLinkingService.php'),
];
$events=['ACCOUNT_CREATED','ACCOUNT_PROFILE_CHANGED','USER_PASSWORD_CHANGED','INITIAL_PASSWORD_SET','PASSWORD_RESET_COMPLETED','USER_MFA_TOTP_ENABLED','USER_MFA_PREFERENCE_CHANGED','USER_MFA_TOTP_REVOKED','MYDIGITALID_LINKED','LOGIN_SECURITY_WARNING'];
$checks=[];
foreach($events as $event)$checks[$event.' approved with bilingual copy']=str_contains($files['dispatcher'],"'$event'")&&substr_count($files['composer'],"'$event'=>")===2;
$checks['account lifecycle wired']=str_contains($files['manual'],"'ACCOUNT_CREATED'")&&str_contains($files['profile'],"'ACCOUNT_PROFILE_CHANGED'");
$checks['password lifecycle wired']=str_contains($files['password'],"'USER_PASSWORD_CHANGED'")&&str_contains($files['initial'],"'INITIAL_PASSWORD_SET'")&&substr_count($files['route'],"'PASSWORD_RESET_COMPLETED'")===2;
$checks['MFA lifecycle wired']=str_contains($files['route'],"'USER_MFA_TOTP_ENABLED'")&&str_contains($files['route'],"'USER_MFA_PREFERENCE_CHANGED'")&&str_contains($files['route'],"'USER_MFA_TOTP_REVOKED'");
$checks['MyDigitalID link uses transactional callback']=str_contains($files['mydid'],"'MYDIGITALID_LINKED'")&&substr_count($files['pdo'],"'MYDIGITALID_LINKED'=>")===2;
$checks['login warning only follows resolved active account']=str_contains($files['route'],"'LOGIN_SECURITY_WARNING'")&&str_contains($files['route'],"is_array(\$warningRecipient)")&&str_contains($files['route'],"['avail_status']??0)===1");
$failed=array_keys(array_filter($checks,static fn(bool $ok):bool=>!$ok));
foreach($checks as $name=>$ok)echo($ok?'PASS ':'FAIL ').$name.PHP_EOL;
echo 'RESULT checks='.count($checks).' failed='.count($failed).PHP_EOL;
exit($failed===[]?0:1);
