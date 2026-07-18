<?php

if (PHP_SAPI !== 'cli') { exit(2); }
$root = dirname(__DIR__, 2);
foreach ([
    'app/Sync/Contracts/SyncApprovalStoreInterface.php',
    'app/Sync/Contracts/SyncPlanApprovalGateInterface.php',
    'app/Sync/DTO/SyncPlan.php',
    'app/Sync/DTO/SyncApproval.php',
    'app/Sync/DTO/SyncApprovalReceipt.php',
    'app/Sync/SyncPlanFingerprinter.php',
    'app/Sync/SyncApprovalService.php',
    'app/Sync/SyncFullConfig.php',
    'app/Sync/FullSyncApprovalGate.php',
] as $file) require_once $root . '/' . $file;

use OneId\App\Sync\Contracts\SyncApprovalStoreInterface;
use OneId\App\Sync\DTO\SyncApproval;
use OneId\App\Sync\DTO\SyncPlan;
use OneId\App\Sync\FullSyncApprovalGate;
use OneId\App\Sync\SyncApprovalService;
use OneId\App\Sync\SyncFullConfig;
use OneId\App\Sync\SyncPlanFingerprinter;

final class S4FFullStore implements SyncApprovalStoreInterface
{
    public array $records = [];
    public int $consumes = 0;
    public function save(SyncApproval $approval): void { $this->records[$approval->approvalId] = $approval; }
    public function consume(string $approvalId): ?SyncApproval
    {
        $this->consumes++;
        $approval = $this->records[$approvalId] ?? null;
        unset($this->records[$approvalId]);
        return $approval;
    }
}

function s4f_plan(string $suffix = ''): SyncPlan
{
    return new SyncPlan([
        ['action'=>'NEW','u_id'=>'NEW-1','category_id'=>1,'changed_fields'=>[],'change_hash'=>'new' . $suffix],
        ['action'=>'UPDATE','u_id'=>'UPDATE-1','category_id'=>1,'changed_fields'=>['data5'],'change_hash'=>'update' . $suffix],
        ['action'=>'DEACTIVATE','u_id'=>'OLD-1','category_id'=>1,'changed_fields'=>[],'change_hash'=>'old' . $suffix],
    ], 6485, 0, 0, [], 1, 0);
}

$checks=0;$failed=0;
$report=static function(bool $ok,string $label)use(&$checks,&$failed):void{$checks++;if(!$ok)$failed++;printf("%s %s\n",$ok?'PASS':'FAIL',$label);};
$reason=static function(callable $fn):string{try{$fn();return '';}catch(RuntimeException $e){return $e->getMessage();}};
$fingerprinter = new SyncPlanFingerprinter();
$plan = s4f_plan();
$hash = $fingerprinter->fingerprint($plan);

$disabled = SyncFullConfig::fromValues('false','0','0','0','0','');
$report($reason(fn()=>$disabled->assertPlan($plan,$fingerprinter))==='SYNC_FULL_DISABLED','full mode defaults disabled');
$report($reason(fn()=>SyncFullConfig::fromValues('true','1','1','1','0','bad'))==='SYNC_FULL_PLAN_HASH_INVALID','enabled mode requires full 64-character hash');

$config = SyncFullConfig::fromValues('true','1','1','1','0',$hash);
$config->assertPlan($plan,$fingerprinter);
$report($config->confirmationText()==='FULL SYNC '.strtoupper(substr($hash,0,12)),'confirmation is bound to plan hash prefix');
$wrongCounts = SyncFullConfig::fromValues('true','2','1','1','0',$hash);
$report($reason(fn()=>$wrongCounts->assertPlan($plan,$fingerprinter))==='SYNC_FULL_COUNT_MISMATCH','exact count mismatch fails closed');
$report($reason(fn()=>$config->assertPlan(s4f_plan('-changed'),$fingerprinter))==='SYNC_FULL_PLAN_MISMATCH','changed plan hash fails closed');

$store = new S4FFullStore();
$service = new SyncApprovalService($store,$fingerprinter,300);
$receipt = $service->issue('0530-09',$plan,6485,1000);
$gate = new FullSyncApprovalGate($service,$config,$fingerprinter);
$approval = $gate->consumeAndValidate($receipt->approvalId,'0530-09',$plan,1001);
$report($approval->counts===$config->expectedCounts && $store->consumes===1,'matching full approval is consumed once');
$report($reason(fn()=>$gate->consumeAndValidate($receipt->approvalId,'0530-09',$plan,1002))==='SYNC_APPROVAL_NOT_AVAILABLE','full approval cannot be replayed');

$store = new S4FFullStore();
$service = new SyncApprovalService($store,$fingerprinter,300);
$receipt = $service->issue('0530-09',$plan,6485,2000);
$gate = new FullSyncApprovalGate($service,$config,$fingerprinter);
$report($reason(fn()=>$gate->consumeAndValidate($receipt->approvalId,'0530-09',s4f_plan('-drift'),2001))==='SYNC_FULL_PLAN_MISMATCH','snapshot drift is rejected by private full-plan gate');
$report($store->consumes===0,'private plan mismatch is rejected before approval consumption');

printf("RESULT checks=%d failed=%d\n",$checks,$failed);exit($failed===0?0:1);
