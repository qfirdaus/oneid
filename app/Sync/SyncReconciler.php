<?php

namespace OneId\App\Sync;

use RuntimeException;

final class SyncReconciler
{
    /**
     * @param array{New:int,Update:int,Deactivate:int,Reactivate:int} $planned
     * @param array{New:int,Update:int,Deactivate:int,Reactivate:int} $executed
     * @param array{New:int,Update:int,Deactivate:int,Reactivate:int} $audited
     */
    public function assertMatched(array $planned, array $executed, array $audited): void
    {
        if ($planned !== $executed || $planned !== $audited) {
            throw new RuntimeException('SYNC_RECONCILIATION_MISMATCH');
        }
    }
}
