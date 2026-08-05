<?php

declare(strict_types=1);

namespace OneId\App\Sync\Adapters;

use OneId\App\Sync\Contracts\SyncApprovalStoreInterface;
use OneId\App\Sync\DTO\SyncApproval;

/** Process-local, one-use approval storage for one CLI cron invocation. */
final class InMemorySyncApprovalStore implements SyncApprovalStoreInterface
{
    /** @var array<string,SyncApproval> */
    private array $approvals = [];

    public function save(SyncApproval $approval): void
    {
        $this->approvals[$approval->approvalId] = $approval;
    }

    public function consume(string $approvalId): ?SyncApproval
    {
        $approval = $this->approvals[$approvalId] ?? null;
        unset($this->approvals[$approvalId]);
        return $approval;
    }
}
