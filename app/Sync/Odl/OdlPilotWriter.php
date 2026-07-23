<?php
declare(strict_types=1);
namespace OneId\App\Sync\Odl;

use OneId\App\Sync\Contracts\InitialPasswordFactoryInterface;
use OneId\App\Sync\Contracts\OdlPilotPersistenceInterface;
use OneId\App\Sync\Contracts\SyncPlanApprovalGateInterface;
use OneId\App\Sync\DTO\SyncPlan;

final class OdlPilotWriter
{
    public function __construct(
        private readonly OdlPilotPersistenceInterface $persistence,
        private readonly InitialPasswordFactoryInterface $passwords
    ) {}

    /** @return array{correlation_id:string,new:int,memberships:int,events:int} */
    public function applyApproved(
        callable $freshPlanProvider,
        string $approvalId,
        string $adminId,
        SyncPlanApprovalGateInterface $approvalGate,
        bool $executionAuthorized
    ): array {
        if (!$executionAuthorized) {
            throw new \RuntimeException('ODL_PILOT_APPLY_NOT_AUTHORIZED');
        }
        if (!$this->persistence->acquireLock()) {
            throw new \RuntimeException('ODL_PILOT_ALREADY_RUNNING');
        }
        $started = false;
        try {
            $plan = $freshPlanProvider();
            if (!$plan instanceof SyncPlan) {
                throw new \RuntimeException('ODL_PILOT_FRESH_PLAN_INVALID');
            }
            if ($plan->legacyCounts() !== [
                'New' => 3, 'Update' => 0,
                'Deactivate' => 0, 'Reactivate' => 0,
            ]) {
                throw new \RuntimeException('ODL_PILOT_NEW_ONLY_VIOLATION');
            }
            $approval = $approvalGate->consumeAndValidate(
                $approvalId,
                $adminId,
                $plan
            );
            $correlation = $approval->correlationId;
            $this->persistence->begin();
            $started = true;
            foreach ($plan->actions as $action) {
                $row = $action['row'];
                $this->persistence->insertStudent(
                    $row,
                    $this->passwords->createHash(),
                    (string) $action['change_hash']
                );
                $this->persistence->insertMembership(
                    (string) $action['u_id'],
                    (string) $action['u_id'],
                    (string) $action['change_hash']
                );
                $this->persistence->appendEvent(
                    $correlation,
                    (string) $action['u_id'],
                    (string) $action['u_id'],
                    'PILOT_NEW'
                );
            }
            $actual = $this->persistence->reconciliation($correlation);
            if ($actual !== ['users' => 3, 'memberships' => 3, 'events' => 3]) {
                throw new \RuntimeException('ODL_PILOT_RECONCILIATION_MISMATCH');
            }
            $this->persistence->commit();
            $started = false;
            return [
                'correlation_id' => $correlation,
                'new' => 3,
                'memberships' => 3,
                'events' => 3,
            ];
        } catch (\Throwable $exception) {
            if ($started) {
                $this->persistence->rollback();
            }
            throw $exception;
        } finally {
            $this->persistence->releaseLock();
        }
    }

    public function rollbackCommitted(
        string $correlationId,
        bool $rollbackAuthorized
    ): int {
        if (!$rollbackAuthorized) {
            throw new \RuntimeException('ODL_PILOT_ROLLBACK_NOT_AUTHORIZED');
        }
        if (preg_match('/^[a-f0-9]{16}$/', $correlationId) !== 1) {
            throw new \RuntimeException('ODL_PILOT_CORRELATION_INVALID');
        }
        if (!$this->persistence->acquireLock()) {
            throw new \RuntimeException('ODL_PILOT_ALREADY_RUNNING');
        }
        $started = false;
        try {
            $this->persistence->begin();
            $started = true;
            $removed = $this->persistence->rollbackCorrelation($correlationId);
            if ($removed !== 3) {
                throw new \RuntimeException('ODL_PILOT_ROLLBACK_COUNT_MISMATCH');
            }
            $this->persistence->commit();
            $started = false;
            return $removed;
        } catch (\Throwable $exception) {
            if ($started) $this->persistence->rollback();
            throw $exception;
        } finally {
            $this->persistence->releaseLock();
        }
    }
}
