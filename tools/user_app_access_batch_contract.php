<?php
$root=dirname(__DIR__);$files=['service'=>file_get_contents($root.'/app/User/UserAppAccessBatchService.php'),'db'=>file_get_contents($root.'/lib/Database.php'),'security'=>file_get_contents($root.'/lib/request_security.php'),'endpoint'=>file_get_contents($root.'/lib/q_func.php'),'ui'=>file_get_contents($root.'/admin/dashboard.php'),'css'=>file_get_contents($root.'/public/dist/css/oneid-user-profile-access.css')];
$checks=[
 'read and batch actions are admin guarded'=>str_contains($files['security'],"'admin_get_user_app_access_state'")&&str_contains($files['security'],"'admin_apply_user_app_access'"),
 'state distinguishes inherited direct and denied'=>str_contains($files['db'],'category_allow')&&str_contains($files['db'],'direct_allow')&&str_contains($files['db'],'denied'),
 'apply locks user and all ACL inputs'=>str_contains($files['service'],'admin_get_user_for_profile_action($userId, true)')&&str_contains($files['service'],'admin_get_user_direct_acl_ids($userId, true)')&&str_contains($files['service'],'admin_get_user_blacklist_rows($userId, true)')&&str_contains($files['service'],'admin_get_category_acl_sp_ids($categoryId, true)'),
 'server calculates authoritative four-way mutation'=>str_contains($files['service'],'$addDirect = $removeDirect = $addDeny = $removeDeny')&&str_contains($files['service'],'USER_ACL_STATE_CHANGED'),
 'other-user sessions revoke after successful mutations'=>substr_count($files['service'],'update_whole_token_status(')===1,
 'self update preserves only current administrator session'=>str_contains($files['service'],'update_other_token_status($userId, $currentToken')&&str_contains($files['db'],'public function update_other_token_status(')&&str_contains($files['endpoint'],'oneid_sso_cookie_token()'),
 'mandatory audit precedes commit'=>strpos($files['service'],'USER_ACL_AUDIT_NOT_WRITTEN')<strpos($files['service'],'->commit()'),
 'UI has profile and application access tabs'=>str_contains($files['ui'],'data-user-profile-tab="profile"')&&str_contains($files['ui'],'data-user-profile-tab="access"'),
 'UI provides search filters review and one batch save'=>str_contains($files['ui'],'user_access_search')&&str_contains($files['ui'],'data-user-access-filter="inherited"')&&str_contains($files['ui'],'userAccessReview')&&str_contains($files['ui'],'admin_apply_user_app_access'),
 'UI explains ACL source'=>str_contains($files['ui'],"a.denied?'Denied':(a.inherited?'Inherited':(a.direct?'Direct':'Available'))"),
 'unsaved profile and ACL changes warn on close'=>str_contains($files['ui'],'profileDirty')&&str_contains($files['ui'],'Discard pending changes?'),
 'mobile modal is full screen and access list bounded'=>str_contains($files['css'],'@media(max-width:767px)')&&str_contains($files['css'],'width:100%;height:100%;margin:0')&&str_contains($files['css'],'height:calc(100vh - 330px)'),
];$failed=0;foreach($checks as $n=>$pass){echo($pass?'PASS ':'FAIL ').$n."\n";if(!$pass)$failed++;}$cmd=escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/tests/characterization/user_app_access_batch.php');passthru($cmd,$status);if($status)$failed++;echo 'RESULT checks='.count($checks).' failed='.$failed."\n";exit($failed?1:0);
