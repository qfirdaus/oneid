<?php

declare(strict_types=1);

namespace OneId\App\Auth\UserMfa;

use InvalidArgumentException;

final class UserLoginMfaPolicy
{
    public const MODES = ['OFF', 'ENROLLMENT', 'PILOT_ENFORCED', 'ENFORCED'];
    public const SCOPE_PASSWORD_ONLY = 'PASSWORD_ONLY';

    public function __construct(
        public readonly string $mode,
        public readonly string $scope,
        public readonly bool $emailEnabled,
        public readonly bool $totpEnabled,
        public readonly int $pendingTtlSeconds,
        public readonly int $otpTtlSeconds,
        public readonly int $maxAttempts,
        public readonly int $resendCooldownSeconds,
        public readonly int $hourlySendLimit
    ) {
        if (!in_array($mode, self::MODES, true)
            || $scope !== self::SCOPE_PASSWORD_ONLY
            || ($mode !== 'OFF' && !$emailEnabled)
            || $pendingTtlSeconds < 60
            || $pendingTtlSeconds > 900
            || $otpTtlSeconds < 60
            || $otpTtlSeconds > 900
            || $maxAttempts < 1
            || $maxAttempts > 10
            || $resendCooldownSeconds < 30
            || $resendCooldownSeconds > 300
            || $hourlySendLimit < 1
            || $hourlySendLimit > 30
        ) {
            throw new InvalidArgumentException('USER_MFA_POLICY_INVALID');
        }
    }

    public static function committedDefault(): self
    {
        return new self('OFF', self::SCOPE_PASSWORD_ONLY, true, false, 300, 300, 5, 60, 10);
    }

    public function enforced(): bool
    {
        return in_array($this->mode, ['PILOT_ENFORCED', 'ENFORCED'], true);
    }
}
