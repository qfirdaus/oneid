<?php

namespace OneId\App\Sync;

use OneId\App\Sync\Contracts\SyncPlanApprovalGateInterface;
use OneId\App\Sync\DTO\SyncApproval;
use OneId\App\Sync\DTO\SyncPlan;

/** Revalidates the approved full plan against private runtime expectations. */
final class FullSyncApprovalGate implements SyncPlanApprovalGateInterface
{
    public function __construct(
        private SyncApprovalService $approvalService,
        private SyncFullConfig $config,
        private SyncPlanFingerprinter $fingerprinter
    ) {
    }

    public function consumeAndValidate(
        string $approvalId,
        string $adminId,
        SyncPlan $currentPlan,
        ?int $now = null
    ): SyncApproval {
        $this->config->assertPlan($currentPlan, $this->fingerprinter);

        return $this->approvalService->consumeAndValidate(
            $approvalId,
            $adminId,
            $currentPlan,
            $now
        );
    }
}
