<?php

namespace OneId\App\Sync\Adapters;

use OneId\App\Sync\Contracts\SyncRunLockInterface;

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
        $this->held = $this->operation->sync_acquire_lock($this->lockName, max(0, $waitSeconds));
        return $this->held;
    }

    public function release(): void
    {
        if ($this->held) {
            $this->operation->sync_release_lock($this->lockName);
            $this->held = false;
        }
    }
}
