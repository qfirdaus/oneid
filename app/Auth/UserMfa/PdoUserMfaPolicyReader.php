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

    public function assertRuntimeParity(string $runtimeMode): void
    {
        if (!hash_equals($this->policy()->mode, strtoupper(trim($runtimeMode)))) {
            throw new RuntimeException('USER_MFA_RUNTIME_DATABASE_POLICY_MISMATCH');
        }
    }
}
