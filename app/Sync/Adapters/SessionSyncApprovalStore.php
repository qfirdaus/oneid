<?php

namespace OneId\App\Sync\Adapters;

use OneId\App\Sync\Contracts\SyncApprovalStoreInterface;
use OneId\App\Sync\DTO\SyncApproval;
use RuntimeException;

/** Server-side PHP session store. Dormant until an S4 runtime endpoint exists. */
final class SessionSyncApprovalStore implements SyncApprovalStoreInterface
{
    private const SESSION_KEY = 'oneid_sync_approvals';

    public function __construct(private int $maxPending = 5)
    {
        if ($this->maxPending < 1 || $this->maxPending > 20) {
            throw new RuntimeException('SYNC_APPROVAL_STORE_LIMIT_INVALID');
        }
    }

    public function save(SyncApproval $approval): void
    {
        $this->requireSession();
        $records = is_array($_SESSION[self::SESSION_KEY] ?? null)
            ? $_SESSION[self::SESSION_KEY]
            : [];
        $now = time();
        foreach ($records as $id => $record) {
            if (!is_array($record) || (int) ($record['expires_at'] ?? 0) <= $now) {
                unset($records[$id]);
            }
        }
        while (count($records) >= $this->maxPending) {
            array_shift($records);
        }
        $records[$approval->approvalId] = $approval->toStorageArray();
        $_SESSION[self::SESSION_KEY] = $records;
    }

    public function consume(string $approvalId): ?SyncApproval
    {
        $this->requireSession();
        $records = is_array($_SESSION[self::SESSION_KEY] ?? null)
            ? $_SESSION[self::SESSION_KEY]
            : [];
        $record = $records[$approvalId] ?? null;
        unset($records[$approvalId]);
        $_SESSION[self::SESSION_KEY] = $records;

        if ($record === null) {
            return null;
        }
        if (!is_array($record)) {
            throw new RuntimeException('SYNC_APPROVAL_CORRUPT');
        }
        return SyncApproval::fromStorageArray($record);
    }

    private function requireSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new RuntimeException('SYNC_APPROVAL_SESSION_REQUIRED');
        }
    }
}
