<?php

namespace OneId\App\Sync\Adapters;

use OneId\App\Sync\Contracts\SyncReconciliationReaderInterface;
use OneId\App\Sync\SyncDatabaseStageException;
use PDOException;

final class DatabaseSyncReconciliationReader implements SyncReconciliationReaderInterface
{
    public function __construct(private object $operation)
    {
    }

    public function changeCounts(int $headerId): array
    {
        try {
            return $this->operation->sync_reconciliation_counts($headerId);
        } catch (PDOException $exception) {
            throw SyncDatabaseStageException::fromPdo('read_reconciliation', $exception);
        }
    }
}
