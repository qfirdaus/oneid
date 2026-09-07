<?php

declare(strict_types=1);

namespace OneId\App\Maintenance;

use PDO;
use RuntimeException;

final class PdoMaintenanceMfaPolicyReader
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function policy(): MaintenanceMfaPolicy
    {
        $row = $this->pdo->query(
            'SELECT policy_mode,email_enabled,totp_enabled,pending_ttl_seconds,
                    otp_ttl_seconds,max_attempts,resend_cooldown_seconds,
                    hourly_send_limit,configuration_version
               FROM maintenance_mfa_policy WHERE singleton_key=1'
        )->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new RuntimeException('MAINTENANCE_MFA_POLICY_UNAVAILABLE');
        }
        return new MaintenanceMfaPolicy(
            (string) $row['policy_mode'],
            (bool) $row['email_enabled'],
            (bool) $row['totp_enabled'],
            (int) $row['pending_ttl_seconds'],
            (int) $row['otp_ttl_seconds'],
            (int) $row['max_attempts'],
            (int) $row['resend_cooldown_seconds'],
            (int) $row['hourly_send_limit'],
            (int) $row['configuration_version']
        );
    }
}
