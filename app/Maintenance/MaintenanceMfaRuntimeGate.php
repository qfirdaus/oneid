<?php

declare(strict_types=1);

namespace OneId\App\Maintenance;

use RuntimeException;

final class MaintenanceMfaRuntimeGate
{
    public function __construct(
        private readonly bool $enabled,
        private readonly bool $activationAuthorized,
        private readonly bool $emailEnabled,
        private readonly bool $totpEnabled
    ) {
    }

    public function assertAvailable(bool $schemaReady, ?MaintenanceMfaPolicy $policy = null): void
    {
        if (!$schemaReady) {
            throw new RuntimeException('MAINTENANCE_MFA_SCHEMA_UNAVAILABLE');
        }
        if (!$this->enabled || !$this->activationAuthorized || !$this->emailEnabled) {
            throw new RuntimeException('MAINTENANCE_MFA_RUNTIME_UNAVAILABLE');
        }
        if ($policy !== null && ($policy->mode !== 'ENFORCED'
            || !$policy->emailEnabled
            || ($policy->totpEnabled && !$this->totpEnabled)
        )) {
            throw new RuntimeException('MAINTENANCE_MFA_RUNTIME_DATABASE_MISMATCH');
        }
    }
}
