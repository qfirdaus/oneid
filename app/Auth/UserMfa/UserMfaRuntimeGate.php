<?php

declare(strict_types=1);

namespace OneId\App\Auth\UserMfa;

use RuntimeException;

final class UserMfaRuntimeGate
{
    public function __construct(
        private readonly string $mode,
        private readonly bool $schemaApplyEnabled,
        private readonly bool $activationAuthorized
    ) {
    }

    public function assertDormantSafe(): void
    {
        if ($this->mode !== 'OFF' || $this->schemaApplyEnabled || $this->activationAuthorized) {
            throw new RuntimeException('USER_MFA_DORMANT_SAFETY_VIOLATION');
        }
    }

    public function assertRequestAllowed(bool $schemaReady): void
    {
        if (!$schemaReady) {
            throw new RuntimeException('USER_MFA_SCHEMA_UNAVAILABLE');
        }
        if (!in_array($this->mode, UserLoginMfaPolicy::MODES, true)) {
            throw new RuntimeException('USER_MFA_MODE_INVALID');
        }
        if ($this->mode !== 'OFF' && !$this->activationAuthorized) {
            throw new RuntimeException('USER_MFA_ACTIVATION_NOT_AUTHORIZED');
        }
    }

    public function assertFeatureActive(): void
    {
        if ($this->mode === 'OFF') {
            throw new RuntimeException('USER_MFA_NOT_ACTIVE');
        }
        if (!$this->activationAuthorized) {
            throw new RuntimeException('USER_MFA_ACTIVATION_NOT_AUTHORIZED');
        }
    }
}
