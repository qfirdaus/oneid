<?php

declare(strict_types=1);

namespace OneId\App\Auth\UserMfa;

use PDO;

final class PdoUserMfaEmailOtpPersistence implements UserMfaEmailOtpPersistenceInterface
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly UserMfaAuditWriterInterface $audit
    ) {
    }

    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo->commit();
    }

    public function rollback(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    public function emailChallengeContextForUpdate(string $transactionId, string $userId): array|false
    {
        $statement = $this->pdo->prepare(
            'SELECT t.transaction_status,t.session_binding_hash,t.browser_digest,
                    UNIX_TIMESTAMP(t.expires_at) expires_at_epoch,
                    p.email_enabled,p.otp_ttl_seconds,p.max_attempts,
                    u.data5 email,u.data1 display_name
               FROM user_login_mfa_transactions t
               JOIN user_login_mfa_policy p ON p.singleton_key=1
               JOIN user_tbl u ON u.u_id=t.u_id
              WHERE t.transaction_id=:transaction_id AND t.u_id=:user_id
              LIMIT 1 FOR UPDATE'
        );
        $statement->execute([
            ':transaction_id' => $transactionId,
            ':user_id' => $userId,
        ]);
        return $statement->fetch(PDO::FETCH_ASSOC);
    }

    public function emailRequestStatsForUpdate(
        string $userId,
        string $sessionHash,
        string $ipAddress,
        string $destinationHmac
    ): array {
        // The singleton row is the serialization lock for request counters.
        $this->pdo->query(
            'SELECT singleton_key FROM user_login_mfa_policy
              WHERE singleton_key=1 FOR UPDATE'
        )->fetchColumn();
        $statement = $this->pdo->prepare(
            "SELECT
                SUM(CASE WHEN c.u_id=:user_id THEN 1 ELSE 0 END) user_hour,
                SUM(CASE WHEN t.session_binding_hash=:session_hash THEN 1 ELSE 0 END) session_hour,
                SUM(CASE WHEN t.requesting_ip=:ip_address THEN 1 ELSE 0 END) ip_hour,
                SUM(CASE WHEN c.destination_hmac=:destination_hmac THEN 1 ELSE 0 END) destination_hour,
                GREATEST(
                    0,
                    60-TIMESTAMPDIFF(
                        SECOND,
                        MAX(CASE WHEN c.destination_hmac=:destination_hmac_2 THEN c.created_at END),
                        NOW()
                    )
                ) cooldown_seconds
               FROM user_login_mfa_challenges c
               JOIN user_login_mfa_transactions t ON t.transaction_id=c.transaction_id
              WHERE c.factor_type='EMAIL_OTP'
                AND c.created_at>=DATE_SUB(NOW(),INTERVAL 1 HOUR)"
        );
        $statement->execute([
            ':user_id' => $userId,
            ':session_hash' => $sessionHash,
            ':ip_address' => $ipAddress,
            ':destination_hmac' => $destinationHmac,
            ':destination_hmac_2' => $destinationHmac,
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'user_hour' => (int) ($row['user_hour'] ?? 0),
            'session_hour' => (int) ($row['session_hour'] ?? 0),
            'ip_hour' => (int) ($row['ip_hour'] ?? 0),
            'destination_hour' => (int) ($row['destination_hour'] ?? 0),
            'cooldown_seconds' => (int) ($row['cooldown_seconds'] ?? 0),
        ];
    }

    public function revokeOpenEmailChallenges(string $transactionId): int
    {
        $statement = $this->pdo->prepare(
            "UPDATE user_login_mfa_challenges
                SET revoked_at=NOW(6),otp_hash=NULL
              WHERE transaction_id=:transaction_id
                AND factor_type='EMAIL_OTP'
                AND consumed_at IS NULL AND revoked_at IS NULL"
        );
        $statement->execute([':transaction_id' => $transactionId]);
        return $statement->rowCount();
    }

    public function createEmailChallenge(array $entry): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO user_login_mfa_challenges(
                challenge_id,transaction_id,u_id,factor_type,otp_hash,
                destination_hmac,max_attempts,expires_at,correlation_id
             ) VALUES(
                :challenge_id,:transaction_id,:user_id,:factor_type,:otp_hash,
                :destination_hmac,:max_attempts,FROM_UNIXTIME(:expires_at_epoch),
                :correlation_id
             )'
        );
        $statement->execute([
            ':challenge_id' => $entry['challenge_id'],
            ':transaction_id' => $entry['transaction_id'],
            ':user_id' => $entry['u_id'],
            ':factor_type' => 'EMAIL_OTP',
            ':otp_hash' => $entry['otp_hash'],
            ':destination_hmac' => $entry['destination_hmac'],
            ':max_attempts' => $entry['max_attempts'],
            ':expires_at_epoch' => $entry['expires_at_epoch'],
            ':correlation_id' => $entry['correlation_id'],
        ]);
        return $statement->rowCount();
    }

    public function markChallengeSent(string $challengeId): int
    {
        $statement = $this->pdo->prepare(
            'UPDATE user_login_mfa_challenges SET sent_at=NOW(6)
              WHERE challenge_id=:challenge_id AND sent_at IS NULL
                AND consumed_at IS NULL AND revoked_at IS NULL'
        );
        $statement->execute([':challenge_id' => $challengeId]);
        return $statement->rowCount();
    }

    public function revokeChallenge(string $challengeId, string $reason): int
    {
        $statement = $this->pdo->prepare(
            'UPDATE user_login_mfa_challenges
                SET revoked_at=COALESCE(revoked_at,NOW(6)),otp_hash=NULL
              WHERE challenge_id=:challenge_id AND consumed_at IS NULL'
        );
        $statement->execute([':challenge_id' => $challengeId]);
        return $statement->rowCount();
    }

    public function emailChallengeForUpdate(string $challengeId): array|false
    {
        $statement = $this->pdo->prepare(
            'SELECT c.challenge_id,c.transaction_id,c.u_id,c.factor_type,
                    c.otp_hash,c.attempts,c.max_attempts,c.consumed_at,c.revoked_at,
                    UNIX_TIMESTAMP(c.expires_at) expires_at_epoch,
                    t.transaction_status,t.session_binding_hash,t.browser_digest
               FROM user_login_mfa_challenges c
               JOIN user_login_mfa_transactions t ON t.transaction_id=c.transaction_id
              WHERE c.challenge_id=:challenge_id LIMIT 1 FOR UPDATE'
        );
        $statement->execute([':challenge_id' => $challengeId]);
        return $statement->fetch(PDO::FETCH_ASSOC);
    }

    public function recordFailedAttempt(string $challengeId): int
    {
        $statement = $this->pdo->prepare(
            'UPDATE user_login_mfa_challenges
                SET attempts=attempts+1,
                    revoked_at=CASE WHEN attempts+1>=max_attempts THEN NOW(6) ELSE revoked_at END,
                    otp_hash=CASE WHEN attempts+1>=max_attempts THEN NULL ELSE otp_hash END
              WHERE challenge_id=:challenge_id
                AND attempts<max_attempts
                AND consumed_at IS NULL AND revoked_at IS NULL'
        );
        $statement->execute([':challenge_id' => $challengeId]);
        return $statement->rowCount();
    }

    public function consumeEmailChallenge(string $challengeId): int
    {
        $statement = $this->pdo->prepare(
            'UPDATE user_login_mfa_challenges
                SET consumed_at=NOW(6),otp_hash=NULL
              WHERE challenge_id=:challenge_id
                AND consumed_at IS NULL AND revoked_at IS NULL'
        );
        $statement->execute([':challenge_id' => $challengeId]);
        return $statement->rowCount();
    }

    public function markPendingLoginVerified(string $transactionId, string $factorType): int
    {
        $statement = $this->pdo->prepare(
            "UPDATE user_login_mfa_transactions
                SET transaction_status='VERIFIED'
              WHERE transaction_id=:transaction_id
                AND transaction_status='PENDING'
                AND expires_at>=NOW(6)"
        );
        $statement->execute([':transaction_id' => $transactionId]);
        return $statement->rowCount();
    }

    public function recordAudit(
        string $event,
        string $userId,
        string $outcome,
        string $reason,
        string $correlationId,
        string $ipAddress
    ): int {
        return $this->audit->write(
            $event,
            $userId,
            $userId,
            $outcome,
            $reason,
            '',
            $correlationId,
            $ipAddress
        );
    }
}
