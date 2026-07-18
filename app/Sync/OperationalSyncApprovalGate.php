<?php

namespace OneId\App\Sync;

use OneId\App\Sync\Contracts\SyncPlanApprovalGateInterface;
use OneId\App\Sync\DTO\SyncApproval;
use OneId\App\Sync\DTO\SyncPlan;
use RuntimeException;

/** Enforces one-time preview approval and server-derived typed confirmation. */
final class OperationalSyncApprovalGate implements SyncPlanApprovalGateInterface
{
    public function __construct(
        private SyncApprovalService $approvalService,
        private SyncOperationalConfig $config,
        private string $confirmation
    ) {
    }

    public function consumeAndValidate(
        string $approvalId,
        string $adminId,
        SyncPlan $currentPlan,
        ?int $now = null
    ): SyncApproval {
        if (!$this->config->enabled) {
            throw new RuntimeException('SYNC_OPERATIONAL_DISABLED');
        }

        $approval = $this->approvalService->consumeAndValidate(
            $approvalId,
            $adminId,
            $currentPlan,
            $now
        );
        $expected = $this->config->confirmationText(
            $approval->planFingerprint,
            $approval->counts
        );
        if (!hash_equals($expected, trim($this->confirmation))) {
            throw new RuntimeException('SYNC_OPERATIONAL_CONFIRMATION_INVALID');
        }

        return $approval;
    }
}
