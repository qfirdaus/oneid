<?php

declare(strict_types=1);

namespace OneId\App\Maintenance;

use InvalidArgumentException;
use OneId\App\Auth\UserMfa\UserLoginMfaPolicy;

final class MaintenanceMfaPolicy
{
    public function __construct(
        public readonly string $mode,
        public readonly bool $emailEnabled,
        public readonly bool $totpEnabled,
        public readonly int $pendingTtlSeconds,
        public readonly int $otpTtlSeconds,
        public readonly int $maxAttempts,
        public readonly int $resendCooldownSeconds,
        public readonly int $hourlySendLimit,
        public readonly int $configurationVersion
    ) {
        if (!in_array($mode, ['ENFORCED', 'DISABLED'], true)
            || !$emailEnabled
            || $configurationVersion < 1
        ) {
            throw new InvalidArgumentException('MAINTENANCE_MFA_POLICY_INVALID');
        }
    }

    public function loginPolicy(): UserLoginMfaPolicy
    {
        if ($this->mode !== 'ENFORCED') {
            throw new InvalidArgumentException('MAINTENANCE_MFA_DISABLED');
        }
        return new UserLoginMfaPolicy(
            'ENFORCED',
            UserLoginMfaPolicy::SCOPE_PASSWORD_ONLY,
            $this->emailEnabled,
            $this->totpEnabled,
            $this->pendingTtlSeconds,
            $this->otpTtlSeconds,
            $this->maxAttempts,
            $this->resendCooldownSeconds,
            $this->hourlySendLimit
        );
    }
}
