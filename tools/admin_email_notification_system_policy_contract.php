<?php

declare(strict_types=1);

if(PHP_SAPI!=='cli'){exit(2);}
$root=dirname(__DIR__);$files=[
 'maintenance'=>(string)file_get_contents($root.'/app/Admin/MaintenanceConfigurationService.php'),
 'sso'=>(string)file_get_contents($root.'/app/Admin/SsoConfigurationService.php'),
 'recovery'=>(string)file_get_contents($root.'/app/Admin/PasswordRecoveryConfigurationService.php'),
 'global'=>(string)file_get_contents($root.'/app/Admin/UserMfaGlobalPolicyService.php'),
 'category'=>(string)file_get_contents($root.'/app/Admin/UserMfaCategoryPolicyService.php'),
 'boundary'=>(string)file_get_contents($root.'/lib/q_func.php'),
];
$checks=[
 'maintenance change queues before commit'=>str_contains($files['maintenance'],"'MAINTENANCE_CHANGED'")&&strpos($files['maintenance'],"'MAINTENANCE_CHANGED'")<strrpos($files['maintenance'],'commit()'),
 'SSO policy change queues before commit'=>str_contains($files['sso'],"'SECURITY_POLICY_CHANGED'")&&strpos($files['sso'],"'SECURITY_POLICY_CHANGED'")<strrpos($files['sso'],'commit()'),
 'password recovery change queues before commit'=>str_contains($files['recovery'],"'SECURITY_POLICY_CHANGED'")&&strpos($files['recovery'],"'SECURITY_POLICY_CHANGED'")<strrpos($files['recovery'],'commit()'),
 'global MFA policy change queues before commit'=>str_contains($files['global'],"'SECURITY_POLICY_CHANGED'")&&strpos($files['global'],"'SECURITY_POLICY_CHANGED'")<strrpos($files['global'],'commit()'),
 'category MFA policy change queues before commit'=>str_contains($files['category'],"'SECURITY_POLICY_CHANGED'")&&strpos($files['category'],"'SECURITY_POLICY_CHANGED'")<strrpos($files['category'],'commit()'),
 'HTTP mutations inject transactional callbacks'=>substr_count($files['boundary'],'oneid_admin_email_notification_operation_callback($operation)')>=3
    &&substr_count($files['boundary'],'oneid_admin_email_notification_callback($pdo)')>=4,
 'unchanged policy paths do not queue'=>strpos($files['sso'],'SC2_CONFIG_UNCHANGED')<strpos($files['sso'],"'SECURITY_POLICY_CHANGED'")
    &&strpos($files['recovery'],'SC6_RECOVERY_UNCHANGED')<strpos($files['recovery'],"'SECURITY_POLICY_CHANGED'"),
];
$failed=0;foreach($checks as $label=>$ok){printf("%s %s\n",$ok?'PASS':'FAIL',$label);$failed+=$ok?0:1;}printf("RESULT checks=%d failed=%d\n",count($checks),$failed);exit($failed===0?0:1);
