<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
$root=dirname(__DIR__);$checks=0;$failed=0;
$r=static function(bool$ok,string$label)use(&$checks,&$failed):void{
 $checks++;$failed+=$ok?0:1;printf("%s %s\n",$ok?'PASS':'FAIL',$label);
};
$files=[
 'app/Sync/Provenance/StaffProvenancePreview.php',
 'tools/source_isolation_staff_preview.php',
 'tests/characterization/source_isolation_matrix.php',
];
foreach($files as$file){$out=[];$code=1;exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($root.'/'.$file),$out,$code);$r($code===0,'source and lint '.$file);}
$up=(string)file_get_contents($root.'/docs/migrations/20260723_source_isolation_staff_hr_up.sql');
$down=(string)file_get_contents($root.'/docs/migrations/20260723_source_isolation_staff_hr_down.sql');
$r(str_contains($up,"'STAFF_HR'")&&str_contains($up,"'dormant'"),'STAFF_HR registration is dormant');
$r(str_contains($down,'NOT EXISTS')&&str_contains($down,'user_external_identity_event'),'STAFF_HR rollback refuses referenced source');
$preview=(string)file_get_contents($root.'/tools/source_isolation_staff_preview.php');
$r(!preg_match('/\\b(?:INSERT|UPDATE|DELETE|REPLACE|ALTER|DROP|TRUNCATE)\\b/i',$preview),'Staff Preview has no mutation SQL');
$adapter=(string)file_get_contents($root.'/app/Sync/Adapters/SourceScopedSyncPersistenceAdapter.php');
$r(str_contains($adapter,'SYNC_SOURCE_OWNERSHIP_VIOLATION')&&str_contains($adapter,'$recordMembership'),'persistence rechecks ownership and records membership');
$r(str_contains($adapter,"account_source'] ?? '') === 'manual'")&&str_contains($adapter,"sync_protected'] ?? 0"),'manual collision context survives source filtering');
$factory=(string)file_get_contents($root.'/app/Sync/SyncEngineFactory.php');
$r(str_contains($factory,'sync_get_inactive_user_ids_by_source')&&str_contains($factory,'sync_assert_source_identity_writable')&&str_contains($factory,'sync_has_other_active_source'),'factory wires read and write source guards');
$runtime=(string)file_get_contents($root.'/config/runtime.php');
$r(str_contains($runtime,"'ONEID_SYNC_STAFF_PROVENANCE_ENABLED' => 'false'"),'Staff enforcement gate defaults fail-closed');
$out=[];$code=1;exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/tests/characterization/source_isolation_matrix.php').' 2>&1',$out,$code);
$r($code===0&&in_array('RESULT checks=6 failed=0',$out,true),'cross-source isolation matrix passes');
printf("RESULT checks=%d failed=%d\n",$checks,$failed);exit($failed===0?0:1);
