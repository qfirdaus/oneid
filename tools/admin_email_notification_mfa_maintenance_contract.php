<?php

declare(strict_types=1);

if(PHP_SAPI!=='cli'){exit(2);}
$root=dirname(__DIR__);
$mfa=(string)file_get_contents($root.'/app/Admin/UserMfaTemporaryExemptionService.php');
$maintenance=(string)file_get_contents($root.'/app/Maintenance/MaintenanceDeveloperAccessService.php');
$pdoComposer=(string)file_get_contents($root.'/app/Notification/AdminEmailNotificationPdoComposer.php');
$boundary=(string)file_get_contents($root.'/lib/q_func.php');
$checks=[
 'MFA grant queues before transaction commit'=>str_contains($mfa,"'MFA_EXEMPTION_GRANTED'")
    &&strpos($mfa,"'MFA_EXEMPTION_GRANTED'")<strpos($mfa,'$this->pdo->commit()'),
 'MFA revoke queues before transaction commit'=>str_contains($mfa,"'MFA_EXEMPTION_REVOKED'")
    &&strrpos($mfa,"'MFA_EXEMPTION_REVOKED'")<strrpos($mfa,'$this->pdo->commit()'),
 'maintenance grant revoke and expiry have distinct events'=>str_contains($maintenance,"'MAINTENANCE_DEVELOPER_GRANTED'")
    &&str_contains($maintenance,"'MAINTENANCE_DEVELOPER_REVOKED'")&&str_contains($maintenance,"'MAINTENANCE_DEVELOPER_EXPIRED'"),
 'maintenance events are queued inside repository transaction'=>strpos($maintenance,"'MAINTENANCE_DEVELOPER_GRANTED'")<strpos($maintenance,"'status' => 1, 'code' => 'MAINTENANCE_ACCESS_GRANTED'")
    &&strpos($maintenance,"'MAINTENANCE_DEVELOPER_REVOKED'")<strpos($maintenance,"'status' => 1, 'code' => 'MAINTENANCE_ACCESS_REVOKED'"),
 'PDO composer provides bilingual copy for six lifecycle events'=>substr_count($pdoComposer,"'MFA_EXEMPTION_GRANTED'=>")===2
    &&substr_count($pdoComposer,"'MFA_EXEMPTION_REVOKED'=>")===2&&substr_count($pdoComposer,"'MFA_EXEMPTION_EXPIRED'=>")===2
    &&substr_count($pdoComposer,"'MAINTENANCE_DEVELOPER_GRANTED'=>")===2
    &&substr_count($pdoComposer,"'MAINTENANCE_DEVELOPER_REVOKED'=>")===2
    &&substr_count($pdoComposer,"'MAINTENANCE_DEVELOPER_EXPIRED'=>")===2,
 'HTTP boundary injects same-connection notification callback'=>substr_count($boundary,'oneid_admin_email_notification_callback($pdo)')>=2
    &&str_contains($boundary,'oneid_admin_email_notification_callback($maintenanceDeveloperPdo)'),
 'pilot routing remains centralized'=>str_contains($pdoComposer,"\$mode==='PILOT'")
    &&str_contains($pdoComposer,'ONEID_ADMIN_EMAIL_NOTIFICATION_PILOT_USER_ID'),
];
$failed=0;foreach($checks as $label=>$ok){printf("%s %s\n",$ok?'PASS':'FAIL',$label);$failed+=$ok?0:1;}
printf("RESULT checks=%d failed=%d\n",count($checks),$failed);exit($failed===0?0:1);
