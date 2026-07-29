<?php

declare(strict_types=1);

namespace OneId\App\Auth\UserMfa;

use PDO;

final class PdoUserMfaPendingLoginPersistence implements UserMfaPendingLoginPersistenceInterface
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly UserMfaAuditWriterInterface $audit
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

    public function createPendingLogin(array $entry): int
    {
        $policyVersion = (int) $this->pdo->query(
            'SELECT configuration_version FROM user_login_mfa_policy
              WHERE singleton_key=1 FOR UPDATE'
        )->fetchColumn();
        $statement = $this->pdo->prepare(
            'INSERT INTO user_login_mfa_transactions(
                transaction_id,u_id,primary_method,transaction_status,
                session_binding_hash,browser_digest,requesting_ip,policy_version,
                expires_at,correlation_id
             ) VALUES(
                :transaction_id,:user_id,:primary_method,\'PENDING\',
                :session_hash,:browser_digest,:ip_address,:policy_version,
                FROM_UNIXTIME(:expires_at_epoch),:correlation_id
             )'
        );
        $statement->execute([
            ':transaction_id' => $entry['transaction_id'],
            ':user_id' => $entry['u_id'],
            ':primary_method' => 'PASSWORD',
            ':session_hash' => $entry['session_binding_hash'],
            ':browser_digest' => $entry['browser_digest'],
            ':ip_address' => $entry['requesting_ip'],
            ':policy_version' => $policyVersion,
            ':expires_at_epoch' => $entry['expires_at_epoch'],
            ':correlation_id' => $entry['correlation_id'],
        ]);
        return $statement->rowCount();
    }

    public function pendingLoginForUpdate(string $transactionId): array|false
    {
        $statement = $this->pdo->prepare(
            'SELECT transaction_id,u_id,primary_method,transaction_status,
                    session_binding_hash,browser_digest,requesting_ip,policy_version,
                    UNIX_TIMESTAMP(expires_at) expires_at_epoch
               FROM user_login_mfa_transactions
              WHERE transaction_id=:transaction_id LIMIT 1 FOR UPDATE'
        );
        $statement->execute([':transaction_id' => $transactionId]);
        return $statement->fetch(PDO::FETCH_ASSOC);
    }

    public function markFactorVerified(string $transactionId, string $factorType): int
    {
        $statement = $this->pdo->prepare(
            "UPDATE user_login_mfa_transactions
                SET transaction_status='VERIFIED'
              WHERE transaction_id=:transaction_id
                AND transaction_status='PENDING' AND expires_at>=NOW(6)"
        );
        $statement->execute([':transaction_id' => $transactionId]);
        return $statement->rowCount();
    }

    public function consumePendingLogin(string $transactionId): int
    {
        $statement = $this->pdo->prepare(
            "UPDATE user_login_mfa_transactions
                SET transaction_status='CONSUMED',consumed_at=NOW(6)
              WHERE transaction_id=:transaction_id
                AND transaction_status='VERIFIED'"
        );
        $statement->execute([':transaction_id' => $transactionId]);
        return $statement->rowCount();
    }

    public function revokePendingLogin(string $transactionId, string $reason): int
    {
        $status = $reason === 'EXPIRED' ? 'EXPIRED' : 'REVOKED';
        $statement = $this->pdo->prepare(
            'UPDATE user_login_mfa_transactions
                SET transaction_status=:status,revoked_at=NOW(6)
              WHERE transaction_id=:transaction_id
                AND transaction_status IN(\'PENDING\',\'VERIFIED\')'
        );
        $statement->execute([
            ':status' => $status,
            ':transaction_id' => $transactionId,
        ]);
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
            $event, $userId, $userId, $outcome, $reason, '',
            $correlationId, $ipAddress
        );
    }
}
