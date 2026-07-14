<?php

namespace OneId\App\Sync\Adapters;

use OneId\App\Sync\Contracts\SyncReconciliationReaderInterface;

final class DatabaseSyncReconciliationReader implements SyncReconciliationReaderInterface
{
    public function __construct(private object $operation)
    {
    }

    public function changeCounts(int $headerId): array
    {
        return $this->operation->sync_reconciliation_counts($headerId);
    }
}
