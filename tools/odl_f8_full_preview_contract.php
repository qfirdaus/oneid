<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
$root=dirname(__DIR__);$checks=0;$failed=0;
$r=static function(bool$ok,string$l)use(&$checks,&$failed){$checks++;$failed+=$ok?0:1;printf("%s %s\n",$ok?'PASS':'FAIL',$l);};
$files=['app/Sync/DTO/OdlFullPlan.php','app/Sync/Odl/OdlFullConfig.php',
 'app/Sync/Odl/OdlFullPlanner.php',
 'app/Sync/Adapters/PdoOdlFullPersistenceAdapter.php',
 'tools/odl_f8_full_preview.php','tools/odl_f8_full_apply.php',
 'tools/odl_f8_schema_migrate.php',
 'tests/characterization/odl_f8_full_preview.php'];
foreach($files as$f){$o=[];$c=1;exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($root.'/'.$f),$o,$c);$r($c===0,'source and lint '.$f);}
$config=(string)file_get_contents($root.'/app/Sync/Odl/OdlFullConfig.php');
$runtime=(string)file_get_contents($root.'/config/runtime.php');
$r(str_contains($runtime,"'ONEID_ODL_FULL_APPLY_ENABLED' => 'false'")
 &&str_contains($config,'ODL_FULL_FLAG_COMBINATION_INVALID'),'Apply defaults disabled and requires Preview');
$planner=(string)file_get_contents($root.'/app/Sync/Odl/OdlFullPlanner.php');
$r(str_contains($planner,'ODL_FULL_DEACTIVATION_NOT_ALLOWED')&&str_contains($planner,'ODL_FULL_CROSS_SOURCE_IDENTITY_CONFLICT'),'planner blocks cross-source and deactivation');
$preview=(string)file_get_contents($root.'/tools/odl_f8_full_preview.php');
$r(str_contains($preview,"'can_apply'=>false")&&str_contains($preview,"'mutation_statements'=>0")&&!preg_match('/\\b(?:INSERT|UPDATE|DELETE|REPLACE|ALTER|DROP|TRUNCATE)\\b/i',$preview),'Preview is aggregate-only zero mutation');
$q=(string)file_get_contents($root.'/lib/q_func.php');
$r(!str_contains($q,'OdlFullPlanner'),'no web Apply or Preview endpoint wired');
$out=[];$code=1;exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/tests/characterization/odl_f8_full_preview.php').' 2>&1',$out,$code);
$apply=(string)file_get_contents($root.'/tools/odl_f8_full_apply.php');
$r(str_contains($apply,'ODL_F8_EXECUTION_AUTHORIZATION_REQUIRED')
 &&str_contains($apply,'ODL_F8_OUTSIDE_CHANGE_WINDOW')
 &&str_contains($apply,'ODL_F8_FRESH_PLAN_MISMATCH')
 &&str_contains($apply,'ODL_F8_RECONCILIATION_MISMATCH'),'one-shot Apply binds authorization, time, fresh plan and reconciliation');
$schema=(string)file_get_contents($root.'/tools/odl_f8_schema_migrate.php');
$r(str_contains($schema,'ONEID_ODL_F8_CHANGE_ID')&&str_contains($schema,'ODL_F8_SCHEMA_ROLLBACK_BLOCKED'),'Full event schema is change-ID guarded');
$r($code===0&&in_array('RESULT checks=8 failed=0',$out,true),'F8 characterization passes');
printf("RESULT checks=%d failed=%d\n",$checks,$failed);exit($failed===0?0:1);
