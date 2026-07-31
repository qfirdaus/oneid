<?php

declare(strict_types=1);

namespace OneId\App\Auth\UserMfa;

use PDO;
use RuntimeException;

final class PdoUserMfaPolicyReader
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function policy(): UserLoginMfaPolicy
    {
        $row = $this->pdo->query(
            'SELECT policy_mode,login_scope,email_enabled,totp_enabled,
                    pending_ttl_seconds,otp_ttl_seconds,max_attempts,
                    resend_cooldown_seconds,hourly_send_limit
               FROM user_login_mfa_policy WHERE singleton_key=1'
        )->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new RuntimeException('USER_MFA_POLICY_UNAVAILABLE');
        }
        return new UserLoginMfaPolicy(
            (string) $row['policy_mode'],
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
        $runtimeMode = strtoupper(trim($runtimeMode));
        $databaseMode = $this->policy()->mode;
        if ($databaseMode !== 'OFF' && !hash_equals($databaseMode, $runtimeMode)) {
            throw new RuntimeException('USER_MFA_RUNTIME_DATABASE_POLICY_MISMATCH');
        }
    }
}
