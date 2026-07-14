<?php

namespace OneId\App\Sync\Contracts;

interface SyncRunLockInterface
{
    public function acquire(int $waitSeconds = 0): bool;

    public function release(): void;
}
