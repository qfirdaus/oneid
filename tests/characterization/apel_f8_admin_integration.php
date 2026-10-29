<?php
declare(strict_types=1);$root=dirname(__DIR__,2);require_once $root.'/lib/config.php';require_once $root.'/bootstrap/sync_runtime.php';
use OneId\App\Sync\Apel\{ApelOperationalConfig,ApelStudentSource};use OneId\App\Sync\SyncSourceScope;
$n=0;$f=0;$r=function(bool$o,string$l)use(&$n,&$f){$n++;$f+=$o?0:1;echo($o?'PASS ':'FAIL '),$l,"\n";};
$scope=SyncSourceScope::fromCode(ApelStudentSource::SOURCE_CODE);$r($scope->sourceCode==='STUDENT_APEL'&&$scope->categoryIds===[10]&&$scope->provenanceEnforced,'APEL source is category-10 and provenance scoped');
$r($scope->preserveExistingEmailOnBlank,'APEL preserves an existing email when upstream is blank');
$cronScope=SyncSourceScope::fromCodeForCron('STUDENT_APEL');$r($cronScope->sourceCode==='STUDENT_APEL','APEL cron requires and accepts its private Apply gate');
$gate=ApelOperationalConfig::fromPrivateRuntime();$r($gate->previewEnabled&&!$gate->canApply(),'runtime enables preview but every Apply path remains disabled');
$ui=file_get_contents($root.'/admin/dashboard.php');$r(str_contains($ui,"pick_preview_sync_user('STUDENT_APEL')")&&str_contains($ui,'STUDENT_APEL: externalSyncText.apel'),'Admin Sync exposes APEL preview card and label');
$endpoint=file_get_contents($root.'/lib/q_func.php');$r(str_contains($endpoint,'$isApelOperational')&&str_contains($endpoint,'APEL_OPERATIONAL_APPLY_DISABLED'),'HTTP boundary has an APEL-specific Apply gate');
$r(str_contains($endpoint,"\$aggregate['source_rows'][\$apelSource]")&&str_contains($ui,'external_summary_apel_rows')&&str_contains($ui,"code: 'STUDENT_APEL', row: 'apel'"),'read-only Summary aggregates and renders APEL');
$cron=file_get_contents($root.'/app/Sync/SyncCronConfig.php');$r(str_contains($cron,'ApelStudentSource')&&str_contains($cron,"\$source !== 'STUDENT_APEL'"),'cron includes APEL but never bypasses its conservative limits');
printf("RESULT checks=%d failures=%d\n",$n,$f);exit($f===0?0:1);
