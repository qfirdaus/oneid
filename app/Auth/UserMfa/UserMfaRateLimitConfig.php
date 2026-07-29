<?php

declare(strict_types=1);

namespace OneId\App\Auth\UserMfa;

use InvalidArgumentException;

final class UserMfaRateLimitConfig
{
    public function __construct(
        public readonly int $userHourly = 10,
        public readonly int $sessionHourly = 10,
        public readonly int $ipHourly = 50,
        public readonly int $destinationHourly = 10,
        public readonly int $resendCooldownSeconds = 60
    ) {
        if ($userHourly < 1
            || $userHourly > 30
            || $sessionHourly < 1
            || $sessionHourly > 30
            || $ipHourly < $sessionHourly
            || $ipHourly > 200
            || $destinationHourly < 1
            || $destinationHourly > 30
            || $resendCooldownSeconds < 30
            || $resendCooldownSeconds > 300
        ) {
            throw new InvalidArgumentException('USER_MFA_RATE_LIMIT_CONFIGURATION_INVALID');
        }
    }

    /** @param array<string, mixed> $stats */
    public function exceeded(array $stats): bool
    {
        return (int) ($stats['user_hour'] ?? 0) >= $this->userHourly
            || (int) ($stats['session_hour'] ?? 0) >= $this->sessionHourly
            || (int) ($stats['ip_hour'] ?? 0) >= $this->ipHourly
            || (int) ($stats['destination_hour'] ?? 0) >= $this->destinationHourly;
    }

    /** @param array<string, mixed> $stats */
    public function cooldownActive(array $stats): bool
    {
        return (int) ($stats['cooldown_seconds'] ?? 0) > 0;
    }
}
