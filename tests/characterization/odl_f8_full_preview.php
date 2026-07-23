<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
require_once dirname(__DIR__,2).'/bootstrap/sync_runtime.php';
use OneId\App\Sync\Odl\OdlFullConfig;
use OneId\App\Sync\Odl\OdlFullPlanner;
$checks=0;$failed=0;$r=static function(bool$ok,string$l)use(&$checks,&$failed){
 $checks++;$failed+=$ok?0:1;printf("%s %s\n",$ok?'PASS':'FAIL',$l);
};
$row=static fn(string$m,string$ic):array=>[
 'source_code'=>'STUDENT_ODL_PG','data1'=>'Student','data2'=>$ic,
 'data3'=>'','data4'=>$m,'data5'=>'','data6'=>'F','data7'=>'P',
 'data8'=>'','data9'=>'','data10'=>'','data11'=>'','data12'=>'',
 'ext_data_source_category'=>'Pelajar',
];
$config=OdlFullConfig::fromValues('true','false','3','2','1');
$plan=(new OdlFullPlanner())->plan(
 [$row('O1','IC1'),$row('O2','IC2'),$row('O3','IC3')],
 [['u_id'=>'O1','data2'=>'IC1','account_source'=>'external','sync_protected'=>0]],
 [['u_id'=>'O1','source_code'=>'STUDENT_ODL_PG','external_user_id'=>'O1','source_active'=>1]],
 $config
);
$r($plan->counts()===['New'=>2,'Keep'=>1,'Update'=>0,'Deactivate'=>0,'Reactivate'=>0],'full plan is exact NEW and KEEP only');
$r(count($plan->safeActions())===2&&!str_contains(json_encode($plan->safeActions()),'IC2'),'safe projection excludes raw identity');
$r(strlen($plan->planHash())===64,'plan hash generated');
$reordered=(new OdlFullPlanner())->plan(
 [$row('O3','IC3'),$row('O1','IC1'),$row('O2','IC2')],
 [['u_id'=>'O1','data2'=>'IC1','account_source'=>'external','sync_protected'=>0]],
 [['u_id'=>'O1','source_code'=>'STUDENT_ODL_PG','external_user_id'=>'O1','source_active'=>1]],
 $config
);
$r($reordered->planHash()===$plan->planHash(),'plan hash is source-order independent');
$blocked=static function(callable$f):string{try{$f();}catch(RuntimeException$e){return$e->getMessage();}return'';};
$r($blocked(fn()=>(new OdlFullPlanner())->plan(
 [$row('O1','DIFFERENT'),$row('O2','IC2'),$row('O3','IC3')],
 [['u_id'=>'O1','data2'=>'IC1','account_source'=>'external','sync_protected'=>0]],
 [['u_id'=>'O1','source_code'=>'STUDENT_ODL_PG','external_user_id'=>'O1','source_active'=>1]],
 $config
))==='ODL_FULL_CROSS_SOURCE_IDENTITY_CONFLICT','identity conflict blocks');
$r($blocked(fn()=>OdlFullConfig::fromValues('false','true','3','2','1'))==='ODL_FULL_FLAG_COMBINATION_INVALID','Apply cannot run without Preview');
$r(OdlFullConfig::fromValues('true','true','3','2','1')->applyEnabled,'authorized Apply config is explicit');
$r($blocked(fn()=>(new OdlFullPlanner())->plan(
 [$row('O1','IC1'),$row('O2','IC2'),$row('O3','IC3')],
 [['u_id'=>'O1','data2'=>'IC1','account_source'=>'external','sync_protected'=>0]],
 [
  ['u_id'=>'O1','source_code'=>'STUDENT_ODL_PG','external_user_id'=>'O1','source_active'=>1],
  ['u_id'=>'OLD','source_code'=>'STUDENT_ODL_PG','external_user_id'=>'OLD','source_active'=>1],
 ],$config
))==='ODL_FULL_DEACTIVATION_NOT_ALLOWED','deactivation candidate blocks');
printf("RESULT checks=%d failed=%d\n",$checks,$failed);exit($failed===0?0:1);
