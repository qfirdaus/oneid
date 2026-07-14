<?php

namespace OneId\App\Sync;

use OneId\App\Sync\DTO\SyncSafetyDecision;
use RuntimeException;

final class SyncSafetyViolation extends RuntimeException
{
    public function __construct(public readonly SyncSafetyDecision $decision)
    {
        parent::__construct('SYNC_SAFETY_BLOCKED');
    }
}
