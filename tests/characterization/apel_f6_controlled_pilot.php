<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/bootstrap/sync_runtime.php';

use OneId\App\Sync\Apel\{ApelPilotConfig,ApelPilotPlanner,ApelPilotWriter};
use OneId\App\Sync\Contracts\{ApelPilotPersistenceInterface,InitialPasswordFactoryInterface,SyncApprovalStoreInterface};
use OneId\App\Sync\DTO\SyncApproval;
use OneId\App\Sync\{SyncApprovalService,SyncPlanFingerprinter};

final class ApelF6Store implements SyncApprovalStoreInterface{public ?SyncApproval $a=null;public function save(SyncApproval $a):void{$this->a=$a;}public function consume(string $id):?SyncApproval{$a=$this->a;$this->a=null;return $a?->approvalId===$id?$a:null;}}
final class ApelF6Passwords implements InitialPasswordFactoryInterface{public function createHash():string{return str_repeat('x',60);}}
final class ApelF6Persistence implements ApelPilotPersistenceInterface{
 public array$calls=[];public bool$bad=false;public function acquireLock():bool{$this->calls[]='lock';return true;}public function releaseLock():void{$this->calls[]='unlock';}public function begin():void{$this->calls[]='begin';}public function commit():void{$this->calls[]='commit';}public function rollback():void{$this->calls[]='rollback';}
 public function insertStudent(array$r,string$p,string$h):void{$this->calls[]=['user',$r['data4']];}public function insertMembership(string$u,string$e,string$h):void{$this->calls[]=['membership',$u];}public function appendEvent(string$c,string$u,string$e,string$t):void{$this->calls[]=['event',$c,$t];}
 public function reconciliation(string$c):array{$this->calls[]='reconcile';return$this->bad?['users'=>2,'memberships'=>3,'events'=>3]:['users'=>3,'memberships'=>3,'events'=>3];}public function rollbackCorrelation(string$c):int{$this->calls[]=['rollback-correlation',$c];return 3;}}
function apelF6Row(string$m,string$i):array{$r=array_fill_keys(array_map(fn($n)=>'data'.$n,range(1,12)),'');return array_replace($r,['source_code'=>'STUDENT_APEL','data1'=>'Private','data2'=>$i,'data4'=>$m,'ext_data_source_category'=>'Pelajar']);}
$rows=[apelF6Row('A1','I1'),apelF6Row('A2','I2'),apelF6Row('A3','I3')];$digests=array_map(fn($r)=>hash('sha256',$r['data4'].'|'.$r['data2']),$rows);
$n=0;$f=0;$r=function(bool$o,string$l)use(&$n,&$f){$n++;$f+=$o?0:1;echo($o?'PASS ':'FAIL '),$l,"\n";};
$cfg=ApelPilotConfig::fromValues('true','false',implode(',',$digests));$r($cfg->previewEnabled&&!$cfg->applyEnabled,'preview enabled while apply remains disabled');
$invalid=false;try{ApelPilotConfig::fromValues('true','false',$digests[0]);}catch(RuntimeException$e){$invalid=true;}$r($invalid,'pilot scope must contain exactly three digests');
$plan=(new ApelPilotPlanner($digests))->plan($rows,[],[]);$r($plan->legacyCounts()===['New'=>3,'Update'=>0,'Deactivate'=>0,'Reactivate'=>0],'planner permits exactly three NEW accounts');
$r(!str_contains(json_encode($plan->safeProjection()),'Private'),'safe projection excludes profile data');
$store=new ApelF6Store();$approval=new SyncApprovalService($store,new SyncPlanFingerprinter());$receipt=$approval->issue('ADMIN',$plan,53);$p=new ApelF6Persistence();$writer=new ApelPilotWriter($p,new ApelF6Passwords());
$blocked=false;try{$writer->applyApproved(fn()=>$plan,$receipt->approvalId,'ADMIN',$approval,false);}catch(RuntimeException$e){$blocked=$e->getMessage()==='APEL_PILOT_APPLY_NOT_AUTHORIZED';}$r($blocked&&$p->calls===[],'unauthorized boundary performs no writes');
$result=$writer->applyApproved(fn()=>$plan,$receipt->approvalId,'ADMIN',$approval,true);$r($result['new']===3&&in_array('commit',$p->calls,true),'approved exact plan commits 3/3/3');
$store2=new ApelF6Store();$approval2=new SyncApprovalService($store2,new SyncPlanFingerprinter());$receipt2=$approval2->issue('ADMIN',$plan,53);$bad=new ApelF6Persistence();$bad->bad=true;try{(new ApelPilotWriter($bad,new ApelF6Passwords()))->applyApproved(fn()=>$plan,$receipt2->approvalId,'ADMIN',$approval2,true);}catch(RuntimeException){}$r(in_array('rollback',$bad->calls,true)&&!in_array('commit',$bad->calls,true),'reconciliation mismatch rolls back');
$r($writer->rollbackCommitted($result['correlation_id'],true)===3,'rollback guard removes exact correlation scope');
printf("RESULT checks=%d failures=%d\n",$n,$f);exit($f===0?0:1);
