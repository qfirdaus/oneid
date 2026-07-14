<?php

namespace OneId\App\Sync\Contracts;

use OneId\App\Sync\DTO\SyncApproval;
use OneId\App\Sync\DTO\SyncPlan;

/** One-time gate binding an approval to the exact in-memory plan. */
interface SyncPlanApprovalGateInterface
{
    public function consumeAndValidate(
        string $approvalId,
        string $adminId,
        SyncPlan $currentPlan,
        ?int $now = null
    ): SyncApproval;
}
