<?php

namespace OneId\App\Sync\Adapters;

use OneId\App\Sync\Contracts\SyncRunLockInterface;
use OneId\App\Sync\SyncDatabaseStageException;
use PDOException;

final class DatabaseSyncRunLock implements SyncRunLockInterface
{
    private bool $held = false;

    public function __construct(
        private object $operation,
        private string $lockName = 'oneid:external-user-sync'
    ) {
    }

    public function acquire(int $waitSeconds = 0): bool
    {
        try {
            $this->held = $this->operation->sync_acquire_lock($this->lockName, max(0, $waitSeconds));
        } catch (PDOException $exception) {
            throw SyncDatabaseStageException::fromPdo('acquire_sync_lock', $exception);
        }
        return $this->held;
    }

    public function release(): void
    {
        if ($this->held) {
            try {
                $this->operation->sync_release_lock($this->lockName);
            } catch (PDOException $exception) {
                throw SyncDatabaseStageException::fromPdo('release_sync_lock', $exception);
            }
            $this->held = false;
        }
    }
}
