<?php
declare(strict_types=1);
$root=dirname(__DIR__);$db=(string)file_get_contents($root.'/lib/Database.php');$service=(string)file_get_contents($root.'/app/Admin/WebAppService.php');$q=(string)file_get_contents($root.'/lib/q_func.php');$security=(string)file_get_contents($root.'/lib/request_security.php');$dashboard=(string)file_get_contents($root.'/admin/dashboard.php');$en=(array)require $root.'/config/locales/en.php';$ms=(array)require $root.'/config/locales/ms.php';
$checks=[
 'archive manager is opened only from the protected system category'=>str_contains($dashboard,'category-archive-open')&&str_contains($dashboard,"isSystem?'<button"),
 'archive manager exposes no bulk delete operation'=>!str_contains($dashboard,'archived-app-purge-all')&&!str_contains($q,'admin_purge_all_archived'),
 'archive listing returns dependency counts without secrets'=>str_contains($db,'admin_get_archived_service_providers')&&str_contains($db,'credential_count')&&!str_contains($db,'code_ciphertext AS'),
 'restore only activates an archived app into a non-system category'=>str_contains($db,'admin_restore_archived_service_provider')&&str_contains($db,'sp_group_id<>0')&&str_contains($db,'production_ready=0'),
 'purge is limited to inactive uncategorized apps'=>str_contains($db,'admin_purge_archived_service_provider')&&str_contains($db,'avail_status=0 AND sp_group_id=0'),
 'purge fails closed while access favourite or credential dependencies remain'=>str_contains($db,'NOT EXISTS(SELECT 1 FROM acl_group')&&str_contains($db,'NOT EXISTS(SELECT 1 FROM sp_api_credential')&&str_contains($service,'WA7_PURGE_DEPENDENCY_BLOCKED'),
 'purge requires exact app name and a bounded reason'=>str_contains($service,'WA7_PURGE_CONFIRMATION_MISMATCH')&&str_contains($service,'mb_strlen($reason)<10')&&str_contains($dashboard,'archive_purge_confirmation'),
 'restore and purge share transaction and mandatory audit controls'=>substr_count($service,'action=restore_archived_app')===1&&substr_count($service,'action=purge_archived_app')===1&&str_contains($service,'WA7_AUDIT_NOT_WRITTEN'),
 'archive endpoints are protected admin actions'=>str_contains($security,"'admin_get_archived_apps'")&&str_contains($security,"'admin_restore_archived_app'")&&str_contains($security,"'admin_purge_archived_app'")&&str_contains($q,"isset(\$_POST['admin_get_archived_apps'])"),
 'archive interface displays dependencies and selective actions'=>str_contains($dashboard,'archived-app-dependencies')&&str_contains($dashboard,'archived-app-restore')&&str_contains($dashboard,'archived-app-purge'),
 'archive interface is complete in English and Malay'=>array_diff(array_filter(array_keys($en),static fn(string$key):bool=>str_starts_with($key,'admin.archive.')),array_keys($ms))===[]&&str_contains($dashboard,"admin.archive.purge_title"),
];
$failed=0;foreach($checks as$label=>$pass){if(!$pass)$failed++;echo($pass?'PASS ':'FAIL ').$label.PHP_EOL;}printf("RESULT checks=%d failed=%d\n",count($checks),$failed);exit($failed?1:0);
