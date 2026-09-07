<?php
declare(strict_types=1);
$root=dirname(__DIR__);$service=(string)file_get_contents($root.'/app/Admin/UserMfaPolicyWorkflowService.php');$route=(string)file_get_contents($root.'/lib/q_func.php');$security=(string)file_get_contents($root.'/lib/request_security.php');$ui=(string)file_get_contents($root.'/admin/dashboard.php');$js=(string)file_get_contents($root.'/public/assetsM/js/user-mfa-admin-policy.js');
$checks=[
'all_modes_exposed'=>str_contains($ui,'value="ENFORCED"')&&str_contains($ui,'value="PILOT_ENFORCED"')&&str_contains($ui,'value="ENROLLMENT"')&&str_contains($ui,'value="EMERGENCY_BYPASS"')&&str_contains($ui,'value="OFF"'),
'bounded_bypass_options'=>str_contains($ui,'value="30"')&&str_contains($ui,'value="480"')&&!str_contains($ui,'value="1440"'),
'grace_and_immediate'=>str_contains($service,"['GRACE','IMMEDIATE']")&&str_contains($service,'DATE_ADD(NOW(6),INTERVAL 5 MINUTE) AS grace_time_value')&&str_contains($service,"transition_strategy']==='IMMEDIATE'"),
'grace_caps_existing_work'=>str_contains($service,'SET c.expires_at=LEAST(c.expires_at,:grace)')&&str_contains($service,'SET expires_at=LEAST(expires_at,:grace)'),
'immediate_revokes_otp_material'=>str_contains($service,"c.otp_hash=CASE WHEN c.factor_type='EMAIL_OTP' THEN NULL")&&str_contains($service,"transaction_status='REVOKED'"),
'optimistic_versioning'=>str_contains($service,'expected_policy_version')&&str_contains($service,'configuration_version=:next')&&str_contains($service,'USER_MFA_WORKFLOW_POLICY_STALE'),
'production_maker_checker'=>str_contains($service,"environment === 'production'")&&str_contains($service,'USER_MFA_APPROVAL_SELF_REJECTED')&&str_contains($service,"'PENDING_APPROVAL'"),
'approval_digest_bound'=>str_contains($service,'request_payload_digest')&&str_contains($service,"hash('sha256'")&&str_contains($service,'expected_policy_version'),
'single_open_request'=>str_contains($service,'USER_MFA_WORKFLOW_OPEN_REQUEST_EXISTS')&&str_contains($service,"requested_mode']!=='EMERGENCY_BYPASS'"),
'audit_and_history_atomic'=>str_contains($service,'user_login_mfa_policy_history')&&str_contains($service,'INSERT INTO syslog')&&str_contains($service,'beginTransaction()'),
'server_routes_guarded'=>str_contains($route,'admin_get_user_mfa_policy_workflow')&&str_contains($route,'admin_request_user_mfa_policy_change')&&str_contains($route,'admin_decide_user_mfa_policy_change')&&str_contains($security,"'admin_request_user_mfa_policy_change'")&&str_contains($security,"'admin_decide_user_mfa_policy_change'"),
'legacy_mutation_closed'=>!str_contains($security,"'admin_update_user_mfa_global_policy'")&&!str_contains($route,"isset(\$_POST['admin_update_user_mfa_global_policy'])"),
'stepup_draft_resume'=>str_contains($js,'oneid_user_mfa_workflow')&&str_contains($js,"configuration_user_mfa_security")&&str_contains($js,'state.resume=true')&&str_contains($js,'!state.resumeScheduled')&&str_contains($js,'state.resumeScheduled=true;clear()'),
'professional_review'=>str_contains($js,'oneid-policy-review')&&str_contains($js,'oneidApplyProfessionalAlertLayout'),
];$failed=0;foreach($checks as$n=>$ok){echo($ok?'PASS ':'FAIL ').$n.PHP_EOL;if(!$ok)$failed++;}echo 'RESULT checks='.count($checks).' failed='.$failed.PHP_EOL;exit($failed?1:0);
