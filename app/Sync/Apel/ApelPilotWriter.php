<?php
declare(strict_types=1);
namespace OneId\App\Sync\Apel;

use OneId\App\Sync\Contracts\ApelPilotPersistenceInterface;
use OneId\App\Sync\Contracts\InitialPasswordFactoryInterface;
use OneId\App\Sync\Contracts\SyncPlanApprovalGateInterface;
use OneId\App\Sync\DTO\SyncPlan;

final class ApelPilotWriter
{
    public function __construct(private readonly ApelPilotPersistenceInterface $persistence, private readonly InitialPasswordFactoryInterface $passwords) {}

    /** @return array{correlation_id:string,new:int,memberships:int,events:int} */
    public function applyApproved(callable $freshPlanProvider,string $approvalId,string $adminId,SyncPlanApprovalGateInterface $gate,bool $authorized): array
    {
        if (!$authorized) throw new \RuntimeException('APEL_PILOT_APPLY_NOT_AUTHORIZED');
        if (!$this->persistence->acquireLock()) throw new \RuntimeException('APEL_PILOT_ALREADY_RUNNING');
        $started=false;
        try {
            $plan=$freshPlanProvider();
            if (!$plan instanceof SyncPlan) throw new \RuntimeException('APEL_PILOT_FRESH_PLAN_INVALID');
            if ($plan->legacyCounts()!==['New'=>3,'Update'=>0,'Deactivate'=>0,'Reactivate'=>0]) throw new \RuntimeException('APEL_PILOT_NEW_ONLY_VIOLATION');
            $approval=$gate->consumeAndValidate($approvalId,$adminId,$plan);
            $this->persistence->begin(); $started=true;
            foreach ($plan->actions as $action) {
                $row=$action['row'];
                $this->persistence->insertStudent($row,$this->passwords->createHash(),(string)$action['change_hash']);
                $this->persistence->insertMembership((string)$action['u_id'],(string)$action['u_id'],(string)$action['change_hash']);
                $this->persistence->appendEvent($approval->correlationId,(string)$action['u_id'],(string)$action['u_id'],'PILOT_NEW');
            }
            if ($this->persistence->reconciliation($approval->correlationId)!==['users'=>3,'memberships'=>3,'events'=>3]) throw new \RuntimeException('APEL_PILOT_RECONCILIATION_MISMATCH');
            $this->persistence->commit(); $started=false;
            return ['correlation_id'=>$approval->correlationId,'new'=>3,'memberships'=>3,'events'=>3];
        } catch (\Throwable $e) {
            if ($started) $this->persistence->rollback();
            throw $e;
        } finally { $this->persistence->releaseLock(); }
    }

    public function rollbackCommitted(string $correlationId,bool $authorized): int
    {
        if (!$authorized) throw new \RuntimeException('APEL_PILOT_ROLLBACK_NOT_AUTHORIZED');
        if (preg_match('/^[a-f0-9]{16}$/',$correlationId)!==1) throw new \RuntimeException('APEL_PILOT_CORRELATION_INVALID');
        if (!$this->persistence->acquireLock()) throw new \RuntimeException('APEL_PILOT_ALREADY_RUNNING');
        $started=false;
        try {$this->persistence->begin();$started=true;$removed=$this->persistence->rollbackCorrelation($correlationId);
            if($removed!==3)throw new \RuntimeException('APEL_PILOT_ROLLBACK_COUNT_MISMATCH');
            $this->persistence->commit();$started=false;return $removed;
        } catch(\Throwable $e){if($started)$this->persistence->rollback();throw $e;}
        finally{$this->persistence->releaseLock();}
    }
}
