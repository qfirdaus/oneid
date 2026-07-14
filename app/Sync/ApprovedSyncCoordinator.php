<?php

namespace OneId\App\Sync;

use OneId\App\Sync\Contracts\SyncPlanApprovalGateInterface;
use OneId\App\Sync\DTO\SyncRunSummary;

/** Dormant S4C entry seam: approval is mandatory for every writer run. */
final class ApprovedSyncCoordinator
{
    public function __construct(
        private SafeSyncOrchestrator $orchestrator,
        private SyncPlanApprovalGateInterface $approvalGate
    ) {
    }

    public function run(
        string $approvalId,
        string $adminId,
        string $triggeredBy,
        int $lockWaitSeconds = 0,
        ?int $now = null
    ): SyncRunSummary {
        if (preg_match('/^[a-f0-9]{64}$/', $approvalId) !== 1) {
            throw new \RuntimeException('SYNC_APPROVAL_INVALID');
        }
        if (trim($adminId) === '') {
            throw new \RuntimeException('SYNC_APPROVAL_ADMIN_INVALID');
        }

        return $this->orchestrator->runApproved(
            $triggeredBy,
            $approvalId,
            $adminId,
            $this->approvalGate,
            $lockWaitSeconds,
            $now
        );
    }
}
