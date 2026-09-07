<?php
declare(strict_types=1);$root=dirname(__DIR__);$s=(string)file_get_contents($root.'/app/Admin/UserMfaLifecycleService.php');$w=(string)file_get_contents($root.'/tools/user_mfa_lifecycle_worker.php');$q=(string)file_get_contents($root.'/lib/q_func.php');$g=(string)file_get_contents($root.'/lib/request_security.php');$ui=(string)file_get_contents($root.'/public/assetsM/js/user-mfa-admin-policy.js');$css=(string)file_get_contents($root.'/public/dist/css/oneid-maintenance-admin.css');$runtime=(string)file_get_contents($root.'/config/runtime.php');$ms=require $root.'/config/locales/ms.php';$en=require $root.'/config/locales/en.php';$checks=[
'bounded_locked_worker'=>str_contains($s,'FOR UPDATE SKIP LOCKED')&&str_contains($w,'$processed<$limit')&&str_contains($w,'$limit>100'),
'worker_dormant_default'=>str_contains($runtime,"'ONEID_USER_MFA_LIFECYCLE_WORKER_ENABLED' => 'false'")&&str_contains($w,'USER_MFA_WORKER_DISABLED'),
'grace_cleanup_terminal'=>str_contains($s,"run_type='GRACE_REVOKE'")&&str_contains($s,"c.expires_at<=NOW(6)")&&str_contains($s,"c.otp_hash=CASE WHEN c.factor_type='EMAIL_OTP' THEN NULL"),
'auto_restore_version_bound'=>str_contains($s,"'AUTO_RESTORE'")&&str_contains($s,'applied_policy_version')&&str_contains($s,'USER_MFA_RESTORE_POLICY_STALE'),
'manual_restore_stepup'=>str_contains($q,'admin_restore_user_mfa_emergency_bypass')&&str_contains($g,"'admin_restore_user_mfa_emergency_bypass'")&&str_contains($s,"'RESTORE USER MFA NOW'"),
'restore_history_audit_run'=>str_contains($s,'user_login_mfa_policy_history')&&str_contains($s,'INSERT INTO syslog')&&str_contains($s,'user_mfa_policy_transition_runs'),
'warning_idempotency'=>str_contains($s,'BYPASS_WARNING_')&&str_contains($s,"\$threshold=(int)\$r['minutes_left']<=10?10:30"),
'weak_mode_banner'=>str_contains($ui,'user_mfa_weak_mode_banner')&&str_contains($ui,'admin.configuration.user_mfa_weakened')&&isset($ms['admin.configuration.user_mfa_weakened'],$en['admin.configuration.user_mfa_weakened']),
'manual_restore_ui'=>str_contains($ui,'Restore MFA now')&&str_contains($ui,'RESTORE USER MFA NOW'),
'manual_restore_fields_visible'=>str_contains($ui,'user_mfa_restore_reference')&&str_contains($ui,'user_mfa_restore_confirmation')&&str_contains($css,'.oneid-policy-restore input.form-control')&&str_contains($css,'display:block!important'),
'manual_restore_stepup_resume'=>str_contains($ui,'oneid_user_mfa_restore_workflow')&&str_contains($ui,"purpose=SECURITY_CONFIGURATION_CHANGE&return=configuration_user_mfa_security")&&str_contains($ui,'persistRestore(rd)'),
];$f=0;foreach($checks as$n=>$ok){echo($ok?'PASS ':'FAIL ').$n.PHP_EOL;if(!$ok)$f++;}echo'RESULT checks='.count($checks).' failed='.$f.PHP_EOL;exit($f?1:0);
