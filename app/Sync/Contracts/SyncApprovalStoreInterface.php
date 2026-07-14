<?php

namespace OneId\App\Sync\Contracts;

use OneId\App\Sync\DTO\SyncApproval;

interface SyncApprovalStoreInterface
{
    public function save(SyncApproval $approval): void;

    /** Atomically remove and return a pending approval. */
    public function consume(string $approvalId): ?SyncApproval;
}
