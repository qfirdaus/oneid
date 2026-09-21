<?php

declare(strict_types=1);

namespace OneId\App\Sync;

final class SourceIdentityCollisionPolicy
{
    /** @param array<string,mixed> $row */
    public static function permits(string $requestedUserId, array $row): bool
    {
        $matchedUserId = (string) ($row['u_id'] ?? '');
        if ($matchedUserId !== '' && hash_equals($matchedUserId, $requestedUserId)) {
            return (int) ($row['same_source'] ?? 0) > 0;
        }

        return $matchedUserId !== ''
            && (int) ($row['avail_status'] ?? 1) === 0
            && hash_equals('external', (string) ($row['account_source'] ?? ''))
            && (int) ($row['sync_protected'] ?? 1) === 0
            && (int) ($row['active_membership'] ?? 1) === 0;
    }
}
