<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
$root=dirname(__DIR__);$checks=0;$failed=0;
$r=static function(bool$ok,string$label)use(&$checks,&$failed):void{$checks++;$failed+=$ok?0:1;printf("%s %s\n",$ok?'PASS':'FAIL',$label);};
$files=[
'app/Sync/Odl/OdlPilotConfig.php','app/Sync/Odl/OdlPilotPlanner.php',
'app/Sync/Odl/OdlPilotWriter.php','app/Sync/Adapters/PdoOdlPilotPersistenceAdapter.php',
'app/Sync/Contracts/OdlPilotPersistenceInterface.php',
'tests/characterization/odl_f7_controlled_pilot.php',
'tools/odl_f7_pilot_preview.php'
];
foreach($files as$file){$path=$root.'/'.$file;$out=[];$code=1;exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($path),$out,$code);$r(is_file($path)&&$code===0,'source and lint '.$file);}
$config=(string)file_get_contents($root.'/app/Sync/Odl/OdlPilotConfig.php');
$planner=(string)file_get_contents($root.'/app/Sync/Odl/OdlPilotPlanner.php');
$writer=(string)file_get_contents($root.'/app/Sync/Odl/OdlPilotWriter.php');
$pdo=(string)file_get_contents($root.'/app/Sync/Adapters/PdoOdlPilotPersistenceAdapter.php');
$runtime=(string)file_get_contents($root.'/config/runtime.php');
$q=(string)file_get_contents($root.'/lib/q_func.php');
$r(str_contains($config,'count($digests) !== 3')&&str_contains($config,'ODL_PILOT_SCOPE_MUST_EQUAL_THREE'),'private allowlist requires exactly three digests');
$r(str_contains($runtime,"'ONEID_ODL_PILOT_APPLY_ENABLED' => 'false'")&&!str_contains($q,'OdlPilotWriter'),'live Apply endpoint remains unwired and fail-closed');
$r(str_contains($planner,"'action' => 'NEW'")&&str_contains($planner,'ODL_PILOT_NEW_ONLY_VIOLATION')&&!str_contains($planner,"'action' => 'UPDATE'"),'planner permits NEW-only scope');
$r(str_contains($writer,'$freshPlanProvider()')&&str_contains($writer,'consumeAndValidate')&&str_contains($writer,'ODL_PILOT_APPLY_NOT_AUTHORIZED'),'writer requires fresh plan approval and separate execution authorization');
$r(str_contains($writer,'reconciliation')&&str_contains($writer,'rollbackCorrelation'),'writer has reconciliation and targeted rollback boundaries');
$r(str_contains($pdo,"'STUDENT_ODL_PG'")&&str_contains($pdo,'u_category=10')&&str_contains($pdo,"event_type='PILOT_NEW'"),'dormant adapter binds category provenance and audit');
$migration=(string)file_get_contents($root.'/docs/migrations/20260723_odl_f7_pilot_audit_up.sql');
$r(str_contains($migration,'CREATE TABLE user_external_identity_event')&&str_contains($migration,'PILOT_ROLLED_BACK'),'additive audit migration is defined');
$schema=(string)file_get_contents($root.'/tools/odl_f7_schema_migrate.php');
$r(str_contains($schema,'ONEID_ODL_F7_CHANGE_ID')&&str_contains($schema,'ODL_F7_ROLLBACK_REQUIRES_ZERO_EVENTS'),'schema Apply and rollback are change-ID guarded');
$preview=(string)file_get_contents($root.'/tools/odl_f7_pilot_preview.php');
$r(str_contains($preview,"'can_apply'=>false")&&str_contains($preview,"'execution_authorized'=>false")&&str_contains($preview,"'mutation_statements'=>0")&&!preg_match('/\\b(?:INSERT|UPDATE|DELETE|REPLACE|ALTER|DROP|TRUNCATE)\\b/i',$preview),'pilot Preview is aggregate-only and zero mutation');
$out=[];$code=1;exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/tests/characterization/odl_f7_controlled_pilot.php').' 2>&1',$out,$code);
$r($code===0&&in_array('RESULT checks=10 failed=0',$out,true),'controlled pilot isolated characterization passes');
printf("RESULT checks=%d failed=%d\n",$checks,$failed);exit($failed===0?0:1);
