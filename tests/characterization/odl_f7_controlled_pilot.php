<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(2);

require_once dirname(__DIR__, 2) . '/bootstrap/sync_runtime.php';

use OneId\App\Sync\Contracts\InitialPasswordFactoryInterface;
use OneId\App\Sync\Contracts\OdlPilotPersistenceInterface;
use OneId\App\Sync\Contracts\SyncApprovalStoreInterface;
use OneId\App\Sync\DTO\SyncApproval;
use OneId\App\Sync\Odl\OdlPilotConfig;
use OneId\App\Sync\Odl\OdlPilotPlanner;
use OneId\App\Sync\Odl\OdlPilotWriter;
use OneId\App\Sync\SyncApprovalService;
use OneId\App\Sync\SyncPlanFingerprinter;

final class F7Store implements SyncApprovalStoreInterface {
    public ?SyncApproval $approval = null;
    public function save(SyncApproval $approval): void { $this->approval = $approval; }
    public function consume(string $approvalId): ?SyncApproval {
        $value = $this->approval;
        $this->approval = null;
        return $value?->approvalId === $approvalId ? $value : null;
    }
}
final class F7Persistence implements OdlPilotPersistenceInterface {
    public array $calls = [];
    public bool $failReconciliation = false;
    public function acquireLock(): bool { $this->calls[]='lock'; return true; }
    public function releaseLock(): void { $this->calls[]='unlock'; }
    public function begin(): void { $this->calls[]='begin'; }
    public function commit(): void { $this->calls[]='commit'; }
    public function rollback(): void { $this->calls[]='rollback'; }
    public function insertStudent(array $row,string $passwordHash,string $changeHash):void{$this->calls[]=['user',$row['data4'],strlen($passwordHash)>20];}
    public function insertMembership(string $userId,string $externalUserId,string $sourceHash):void{$this->calls[]=['membership',$userId,$externalUserId];}
    public function appendEvent(string $correlationId,string $userId,string $externalUserId,string $eventType):void{$this->calls[]=['event',$correlationId,$userId,$eventType];}
    public function reconciliation(string $correlationId):array{$this->calls[]='reconcile';return $this->failReconciliation?['users'=>2,'memberships'=>3,'events'=>3]:['users'=>3,'memberships'=>3,'events'=>3];}
    public function rollbackCorrelation(string $correlationId):int{$this->calls[]=['rollback-correlation',$correlationId];return 3;}
}
final class F7Passwords implements InitialPasswordFactoryInterface {
    public function createHash(): string { return str_repeat('x', 60); }
}
function f7row(string $matric,string $ic): array {
    $row=array_fill_keys(array_map(fn($n)=>'data'.$n,range(1,12)),'');
    return array_replace($row,[
        'source_code'=>'STUDENT_ODL_PG','data1'=>'Private',
        'data2'=>$ic,'data4'=>$matric,'data5'=>'private@example.test',
        'ext_data_source_category'=>'Pelajar',
    ]);
}
$rows=[f7row('ODL001','IC001'),f7row('ODL002','IC002'),f7row('ODL003','IC003'),f7row('ODL004','IC004')];
$digests=array_map(fn($row)=>hash('sha256',$row['data4'].'|'.$row['data2']),array_slice($rows,0,3));
$checks=0;$failed=0;
$report=static function(bool $ok,string $label)use(&$checks,&$failed):void{$checks++;$failed+=$ok?0:1;printf("%s %s\n",$ok?'PASS':'FAIL',$label);};

$config=OdlPilotConfig::fromValues('true','false',implode(',',$digests));
$report($config->previewEnabled&&!$config->applyEnabled&&count($config->allowedIdentityDigests)===3,'implementation config enables Preview only for exact scope three');
$invalid=false;try{OdlPilotConfig::fromValues('true','false',implode(',',array_slice($digests,0,2)));}catch(RuntimeException $e){$invalid=$e->getMessage()==='ODL_PILOT_SCOPE_MUST_EQUAL_THREE';}
$report($invalid,'scope other than three fails closed');
$plan=(new OdlPilotPlanner($digests))->plan($rows,[],[]);
$report($plan->legacyCounts()===['New'=>3,'Update'=>0,'Deactivate'=>0,'Reactivate'=>0],'planner selects exactly three NEW actions');
$report(count($plan->safeProjection())===3&&!str_contains(json_encode($plan->safeProjection()),'Private'),'safe projection excludes raw profile');
$existing=false;try{(new OdlPilotPlanner($digests))->plan($rows,[['u_id'=>'ODL001','data2'=>'IC001']],[]);}catch(RuntimeException $e){$existing=$e->getMessage()==='ODL_PILOT_NEW_ONLY_VIOLATION';}
$report($existing,'existing identity violates NEW-only scope');

$store=new F7Store();$approval=new SyncApprovalService($store,new SyncPlanFingerprinter());$receipt=$approval->issue('ADMIN1',$plan,53);
$persistence=new F7Persistence();$writer=new OdlPilotWriter($persistence,new F7Passwords());
$blocked=false;try{$writer->applyApproved(fn()=>$plan,$receipt->approvalId,'ADMIN1',$approval,false);}catch(RuntimeException $e){$blocked=$e->getMessage()==='ODL_PILOT_APPLY_NOT_AUTHORIZED';}
$report($blocked&&$persistence->calls===[],'implementation-only boundary performs zero mutation');
$result=$writer->applyApproved(fn()=>$plan,$receipt->approvalId,'ADMIN1',$approval,true);
$report($result['new']===3&&$result['memberships']===3&&$result['events']===3,'approved rehearsal writes exact user membership event totals');
$report(end($persistence->calls)==='unlock'&&in_array('commit',$persistence->calls,true)&&!in_array('rollback',$persistence->calls,true),'healthy rehearsal commits and releases lock');
$rolled=$writer->rollbackCommitted($result['correlation_id'],true);
$report($rolled===3&&in_array('rollback-correlation',array_column(array_filter($persistence->calls,'is_array'),0),true),'correlation rollback removes exact pilot scope');

$store2=new F7Store();$approval2=new SyncApprovalService($store2,new SyncPlanFingerprinter());$receipt2=$approval2->issue('ADMIN1',$plan,53);
$bad=new F7Persistence();$bad->failReconciliation=true;
try{$writer2=new OdlPilotWriter($bad,new F7Passwords());$writer2->applyApproved(fn()=>$plan,$receipt2->approvalId,'ADMIN1',$approval2,true);}catch(RuntimeException $e){}
$report(in_array('rollback',$bad->calls,true)&&!in_array('commit',$bad->calls,true)&&end($bad->calls)==='unlock','reconciliation mismatch rolls back and releases lock');

printf("RESULT checks=%d failed=%d\n",$checks,$failed);exit($failed===0?0:1);
