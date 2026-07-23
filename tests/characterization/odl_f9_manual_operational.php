<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
require_once dirname(__DIR__,2).'/bootstrap/sync_runtime.php';

use OneId\App\Sync\Adapters\LegacySyncPolicy;
use OneId\App\Sync\Adapters\SourceScopedSyncPersistenceAdapter;
use OneId\App\Sync\Contracts\SyncPersistenceInterface;
use OneId\App\Sync\Odl\OdlOperationalConfig;
use OneId\App\Sync\SyncPlanner;
use OneId\App\Sync\SyncSafetyPolicy;

$checks=0;$failed=0;
$r=static function(bool$ok,string$label)use(&$checks,&$failed):void{
 $checks++;$failed+=$ok?0:1;printf("%s %s\n",$ok?'PASS':'FAIL',$label);
};
$r(OdlOperationalConfig::fromValues('true','false')->previewEnabled,
 'manual ODL Preview can be enabled independently');
$blocked=false;try{OdlOperationalConfig::fromValues('false','true');}
catch(RuntimeException$e){$blocked=$e->getMessage()==='ODL_OPERATIONAL_FLAG_COMBINATION_INVALID';}
$r($blocked,'ODL Apply cannot be enabled without Preview');
$blocked=false;try{OdlOperationalConfig::fromValues('true','false')->assertApplyEnabled();}
catch(RuntimeException$e){$blocked=$e->getMessage()==='ODL_OPERATIONAL_APPLY_DISABLED';}
$r($blocked,'ODL Apply remains fail-closed');

$old=[[
 'u_id'=>'ODL1','u_category'=>10,'avail_status'=>1,'data1'=>'Student',
 'data2'=>'IC1','data3'=>'','data4'=>'ODL1','data5'=>'kept@oneid.test',
 'data6'=>'F','data7'=>'P','data8'=>'','data9'=>'','data10'=>'',
 'data11'=>'','data12'=>'','u_changes_hash'=>'',
 'account_source'=>'external','sync_protected'=>0,
]];
$row=[
 'source_code'=>'STUDENT_ODL_PG','data1'=>'Student','data2'=>'IC1',
 'data3'=>'','data4'=>'ODL1','data5'=>'','data6'=>'F','data7'=>'P',
 'data8'=>'','data9'=>'','data10'=>'','data11'=>'','data12'=>'',
 'ext_data_source_category'=>'Pelajar',
];
$plan=(new SyncPlanner(new LegacySyncPolicy(),true))->plan([$row],$old,[]);
$r(($plan->legacyCounts()['Update']??-1)===0,
 'blank ODL email does not erase existing OneID email');
$decision=(new SyncSafetyPolicy(requiredSourceCode:'STUDENT_ODL_PG'))
 ->assess([$row],$old,$plan,1);
$r($decision->allowed,'ODL-only safety accepts student source without Staff rows');

final class F9Persistence implements SyncPersistenceInterface{
 public array$insert=[];public function begin():void{}public function commit():void{}
 public function rollback():void{}public function createHeader(int$type):int{return 1;}
 public function activeUsers():array{return[];}public function inactiveUserIds():array{return[];}
 public function deactivateUser(string$userId):void{}
 public function updateUser(string$userId,array$row,string$changeHash):void{}
 public function updateHeaderStatus(int$headerId,int$status,string$field,int$count):void{}
 public function stageExternalUser(int$headerId,array$row):int{return 1;}
 public function insertExternalUser(array$row,int$categoryId,string$passwordHash,string$changeHash):void{$this->insert=[$row,$categoryId];}
 public function markStagedUser(int$headerId,int$bodyId,int$status):void{}
 public function appendChanges(array$changes):void{}
 public function updateSummary(int$headerId,int$new,int$updated,int$deactivated,int$reactivated,string$triggeredBy):void{}
 public function header(int$headerId):array{return[];}
}
$fixture=new F9Persistence();$seen=[];
$guarded=new SourceScopedSyncPersistenceAdapter(
 $fixture,[10],static fn()=>[],static fn()=>[],
 static function(string$uid,string$ic)use(&$seen):void{$seen=[$uid,$ic];}
);
$guarded->insertExternalUser($row,10,'hash','change');
$r($seen===['ODL1','IC1'],'write isolation guard receives Matrik and IC');

printf("RESULT checks=%d failed=%d\n",$checks,$failed);exit($failed===0?0:1);
