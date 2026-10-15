<?php

$root=dirname(__DIR__);
$files=[
 'service'=>file_get_contents($root.'/app/Admin/UserCategoryAppAccessService.php'),
 'database'=>file_get_contents($root.'/lib/Database.php'),
 'security'=>file_get_contents($root.'/lib/request_security.php'),
 'endpoint'=>file_get_contents($root.'/lib/q_func.php'),
 'ui'=>file_get_contents($root.'/admin/dashboard.php'),
 'css'=>file_get_contents($root.'/public/dist/css/oneid-category-app-access.css'),
];
$checks=[
 'new read and batch actions are admin guarded'=>str_contains($files['security'],"'admin_get_category_app_access_state'")&&str_contains($files['security'],"'admin_apply_category_app_access'"),
 'batch service locks category and assignments'=>str_contains($files['service'],'admin_get_user_category_for_acl_action($categoryId, true)')&&str_contains($files['service'],'admin_get_category_acl_sp_ids($categoryId, true)'),
 'batch service validates additions as active'=>str_contains($files['service'],'admin_get_active_service_provider_ids($toAdd)'),
 'batch mutation is transactional and audit mandatory'=>str_contains($files['service'],'beginTransaction()')&&str_contains($files['service'],'CATEGORY_ACL_AUDIT_NOT_WRITTEN')&&str_contains($files['service'],'rollback()'),
 'endpoint decodes only JSON array selection'=>str_contains($files['endpoint'],'JSON_THROW_ON_ERROR')&&str_contains($files['endpoint'],'!is_array($decoded)'),
 'UI uses one searchable selectable application list'=>str_contains($files['ui'],'category_access_search')&&str_contains($files['ui'],'category-access-checkbox'),
 'UI provides review and single save action'=>str_contains($files['ui'],'categoryAccessReview')&&str_contains($files['ui'],'admin_apply_category_app_access'),
 'UI warns before discarding pending changes'=>str_contains($files['ui'],'Discard pending changes?'),
 'mobile layout is explicitly responsive'=>str_contains($files['css'],'@media(max-width:767px)')&&str_contains($files['css'],'height:calc(100vh - 294px)'),
];
$failed=0;foreach($checks as $name=>$pass){echo ($pass?'PASS ':'FAIL ').$name."\n";if(!$pass)$failed++;}
$cmd=escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/tests/characterization/category_app_access_batch.php');passthru($cmd,$fixtureStatus);
if($fixtureStatus!==0)$failed++;
echo 'RESULT checks='.count($checks).' failed='.$failed."\n";exit($failed===0?0:1);
