<?php

namespace OneId\App\User\Adapters;

use OneId\App\User\Contracts\UserResyncApprovalStoreInterface;
use RuntimeException;

final class SessionUserResyncApprovalStore implements UserResyncApprovalStoreInterface
{
    private const SESSION_KEY = 'oneid_user_resync_approvals';

    public function save(array $approval): void
    {
        $this->requireSession();
        $approvalId = (string) ($approval['approval_id'] ?? '');
        if (preg_match('/^[a-f0-9]{64}$/', $approvalId) !== 1) {
            throw new RuntimeException('RESYNC_APPROVAL_INVALID');
        }

        $now = time();
        $records = is_array($_SESSION[self::SESSION_KEY] ?? null)
            ? $_SESSION[self::SESSION_KEY]
            : [];
        foreach ($records as $id => $record) {
            if (!is_array($record) || (int) ($record['expires_at'] ?? 0) <= $now) {
                unset($records[$id]);
            }
        }
        // Only the latest preview for an admin/user combination remains usable.
        foreach ($records as $id => $record) {
            if (($record['admin_id'] ?? null) === ($approval['admin_id'] ?? null)
                && ($record['user_id'] ?? null) === ($approval['user_id'] ?? null)
            ) {
                unset($records[$id]);
            }
        }
        $records[$approvalId] = $approval;
        $_SESSION[self::SESSION_KEY] = $records;
    }

    public function consume(string $approvalId): ?array
    {
        $this->requireSession();
        $records = is_array($_SESSION[self::SESSION_KEY] ?? null)
            ? $_SESSION[self::SESSION_KEY]
            : [];
        $approval = $records[$approvalId] ?? null;
        unset($records[$approvalId]);
        $_SESSION[self::SESSION_KEY] = $records;
        return is_array($approval) ? $approval : null;
    }

    private function requireSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new RuntimeException('RESYNC_SESSION_REQUIRED');
        }
    }
}
