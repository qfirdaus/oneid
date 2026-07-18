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
    'app/Sync/SyncOperationalConfig.php',
    'app/Sync/OperationalSyncApprovalGate.php',
] as $file) require_once $root . '/' . $file;

use OneId\App\Sync\Contracts\SyncApprovalStoreInterface;
use OneId\App\Sync\DTO\SyncApproval;
use OneId\App\Sync\DTO\SyncPlan;
use OneId\App\Sync\OperationalSyncApprovalGate;
use OneId\App\Sync\SyncApprovalService;
use OneId\App\Sync\SyncOperationalConfig;
use OneId\App\Sync\SyncPlanFingerprinter;

final class S4GStore implements SyncApprovalStoreInterface
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

function s4g_plan(bool $deactivate = false, string $suffix = ''): SyncPlan
{
    $actions = [
        ['action'=>'NEW','u_id'=>'NEW-1','category_id'=>1,'changed_fields'=>[],'change_hash'=>'new'.$suffix],
        ['action'=>'UPDATE','u_id'=>'UPDATE-1','category_id'=>1,'changed_fields'=>['data5'],'change_hash'=>'update'.$suffix],
    ];
    if ($deactivate) {
        $actions[] = ['action'=>'DEACTIVATE','u_id'=>'OLD-1','category_id'=>1,'changed_fields'=>[],'change_hash'=>'old'.$suffix];
    }
    return new SyncPlan($actions, 6485, 0, 0, [], 1, 0);
}

$checks=0;$failed=0;
$report=static function(bool $ok,string $label)use(&$checks,&$failed):void{$checks++;if(!$ok)$failed++;printf("%s %s\n",$ok?'PASS':'FAIL',$label);};
$reason=static function(callable $fn):string{try{$fn();return '';}catch(RuntimeException $e){return $e->getMessage();}};
$fingerprinter = new SyncPlanFingerprinter();
$disabled = SyncOperationalConfig::fromValue('false');
$enabled = SyncOperationalConfig::fromValue('true');
$plan = s4g_plan();
$hash = $fingerprinter->fingerprint($plan);

$report($reason(fn()=>SyncOperationalConfig::fromValue('yes'))==='SYNC_OPERATIONAL_FLAG_INVALID','operational flag is strict');
$report($reason(fn()=>$disabled->confirmationText($hash,$plan->legacyCounts()))==='SYNC_OPERATIONAL_DISABLED','operational mode defaults disabled');
$report($enabled->confirmationText($hash,$plan->legacyCounts())==='APPLY SYNC '.strtoupper(substr($hash,0,12)),'routine confirmation binds exact plan hash');
$deactivatePlan = s4g_plan(true);
$deactivateHash = $fingerprinter->fingerprint($deactivatePlan);
$report($enabled->confirmationText($deactivateHash,$deactivatePlan->legacyCounts())==='APPLY SYNC DEACTIVATE 1 '.strtoupper(substr($deactivateHash,0,12)),'deactivation requires explicit count confirmation');

$store = new S4GStore();
$service = new SyncApprovalService($store,$fingerprinter,300);
$receipt = $service->issue('0530-09',$deactivatePlan,6485,1000);
$confirmation = $enabled->confirmationText($receipt->planFingerprint,$receipt->counts);
$gate = new OperationalSyncApprovalGate($service,$enabled,$confirmation);
$approval = $gate->consumeAndValidate($receipt->approvalId,'0530-09',$deactivatePlan,1001);
$report($approval->counts['Deactivate']===1&&$store->consumes===1,'matching operational approval is consumed once');
$report($reason(fn()=>$gate->consumeAndValidate($receipt->approvalId,'0530-09',$deactivatePlan,1002))==='SYNC_APPROVAL_NOT_AVAILABLE','operational approval cannot be replayed');

$store = new S4GStore();
$service = new SyncApprovalService($store,$fingerprinter,300);
$receipt = $service->issue('0530-09',$deactivatePlan,6485,2000);
$gate = new OperationalSyncApprovalGate($service,$enabled,'APPLY SYNC '.strtoupper(substr($receipt->planFingerprint,0,12)));
$report($reason(fn()=>$gate->consumeAndValidate($receipt->approvalId,'0530-09',$deactivatePlan,2001))==='SYNC_OPERATIONAL_CONFIRMATION_INVALID','deactivation confirmation cannot be downgraded');
$report($store->consumes===1,'invalid confirmation burns the one-time approval');

$store = new S4GStore();
$service = new SyncApprovalService($store,$fingerprinter,300);
$receipt = $service->issue('0530-09',$plan,6485,3000);
$gate = new OperationalSyncApprovalGate($service,$enabled,$enabled->confirmationText($receipt->planFingerprint,$receipt->counts));
$report($reason(fn()=>$gate->consumeAndValidate($receipt->approvalId,'0530-09',s4g_plan(false,'-drift'),3001))==='SYNC_APPROVAL_PLAN_MISMATCH','fresh plan drift is rejected');

printf("RESULT checks=%d failed=%d\n",$checks,$failed);exit($failed===0?0:1);
