<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
$root=dirname(__DIR__);$checks=0;$failed=0;
$r=static function(bool$ok,string$l)use(&$checks,&$failed){$checks++;$failed+=$ok?0:1;printf("%s %s\n",$ok?'PASS':'FAIL',$l);};
$files=['app/Sync/DTO/OdlFullPlan.php','app/Sync/Odl/OdlFullConfig.php',
 'app/Sync/Odl/OdlFullPlanner.php','tools/odl_f8_full_preview.php',
 'tests/characterization/odl_f8_full_preview.php'];
foreach($files as$f){$o=[];$c=1;exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($root.'/'.$f),$o,$c);$r($c===0,'source and lint '.$f);}
$config=(string)file_get_contents($root.'/app/Sync/Odl/OdlFullConfig.php');
$r(str_contains($config,'ODL_FULL_APPLY_NOT_AUTHORIZED'),'Apply config is hard blocked');
$planner=(string)file_get_contents($root.'/app/Sync/Odl/OdlFullPlanner.php');
$r(str_contains($planner,'ODL_FULL_DEACTIVATION_NOT_ALLOWED')&&str_contains($planner,'ODL_FULL_CROSS_SOURCE_IDENTITY_CONFLICT'),'planner blocks cross-source and deactivation');
$preview=(string)file_get_contents($root.'/tools/odl_f8_full_preview.php');
$r(str_contains($preview,"'can_apply'=>false")&&str_contains($preview,"'mutation_statements'=>0")&&!preg_match('/\\b(?:INSERT|UPDATE|DELETE|REPLACE|ALTER|DROP|TRUNCATE)\\b/i',$preview),'Preview is aggregate-only zero mutation');
$q=(string)file_get_contents($root.'/lib/q_func.php');
$r(!str_contains($q,'OdlFullPlanner'),'no web Apply or Preview endpoint wired');
$out=[];$code=1;exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/tests/characterization/odl_f8_full_preview.php').' 2>&1',$out,$code);
$r($code===0&&in_array('RESULT checks=7 failed=0',$out,true),'F8 characterization passes');
printf("RESULT checks=%d failed=%d\n",$checks,$failed);exit($failed===0?0:1);
