<?php

declare(strict_types=1);

$root=dirname(__DIR__);
$service=(string)file_get_contents($root.'/app/Admin/WebAppService.php');
$upload=(string)file_get_contents($root.'/lib/upload_security.php');
$database=(string)file_get_contents($root.'/lib/Database.php');
$stagingNginx=(string)file_get_contents($root.'/deployment/nginx/oneid-staging.conf');
$productionNginx=(string)file_get_contents($root.'/deployment/nginx/oneid-production.conf');
$checks=[
    'upload has non-public staging operation'=>str_contains($upload,'function stage_app_icon_upload')&&str_contains($service,"/storage/runtime/app-icon-staging"),
    'staged file uses restrictive permissions'=>str_contains($upload,'chmod($stagedPath, 0600)'),
    'publish uses rename and collision refusal'=>str_contains($upload,'file_exists($target) || !rename($stagedPath, $target)'),
    'create begins and commits transaction'=>substr_count($service,'$this->operation->beginTransaction();')>=3&&str_contains($service,"'code'=>'WA4_APP_CREATED_ENVIRONMENT_ASSET'"),
    'update emits atomic success code'=>str_contains($service,"'code'=>'WA4_APP_UPDATED_ENVIRONMENT_ASSET'"),
    'category is locked before mutation'=>substr_count($service,'admin_get_app_category_for_update')>=2,
    'edit target is locked with full image state'=>str_contains($service,'admin_get_service_provider_for_update')&&str_contains($database,'sp_description,sp_domain,sp_image,avail_status'),
    'successful create audit is mandatory'=>str_contains($service,'syslog_record(13')&&substr_count($service,"WA3_AUDIT_NOT_WRITTEN")>=2,
    'successful edit audit is mandatory'=>str_contains($service,'syslog_record(14'),
    'upload rejection prevents partial mutation'=>substr_count($service,"throw new WebAppManagementException('WA3_ICON_REJECTED'")===2,
    'compensation rolls back and removes staged/published file'=>str_contains($service,'private function compensate')&&str_contains($service,'discard_staged_app_icon($staged)')&&str_contains($service,'unlink($publishedPath)'),
    'legacy save helper is no longer called by service'=>!str_contains($service,'save_app_icon_upload('),
    'deployment request limit exceeds five megabyte icon policy'=>str_contains($stagingNginx,'client_max_body_size 6m;')&&str_contains($productionNginx,'client_max_body_size 6m;'),
];
$passed=0;
foreach($checks as $label=>$ok){echo($ok?'PASS':'FAIL').' '.$label."\n";$passed+=$ok?1:0;}
printf("RESULT %d/%d\n",$passed,count($checks));
exit($passed===count($checks)?0:1);
