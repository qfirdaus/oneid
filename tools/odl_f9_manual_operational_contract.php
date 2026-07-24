<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
$root=dirname(__DIR__);$checks=0;$failed=0;
$r=static function(bool$ok,string$label)use(&$checks,&$failed):void{
 $checks++;$failed+=$ok?0:1;printf("%s %s\n",$ok?'PASS':'FAIL',$label);
};
$files=[
 'app/Sync/Odl/OdlOperationalConfig.php','app/Sync/SyncSourceScope.php',
 'app/Sync/SyncPlanner.php','app/Sync/SyncPreviewService.php',
 'app/Sync/SyncEngineFactory.php','app/Sync/Adapters/SourceScopedSyncPersistenceAdapter.php',
 'lib/Database.php','lib/q_func.php','admin/dashboard.php',
 'tools/odl_f9_manual_preview.php','tools/odl_f9a_rollback_readiness.php',
 'tests/characterization/odl_f9_manual_operational.php',
];
foreach($files as$file){$o=[];$c=1;exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($root.'/'.$file),$o,$c);$r($c===0,'source and lint '.$file);}
$scope=(string)file_get_contents($root.'/app/Sync/SyncSourceScope.php');
$r(str_contains($scope,'OdlStudentSource::SOURCE_CODE')
 &&str_contains($scope,'[10]'),'ODL scope is category 10 and provenance enforced');
$endpoint=(string)file_get_contents($root.'/lib/q_func.php');
$r(str_contains($endpoint,'ODL_OPERATIONAL_APPLY_DISABLED')
 &&str_contains($endpoint,'sync_assert_source_snapshot_isolated'),
 'Preview isolates source and Apply has separate fail-closed gate');
$db=(string)file_get_contents($root.'/lib/Database.php');
$r(str_contains($db,'SYNC_CROSS_SOURCE_IDENTITY_COLLISION')
 &&str_contains($db,'SYNC_SOURCE_MEMBERSHIP_CONFLICT'),
 'Matrik IC and membership collisions block');
$ui=(string)file_get_contents($root.'/admin/dashboard.php');
$r(str_contains($ui,"pick_preview_sync_user('STUDENT_ODL_PG')")
 &&str_contains($ui,"STUDENT_ODL_PG: 'Sinkronisasi Pelajar ODL'"),
 'Admin routes ODL through guarded Preview and Apply modal');
$badgeStart=strpos($ui,'function external_action_total(');
$badgeEnd=strpos($ui,'function show_external_action_notice(',$badgeStart?:0);
$badgeFunction=$badgeStart!==false&&$badgeEnd!==false
 ?substr($ui,$badgeStart,$badgeEnd-$badgeStart):'';
$r(str_contains($badgeFunction,"'CANDIDATE_NEW'")
 &&str_contains($badgeFunction,"'CANDIDATE_DEACTIVATE'")
 &&str_contains($badgeFunction,"'ADD_MEMBERSHIP'")
 &&!str_contains($badgeFunction,'KEEP_ACCOUNT_ACTIVE'),
 'notification badge counts actionable changes and excludes KEEP');
$runtime=(string)file_get_contents($root.'/config/runtime.php');
$r(str_contains($runtime,"'ONEID_ODL_OPERATIONAL_PREVIEW_ENABLED' => 'false'")
 &&str_contains($runtime,"'ONEID_ODL_OPERATIONAL_APPLY_ENABLED' => 'false'"),
 'deployment defaults remain fail-closed');
$r(!preg_match('/cron|scheduler/i',(string)file_get_contents($root.'/app/Sync/Odl/OdlOperationalConfig.php')),
 'F9 adds no scheduler wiring');
$runner=(string)file_get_contents($root.'/tools/odl_f9_manual_preview.php');
$r(str_contains($runner,"'mutation_statements']=0")
 &&!preg_match('/\\b(?:INSERT\\s+INTO|UPDATE\\s+\\w+\\s+SET|DELETE\\s+FROM|REPLACE\\s+INTO|ALTER\\s+TABLE|DROP\\s+TABLE|TRUNCATE\\s+TABLE)\\b/i',$runner),
 'CLI Preview is zero mutation');
$rollback=(string)file_get_contents($root.'/tools/odl_f9a_rollback_readiness.php');
$r(str_contains($rollback,"'mutation_statements'=>0")
 &&str_contains($rollback,'ROLLBACK_OLD_DATA_MISSING')
 &&str_contains($rollback,'ROLLBACK_ODL_MEMBERSHIP_MISSING')
 &&!preg_match('/\\b(?:INSERT\\s+INTO|UPDATE\\s+\\w+\\s+SET|DELETE\\s+FROM)\\b/i',$rollback),
 'F9A rollback readiness is read-only and fail-closed');
$o=[];$c=1;exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/tests/characterization/odl_f9_manual_operational.php').' 2>&1',$o,$c);
$r($c===0&&in_array('RESULT checks=11 failed=0',$o,true),'F9 characterization passes');
printf("RESULT checks=%d failed=%d\n",$checks,$failed);exit($failed===0?0:1);
