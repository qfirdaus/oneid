<?php

declare(strict_types=1);

namespace OneId\App\Auth\UserMfa;

interface UserMfaSessionRevokerInterface
{
    public function revokeAll(string $userId, string $reason): int;
}
