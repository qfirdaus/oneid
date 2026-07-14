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
