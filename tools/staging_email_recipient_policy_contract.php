<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
$root=dirname(__DIR__);
$router=(string)file_get_contents($root.'/app/Mail/OneIdInformationalEmailRouter.php');
$recovery=(string)file_get_contents($root.'/app/Auth/UserMfa/UserMfaRecoveryEmailSender.php');
$q=(string)file_get_contents($root.'/lib/q_func.php');
$checks=[
 'informational routing is gated by PILOT mode'=>str_contains($router,"oneid_admin_email_notification_delivery_mode()!=='PILOT'"),
 'pilot recipient is resolved server-side by configured ID'=>str_contains($router,"ONEID_ADMIN_EMAIL_NOTIFICATION_PILOT_USER_ID")&&str_contains($router,'FROM user_tbl'),
 'invalid pilot fails closed'=>substr_count($router,'INFORMATIONAL_EMAIL_PILOT_INVALID')===2,
 'recovery OTP remains addressed to user'=>str_contains($recovery,"),\n            true")&&str_contains($recovery,'if (!$requiresVerification)'),
 'recovery revocation notice is routed as informational'=>str_contains($recovery,"),\n            false"),
 'legacy password OTP stays direct while delivery test is routed'=>str_contains($q,'if($isTest){$r=\\OneId\\App\\Mail\\OneIdInformationalEmailRouter::route'),
 'pilot informational subjects are visibly labelled'=>str_contains($recovery,'oneid_admin_email_notification_pilot_prefix()')&&str_contains($q,'oneid_admin_email_notification_pilot_prefix()'),
];
$failed=[];foreach($checks as $name=>$ok){echo($ok?'PASS ':'FAIL ').$name.PHP_EOL;if(!$ok)$failed[]=$name;}
echo 'RESULT checks='.count($checks).' failed='.count($failed).PHP_EOL;exit($failed===[]?0:1);
