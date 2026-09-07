<?php

$root=dirname(__DIR__);
$ms=require $root.'/config/locales/ms.php';
$en=require $root.'/config/locales/en.php';
$dashboard=(string)file_get_contents($root.'/admin/dashboard.php');
$policy=(string)file_get_contents($root.'/public/assetsM/js/user-mfa-admin-policy.js');
$keys=[
    'admin.configuration.user_mfa_mode_label',
    'admin.configuration.user_mfa_pending_label',
    'admin.configuration.user_mfa_hint_enforced_body',
    'admin.configuration.user_mfa_hint_bypass_body',
    'admin.configuration.user_mfa_status',
    'admin.configuration.user_mfa_review_title',
    'admin.configuration.duration_30_minutes',
    'admin.configuration.duration_hours',
    'admin.configuration.duration_hours_maximum',
    'admin.configuration.sso_timeout_change',
    'admin.configuration.sso_multi_change',
    'admin.configuration.calculating_impact',
    'admin.configuration.affected_users',
    'admin.configuration.saving_policy_notice',
];
$checks=[];
$checks['BM and English locale catalogues retain exact key parity']=array_keys($ms)===array_keys($en);
foreach($keys as $key){$checks['translation exists in both catalogues: '.$key]=isset($ms[$key],$en[$key])&&$ms[$key]!==$en[$key];}
$checks['Authentication duration choices are locale-backed']=!str_contains($dashboard,'<option value="1">1 jam</option>')&&str_contains($dashboard,"oneid_translate('admin.configuration.duration_1_hour')");
$checks['User MFA mode and transition controls are locale-backed']=!str_contains($dashboard,'>Mode operasi User MFA</label>')&&!str_contains($dashboard,'>Immediate revoke</option>');
$checks['temporary exemption durations no longer show two languages together']=!str_contains($dashboard,'jam / hour');
$checks['User MFA dynamic guidance reads the shared locale catalogue']=str_contains($policy,"function t(key,parameters)")&&str_contains($policy,"t('admin.configuration.user_mfa_status'")&&str_contains($policy,'t(h.title)');
$checks['Authentication preview and result copy reads the shared locale catalogue']=str_contains($dashboard,"adminText('admin.configuration.affected_users'")&&str_contains($dashboard,"adminText('admin.configuration.saving_policy_notice')");
$failed=0;
foreach($checks as $description=>$passed){echo ($passed?'PASS ':'FAIL ').$description.PHP_EOL;if(!$passed)$failed++;}
echo 'RESULT checks='.count($checks).' failed='.$failed.PHP_EOL;
exit($failed===0?0:1);
