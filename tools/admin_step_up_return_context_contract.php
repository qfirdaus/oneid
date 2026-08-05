<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
$root=dirname(__DIR__);require_once $root.'/lib/config.php';require_once $root.'/app/Auth/AdminStepUpReturnContext.php';
$registry=\OneId\App\Auth\AdminStepUpReturnContext::registry();
$expected=['active_sessions','configuration_admin_2fa','configuration_account_recovery','configuration_locale','configuration_login_banner','configuration_user_mfa_security','configuration_user_mfa_category','configuration_user_mfa_exemption','admin_metadata'];sort($expected);$actual=array_keys($registry);sort($actual);
$dashboard=(string)file_get_contents($root.'/admin/dashboard.php');$step=(string)file_get_contents($root.'/page/admin_step_up.php');$global=(string)file_get_contents($root.'/public/assetsM/js/user-mfa-admin-policy.js');$category=(string)file_get_contents($root.'/public/assetsM/js/user-2fa-category-policy.js');$exemption=(string)file_get_contents($root.'/public/assetsM/js/user-2fa-temporary-exemption.js');$banner=(string)file_get_contents($root.'/public/assetsM/js/login-banner-admin.js');
$callers=$dashboard.$global.$category.$exemption;
$checks=[
 'registry_exact'=>$actual===$expected,
 'server_allowlist'=>str_contains($step,'AdminStepUpReturnContext::redirectUrl')&&\OneId\App\Auth\AdminStepUpReturnContext::normalize('unknown')===''&&\OneId\App\Auth\AdminStepUpReturnContext::redirectUrl('unknown')===\APP_URL.'/admin/dashboard',
 'callers_specific'=>str_contains($callers,'return=active_sessions')&&str_contains($callers,'return=configuration_admin_2fa')&&str_contains($callers,'return=configuration_account_recovery')&&str_contains($callers,'return=configuration_locale')&&str_contains($callers,'return=configuration_login_banner')&&str_contains($callers,'return=configuration_user_mfa_security')&&str_contains($callers,'return=configuration_user_mfa_category')&&str_contains($callers,'return=configuration_user_mfa_exemption')&&str_contains($callers,'return=admin_metadata'),
 'central_restorer'=>str_contains($dashboard,'restoreAdminStepUpReturnContext')&&str_contains($dashboard,'showStepUpReturnTab')&&str_contains($dashboard,"new CustomEvent('oneid:step-up-context-ready'"),
 'active_order'=>str_contains($dashboard,"if(context==='active_sessions'){get_all_user_activ_session(1,ready)")&&str_contains($dashboard,"event.detail.context!=='active_sessions'")&&!str_contains($dashboard,"$(function(){var pending=sessionStorage.getItem('oneid_as3_pending_target')"),
 'hidden_resume_gated'=>str_contains($global,"oneidStepUpContextReady !== 'configuration_user_mfa_security'")&&str_contains($category,"oneidStepUpContextReady !== 'configuration_user_mfa_category'")&&str_contains($exemption,"oneidStepUpContextReady === 'configuration_user_mfa_exemption'")&&str_contains($dashboard,"canResumeAdmin2fa=window.oneidStepUpContextReady==='configuration_admin_2fa'"),
 'login_banner_central'=>!str_contains($banner,'return=login_banner')&&!str_contains($banner,"location.hash==='#configuration_login_banner'"),
];
$failed=[];foreach($checks as$key=>$pass){echo($pass?'PASS ':'FAIL ').$key."\n";if(!$pass)$failed[]=$key;}printf("RETURN_CONTEXT checks=%d passed=%d\n",count($checks),count($checks)-count($failed));exit($failed?1:0);
