<?php

declare(strict_types=1);

namespace OneId\App\Auth\UserMfa;

use RuntimeException;

final class LegacyUserMfaSessionRevoker implements UserMfaSessionRevokerInterface
{
    public function __construct(private readonly object $operation)
    {
    }

    public function revokeAll(string $userId, string $reason): int
    {
        if (!method_exists($this->operation, 'update_whole_token_status')) {
            throw new RuntimeException('USER_MFA_SESSION_REVOCATION_UNAVAILABLE');
        }
        return (int) $this->operation->update_whole_token_status($userId, 0);
    }
}
