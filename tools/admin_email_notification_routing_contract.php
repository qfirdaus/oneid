<?php

declare(strict_types=1);

if(PHP_SAPI!=='cli'){exit(2);}
require_once dirname(__DIR__).'/config/runtime.php';

$keys=['ONEID_ADMIN_EMAIL_NOTIFICATION_ENABLED','ONEID_ADMIN_EMAIL_NOTIFICATION_DELIVERY_MODE',
    'ONEID_ADMIN_EMAIL_NOTIFICATION_PILOT_USER_ID','ONEID_ADMIN_EMAIL_NOTIFICATION_LIVE_APPROVED'];
$set=static function(array $values)use($keys):void{
    $baseline=['ONEID_ADMIN_EMAIL_NOTIFICATION_ENABLED'=>'false','ONEID_ADMIN_EMAIL_NOTIFICATION_DELIVERY_MODE'=>'OFF',
        'ONEID_ADMIN_EMAIL_NOTIFICATION_PILOT_USER_ID'=>'#','ONEID_ADMIN_EMAIL_NOTIFICATION_LIVE_APPROVED'=>'false'];
    foreach($keys as $key)putenv($key.'='.(string)($values[$key]??$baseline[$key]));
};
$checks=[];
$set(['ONEID_ADMIN_EMAIL_NOTIFICATION_ENABLED'=>'false','ONEID_ADMIN_EMAIL_NOTIFICATION_DELIVERY_MODE'=>'PILOT','ONEID_ADMIN_EMAIL_NOTIFICATION_PILOT_USER_ID'=>'pilot']);
$checks['master switch always wins']=oneid_admin_email_notification_delivery_mode()==='OFF';
$set(['ONEID_ADMIN_EMAIL_NOTIFICATION_ENABLED'=>'true','ONEID_ADMIN_EMAIL_NOTIFICATION_DELIVERY_MODE'=>'PILOT']);
$checks['pilot without exact account fails closed']=oneid_admin_email_notification_delivery_mode()==='OFF';
$set(['ONEID_ADMIN_EMAIL_NOTIFICATION_ENABLED'=>'true','ONEID_ADMIN_EMAIL_NOTIFICATION_DELIVERY_MODE'=>'PILOT','ONEID_ADMIN_EMAIL_NOTIFICATION_PILOT_USER_ID'=>'820705025923']);
$checks['approved pilot mode is accepted']=oneid_admin_email_notification_delivery_mode()==='PILOT';
$set(['ONEID_ADMIN_EMAIL_NOTIFICATION_ENABLED'=>'true','ONEID_ADMIN_EMAIL_NOTIFICATION_DELIVERY_MODE'=>'LIVE','ONEID_ADMIN_EMAIL_NOTIFICATION_LIVE_APPROVED'=>'false']);
$checks['live without separate approval fails closed']=oneid_admin_email_notification_delivery_mode()==='OFF';
$set(['ONEID_ADMIN_EMAIL_NOTIFICATION_ENABLED'=>'true','ONEID_ADMIN_EMAIL_NOTIFICATION_DELIVERY_MODE'=>'LIVE','ONEID_ADMIN_EMAIL_NOTIFICATION_LIVE_APPROVED'=>'true']);
$checks['live with separate approval is accepted']=oneid_admin_email_notification_delivery_mode()==='LIVE';
$set(['ONEID_ADMIN_EMAIL_NOTIFICATION_ENABLED'=>'true','ONEID_ADMIN_EMAIL_NOTIFICATION_DELIVERY_MODE'=>'UNKNOWN']);
$checks['unknown mode fails closed']=oneid_admin_email_notification_delivery_mode()==='OFF';
foreach($keys as $key)putenv($key);
$worker=(string)file_get_contents(dirname(__DIR__).'/tools/admin_email_notification_worker.php');
$checks['worker suppresses mismatched pilot recipient']=str_contains($worker,'PILOT_RECIPIENT_MISMATCH')
    &&str_contains($worker,"'SUPPRESSED'")&&str_contains($worker,'hash_equals');
$dispatcher=(string)file_get_contents(dirname(__DIR__).'/app/Notification/AdminEmailNotificationDispatcher.php');
$checks['dispatcher overwrites supplied recipient in pilot mode']=str_contains($dispatcher,"\$mode==='PILOT'")
    &&str_contains($dispatcher,'$recipientEmail=(string)$pilot[\'data5\']');

$failed=0;foreach($checks as $label=>$ok){printf("%s %s\n",$ok?'PASS':'FAIL',$label);$failed+=$ok?0:1;}
printf("RESULT checks=%d failed=%d\n",count($checks),$failed);exit($failed===0?0:1);
