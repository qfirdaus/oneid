<?php

declare(strict_types=1);

namespace OneId\App\Auth\UserMfa;

use PDO;
use RuntimeException;

require_once __DIR__ . '/UserMfaOperationalModeResolver.php';

final class PdoUserMfaPolicyReader
{
    private readonly UserMfaOperationalModeResolver $modes;
    private readonly string $environment;

    public function __construct(private readonly PDO $pdo, ?string $environment = null)
    {
        $this->modes = new UserMfaOperationalModeResolver();
        $environment = strtolower(trim((string) ($environment ?? (function_exists('oneid_config') ? \oneid_config('ONEID_ENVIRONMENT', '') : ''))));
        $this->environment = in_array($environment, ['local','staging','production'], true) ? $environment : '';
    }

    public function policy(): UserLoginMfaPolicy
    {
        $row = $this->pdo->query(
            'SELECT policy_mode,login_scope,email_enabled,totp_enabled,
                    pending_ttl_seconds,otp_ttl_seconds,max_attempts,
                    resend_cooldown_seconds,hourly_send_limit,configuration_version
               FROM user_login_mfa_policy WHERE singleton_key=1'
        )->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new RuntimeException('USER_MFA_POLICY_UNAVAILABLE');
        }
        $state = $this->operationalState((string) $row['policy_mode'], (int) $row['configuration_version']);
        return new UserLoginMfaPolicy(
            $state['effective_mode'],
            (string) $row['login_scope'],
            (bool) $row['email_enabled'],
            (bool) $row['totp_enabled'],
            (int) $row['pending_ttl_seconds'],
            (int) $row['otp_ttl_seconds'],
            (int) $row['max_attempts'],
            (int) $row['resend_cooldown_seconds'],
            (int) $row['hourly_send_limit']
        );
    }

    public function pilotEligible(string $userId): bool
    {
        if (preg_match('/\A[A-Za-z0-9_.@-]{1,20}\z/', $userId) !== 1) {
            return false;
        }
        $statement = $this->pdo->prepare(
            "SELECT COUNT(*) FROM user_login_mfa_pilot_users
              WHERE u_id=:user_id AND pilot_status='ACTIVE'"
        );
        $statement->execute([':user_id' => $userId]);
        return (int) $statement->fetchColumn() === 1;
    }

    public function selfServiceEligible(string $userId): bool
    {
        if (preg_match('/\A[A-Za-z0-9_.@-]{1,20}\z/', $userId) !== 1) {
            return false;
        }
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM user_tbl
              WHERE u_id=:user_id AND u_type=0 AND avail_status=1'
        );
        $statement->execute([':user_id' => $userId]);
        return (int) $statement->fetchColumn() === 1;
    }

    public function categoryEnforced(string $userId): bool
    {
        if (preg_match('/\A[A-Za-z0-9_.@-]{1,20}\z/', $userId) !== 1) {
            return true;
        }
        try {
            $statement = $this->pdo->prepare(
                "SELECT DISTINCT s.source_family
                   FROM user_external_identity i
                   JOIN external_source s ON s.source_code=i.source_code
                  WHERE i.u_id=:user AND i.source_active=1
                    AND s.source_family IN ('staff','student')"
            );
            $statement->execute([':user' => $userId]);
            $families = $statement->fetchAll(PDO::FETCH_COLUMN);
            if (count($families) !== 1) {
                return true;
            }
            $category = $families[0] === 'staff' ? 'STAFF' : 'STUDENT';
            $policy = $this->pdo->prepare(
                'SELECT enforcement_enabled
                   FROM user_login_mfa_category_policy
                  WHERE category_code=:category'
            );
            $policy->execute([':category' => $category]);
            $enabled = $policy->fetchColumn();
            return $enabled === false ? true : (int) $enabled === 1;
        } catch (\Throwable) {
            return true;
        }
    }

    public function temporarilyExempt(string $userId): bool
    {
        if (preg_match('/\A[A-Za-z0-9_.@-]{1,20}\z/', $userId) !== 1) {
            return false;
        }
        try {
            $statement = $this->pdo->prepare(
                "SELECT COUNT(*)
                   FROM user_login_mfa_exemptions e
                   JOIN user_tbl u ON u.u_id=e.u_id
                  WHERE e.u_id=:user AND u.u_type=0 AND u.avail_status=1
                    AND e.exemption_status='ACTIVE'
                    AND e.starts_at<=NOW(6) AND e.expires_at>NOW(6)"
            );
            $statement->execute([':user' => $userId]);
            return (int) $statement->fetchColumn() === 1;
        } catch (\Throwable) {
            // Missing/unavailable exemption state must never create a bypass.
            return false;
        }
    }

    public function assertRuntimeParity(string $runtimeMode): void
    {
        $this->modes->assertWithinRuntimeCeiling($this->policy()->mode, $runtimeMode);
    }

    /** @return array<string,mixed> */
    public function operationalState(?string $storedMode = null, ?int $storedVersion = null): array
    {
        if ($storedMode === null) {
            $policy = $this->pdo->query(
                'SELECT policy_mode,configuration_version FROM user_login_mfa_policy WHERE singleton_key=1'
            )->fetch(PDO::FETCH_ASSOC);
            $storedMode = is_array($policy) ? (string) $policy['policy_mode'] : '';
            $storedVersion = is_array($policy) ? (int) $policy['configuration_version'] : null;
        }
        $bypass = null;
        if (strtoupper($storedMode) === 'EMERGENCY_BYPASS') {
            if ($this->environment === '') {
                return $this->modes->resolve($storedMode, null, (int) $this->pdo->query('SELECT UNIX_TIMESTAMP(NOW(6))')->fetchColumn(), $storedVersion);
            }
            $query = $this->pdo->prepare(
                "SELECT request_id,requested_mode,restore_mode,request_status,starts_at,expires_at,applied_policy_version,
                        UNIX_TIMESTAMP(starts_at) starts_at_epoch,UNIX_TIMESTAMP(expires_at) expires_at_epoch
                   FROM user_mfa_policy_change_requests
                  WHERE environment=:environment
                    AND requested_mode='EMERGENCY_BYPASS' AND request_status='ACTIVE'
                  ORDER BY activated_at DESC,request_id DESC LIMIT 1"
            );
            $query->execute([':environment' => $this->environment]);
            $candidate = $query->fetch(PDO::FETCH_ASSOC);
            $bypass = is_array($candidate) ? $candidate : null;
        }
        $databaseEpoch = (int) $this->pdo->query('SELECT UNIX_TIMESTAMP(NOW(6))')->fetchColumn();
        return $this->modes->resolve($storedMode, $bypass, $databaseEpoch, $storedVersion);
    }
}
