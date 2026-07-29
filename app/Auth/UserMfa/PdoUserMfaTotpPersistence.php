<?php

declare(strict_types=1);

namespace OneId\App\Auth\UserMfa;

use PDO;

final class PdoUserMfaTotpPersistence implements UserMfaTotpPersistenceInterface
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly UserMfaAuditWriterInterface $audit,
        private readonly UserMfaSessionRevokerInterface $sessions
    ) {
    }

    public function beginTransaction(): void { $this->pdo->beginTransaction(); }
    public function commit(): void { $this->pdo->commit(); }
    public function rollback(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    public function policyForUpdate(): array|false
    {
        return $this->pdo->query(
            'SELECT policy_mode,email_enabled,totp_enabled,configuration_version
               FROM user_login_mfa_policy WHERE singleton_key=1 FOR UPDATE'
        )->fetch(PDO::FETCH_ASSOC);
    }

    public function pendingFactorForUpdate(string $factorId, string $userId): array|false
    {
        $statement = $this->pdo->prepare(
            "SELECT factor_id,u_id,encrypted_secret,secret_nonce,key_version,
                    factor_status,enrollment_session_hash,enrollment_browser_digest,
                    last_used_time_step
               FROM user_mfa_factors
              WHERE factor_id=:factor_id AND u_id=:user_id
                AND factor_type='TOTP' AND factor_status='PENDING'
              LIMIT 1 FOR UPDATE"
        );
        $statement->execute([':factor_id' => $factorId, ':user_id' => $userId]);
        return $statement->fetch(PDO::FETCH_ASSOC);
    }

    public function activeFactorForUpdate(string $userId): array|false
    {
        $statement = $this->pdo->prepare(
            "SELECT factor_id,u_id,encrypted_secret,secret_nonce,key_version,
                    factor_status,last_used_time_step
               FROM user_mfa_factors
              WHERE u_id=:user_id AND factor_type='TOTP' AND factor_status='ACTIVE'
              LIMIT 1 FOR UPDATE"
        );
        $statement->execute([':user_id' => $userId]);
        return $statement->fetch(PDO::FETCH_ASSOC);
    }

    public function createPendingFactor(array $factor): string|false
    {
        $statement = $this->pdo->prepare(
            "INSERT INTO user_mfa_factors(
                u_id,factor_type,encrypted_secret,secret_nonce,key_version,
                factor_status,device_label,enrollment_session_hash,
                enrollment_browser_digest,correlation_id
             ) VALUES(
                :user_id,'TOTP',:encrypted_secret,:secret_nonce,:key_version,
                'PENDING',:device_label,:session_hash,:browser_digest,:correlation_id
             )"
        );
        $statement->execute([
            ':user_id' => $factor['u_id'],
            ':encrypted_secret' => $factor['encrypted_secret'],
            ':secret_nonce' => $factor['secret_nonce'],
            ':key_version' => $factor['key_version'],
            ':device_label' => $factor['device_label'],
            ':session_hash' => $factor['enrollment_session_hash'],
            ':browser_digest' => $factor['enrollment_browser_digest'],
            ':correlation_id' => $factor['correlation_id'],
        ]);
        return $statement->rowCount() === 1 ? $this->pdo->lastInsertId() : false;
    }

    public function confirmFactor(string $factorId, int $timeStep): int
    {
        $statement = $this->pdo->prepare(
            "UPDATE user_mfa_factors
                SET factor_status='ACTIVE',confirmed_at=NOW(6),
                    last_used_time_step=:time_step,last_used_at=NOW(6)
              WHERE factor_id=:factor_id AND factor_status='PENDING'"
        );
        $statement->execute([':time_step' => $timeStep, ':factor_id' => $factorId]);
        return $statement->rowCount();
    }

    public function updateLastUsedStep(string $factorId, int $expectedPreviousStep, int $newStep): int
    {
        $condition = $expectedPreviousStep < 0
            ? 'last_used_time_step IS NULL'
            : 'last_used_time_step=:expected_step';
        $statement = $this->pdo->prepare(
            "UPDATE user_mfa_factors
                SET last_used_time_step=:new_step,last_used_at=NOW(6)
              WHERE factor_id=:factor_id AND factor_status='ACTIVE'
                AND {$condition} AND :new_step_compare>COALESCE(last_used_time_step,-1)"
        );
        $parameters = [
            ':new_step' => $newStep,
            ':new_step_compare' => $newStep,
            ':factor_id' => $factorId,
        ];
        if ($expectedPreviousStep >= 0) {
            $parameters[':expected_step'] = $expectedPreviousStep;
        }
        $statement->execute($parameters);
        return $statement->rowCount();
    }

    public function setPreference(string $userId, string $factorType, string $correlationId): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO user_mfa_preferences(
                u_id,preferred_factor,configuration_version,correlation_id
             ) VALUES(:user_id,:factor_type,1,:correlation_id)
             ON DUPLICATE KEY UPDATE
                preferred_factor=VALUES(preferred_factor),
                configuration_version=configuration_version+1,
                correlation_id=VALUES(correlation_id)'
        );
        $statement->execute([
            ':user_id' => $userId,
            ':factor_type' => $factorType,
            ':correlation_id' => $correlationId,
        ]);
        return $statement->rowCount() > 0 ? 1 : 0;
    }

    public function revokeFactors(string $userId, string $reason): int
    {
        $statement = $this->pdo->prepare(
            "UPDATE user_mfa_factors
                SET factor_status='REVOKED',revoked_at=NOW(6)
              WHERE u_id=:user_id AND factor_type='TOTP'
                AND factor_status IN('PENDING','ACTIVE')"
        );
        $statement->execute([':user_id' => $userId]);
        return $statement->rowCount();
    }

    public function revokePendingChallenges(string $userId, string $reason): int
    {
        $statement = $this->pdo->prepare(
            'UPDATE user_login_mfa_challenges
                SET revoked_at=NOW(6),
                    otp_hash=CASE WHEN factor_type=\'EMAIL_OTP\' THEN NULL ELSE otp_hash END
              WHERE u_id=:user_id AND consumed_at IS NULL AND revoked_at IS NULL'
        );
        $statement->execute([':user_id' => $userId]);
        return $statement->rowCount();
    }

    public function revokeUserSessions(string $userId, string $reason): int
    {
        return $this->sessions->revokeAll($userId, $reason);
    }

    public function recordAudit(
        string $event,
        string $targetUserId,
        string $actorUserId,
        string $outcome,
        string $reason,
        string $reference,
        string $correlationId
    ): int {
        return $this->audit->write(
            $event, $targetUserId, $actorUserId, $outcome, $reason,
            $reference, $correlationId, '0.0.0.0'
        );
    }
}
