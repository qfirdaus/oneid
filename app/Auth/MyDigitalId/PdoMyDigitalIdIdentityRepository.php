<?php

declare(strict_types=1);

namespace OneId\App\Auth\MyDigitalId;

use DateTimeImmutable;
use PDO;
use PDOException;
use Throwable;

final class PdoMyDigitalIdIdentityRepository
{
    private const PROVIDER = 'mydigitalid';
    private const ISSUER = 'https://sso.digital-id.my/realms/upnm';
    private const OUTCOMES = ['SUCCESS', 'REJECTED', 'ERROR'];
    private const REASON_CODES = [
        'MYDID_LOGIN_SUCCESS',
        'MYDID_USER_NOT_FOUND',
        'MYDID_USER_INACTIVE',
        'MYDID_IDENTITY_AMBIGUOUS',
        'MYDID_IDENTITY_MISMATCH',
        'MYDID_STATE_INVALID',
        'MYDID_NONCE_INVALID',
        'MYDID_TOKEN_INVALID',
        'MYDID_PROVIDER_ERROR',
        'MYDID_CONFIGURATION_DISABLED',
    ];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function transactional(callable $operation): mixed
    {
        if ($this->pdo->inTransaction()) {
            throw new MyDigitalIdPersistenceException('MYDID_TRANSACTION_ALREADY_ACTIVE');
        }
        $this->pdo->beginTransaction();
        try {
            $result = $operation($this);
            $this->pdo->commit();
            return $result;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** @return null|array<string,mixed> */
    public function findActiveBySubject(string $subjectHmac): ?array
    {
        $this->assertDigest($subjectHmac, 'MYDID_SUBJECT_HMAC_INVALID');
        $statement = $this->pdo->prepare(
            "SELECT identity_id,u_id,provider_code,issuer,subject_hmac,nric_hmac,
                    hmac_key_id,first_verified_at,last_verified_at,last_login_at,
                    login_count
             FROM user_federated_identity
             WHERE provider_code=:provider
               AND issuer=:issuer
               AND subject_hmac=:subject_hmac
               AND identity_status='ACTIVE'
             LIMIT 2"
        );
        $statement->execute([
            ':provider' => self::PROVIDER,
            ':issuer' => self::ISSUER,
            ':subject_hmac' => $subjectHmac,
        ]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) > 1) {
            throw new MyDigitalIdPersistenceException('MYDID_ACTIVE_LINK_AMBIGUOUS');
        }
        return $rows[0] ?? null;
    }

    /** @return null|array<string,mixed> */
    public function findActiveByUser(string $userId): ?array
    {
        $this->assertUserId($userId);
        $statement = $this->pdo->prepare(
            "SELECT identity_id,u_id,provider_code,issuer,subject_hmac,nric_hmac,
                    hmac_key_id,first_verified_at,last_verified_at,last_login_at,
                    login_count
             FROM user_federated_identity
             WHERE provider_code=:provider
               AND u_id=:u_id
               AND identity_status='ACTIVE'
             LIMIT 2
             FOR UPDATE"
        );
        $statement->execute([
            ':provider' => self::PROVIDER,
            ':u_id' => $userId,
        ]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) > 1) {
            throw new MyDigitalIdPersistenceException('MYDID_ACTIVE_LINK_AMBIGUOUS');
        }
        return $rows[0] ?? null;
    }

    public function createActiveLink(
        string $userId,
        string $subjectHmac,
        string $nricHmac,
        string $hmacKeyId,
        DateTimeImmutable $verifiedAt
    ): int {
        $this->assertUserId($userId);
        $this->assertDigest($subjectHmac, 'MYDID_SUBJECT_HMAC_INVALID');
        $this->assertDigest($nricHmac, 'MYDID_NRIC_HMAC_INVALID');
        $this->assertKeyId($hmacKeyId);

        try {
            $statement = $this->pdo->prepare(
                "INSERT INTO user_federated_identity
                    (u_id,provider_code,issuer,subject_hmac,nric_hmac,hmac_key_id,
                     identity_status,first_verified_at,last_verified_at)
                 VALUES
                    (:u_id,:provider,:issuer,:subject_hmac,:nric_hmac,:key_id,
                     'ACTIVE',:first_verified_at,:last_verified_at)"
            );
            $timestamp = $verifiedAt->format('Y-m-d H:i:s.u');
            $statement->execute([
                ':u_id' => $userId,
                ':provider' => self::PROVIDER,
                ':issuer' => self::ISSUER,
                ':subject_hmac' => $subjectHmac,
                ':nric_hmac' => $nricHmac,
                ':key_id' => $hmacKeyId,
                ':first_verified_at' => $timestamp,
                ':last_verified_at' => $timestamp,
            ]);
            $identityId = (int) $this->pdo->lastInsertId();
            if ($identityId < 1 || $statement->rowCount() !== 1) {
                throw new MyDigitalIdPersistenceException('MYDID_LINK_NOT_CREATED');
            }
            return $identityId;
        } catch (MyDigitalIdPersistenceException $exception) {
            throw $exception;
        } catch (PDOException) {
            throw new MyDigitalIdPersistenceException('MYDID_LINK_CREATE_FAILED');
        }
    }

    public function touchSuccessfulLogin(
        int $identityId,
        string $userId,
        string $nricHmac,
        DateTimeImmutable $authenticatedAt
    ): void {
        if ($identityId < 1) {
            throw new MyDigitalIdPersistenceException('MYDID_IDENTITY_ID_INVALID');
        }
        $this->assertUserId($userId);
        $this->assertDigest($nricHmac, 'MYDID_NRIC_HMAC_INVALID');
        $statement = $this->pdo->prepare(
            "UPDATE user_federated_identity
             SET last_verified_at=:verified_at,
                 last_login_at=:login_at,
                 login_count=login_count+1
             WHERE identity_id=:identity_id
               AND u_id=:u_id
               AND nric_hmac=:nric_hmac
               AND identity_status='ACTIVE'"
        );
        $timestamp = $authenticatedAt->format('Y-m-d H:i:s.u');
        $statement->execute([
            ':verified_at' => $timestamp,
            ':login_at' => $timestamp,
            ':identity_id' => $identityId,
            ':u_id' => $userId,
            ':nric_hmac' => $nricHmac,
        ]);
        if ($statement->rowCount() !== 1) {
            throw new MyDigitalIdPersistenceException('MYDID_ACTIVE_LINK_MISMATCH');
        }
    }

    /**
     * @param array{
     *   identity_id?:int|null,u_id?:string|null,outcome:string,reason_code:string,
     *   subject_hmac?:string|null,nric_hmac?:string|null,hmac_key_id?:string|null,
     *   ip_hmac?:string|null,user_agent_hmac?:string|null,session_id_hmac?:string|null,
     *   correlation_id:string,occurred_at:DateTimeImmutable
     * } $event
     */
    public function recordEvent(array $event): int
    {
        $identityId = $event['identity_id'] ?? null;
        if ($identityId !== null && (!is_int($identityId) || $identityId < 1)) {
            throw new MyDigitalIdPersistenceException('MYDID_EVENT_IDENTITY_INVALID');
        }
        $userId = $event['u_id'] ?? null;
        if ($userId !== null) {
            $this->assertUserId($userId);
        }
        $outcome = $event['outcome'] ?? '';
        if (!is_string($outcome) || !in_array($outcome, self::OUTCOMES, true)) {
            throw new MyDigitalIdPersistenceException('MYDID_EVENT_OUTCOME_INVALID');
        }
        $reason = $event['reason_code'] ?? '';
        if (!is_string($reason) || !in_array($reason, self::REASON_CODES, true)) {
            throw new MyDigitalIdPersistenceException('MYDID_EVENT_REASON_INVALID');
        }
        foreach (['subject_hmac', 'nric_hmac', 'ip_hmac', 'user_agent_hmac', 'session_id_hmac'] as $field) {
            if (($event[$field] ?? null) !== null) {
                $this->assertDigest((string) $event[$field], 'MYDID_EVENT_DIGEST_INVALID');
            }
        }
        $keyId = $event['hmac_key_id'] ?? null;
        if ($keyId !== null) {
            $this->assertKeyId((string) $keyId);
        }
        $correlationId = $event['correlation_id'] ?? '';
        if (!is_string($correlationId) || preg_match('/^[a-f0-9]{32}$/D', $correlationId) !== 1) {
            throw new MyDigitalIdPersistenceException('MYDID_EVENT_CORRELATION_INVALID');
        }
        $occurredAt = $event['occurred_at'] ?? null;
        if (!$occurredAt instanceof DateTimeImmutable) {
            throw new MyDigitalIdPersistenceException('MYDID_EVENT_TIME_INVALID');
        }

        try {
            $statement = $this->pdo->prepare(
                'INSERT INTO federated_auth_event
                    (identity_id,u_id,provider_code,outcome,reason_code,
                     subject_hmac,nric_hmac,hmac_key_id,ip_hmac,user_agent_hmac,
                     session_id_hmac,correlation_id,occurred_at)
                 VALUES
                    (:identity_id,:u_id,:provider,:outcome,:reason_code,
                     :subject_hmac,:nric_hmac,:key_id,:ip_hmac,:user_agent_hmac,
                     :session_id_hmac,:correlation_id,:occurred_at)'
            );
            $statement->execute([
                ':identity_id' => $identityId,
                ':u_id' => $userId,
                ':provider' => self::PROVIDER,
                ':outcome' => $outcome,
                ':reason_code' => $reason,
                ':subject_hmac' => $event['subject_hmac'] ?? null,
                ':nric_hmac' => $event['nric_hmac'] ?? null,
                ':key_id' => $keyId,
                ':ip_hmac' => $event['ip_hmac'] ?? null,
                ':user_agent_hmac' => $event['user_agent_hmac'] ?? null,
                ':session_id_hmac' => $event['session_id_hmac'] ?? null,
                ':correlation_id' => $correlationId,
                ':occurred_at' => $occurredAt->format('Y-m-d H:i:s.u'),
            ]);
            $eventId = (int) $this->pdo->lastInsertId();
            if ($eventId < 1 || $statement->rowCount() !== 1) {
                throw new MyDigitalIdPersistenceException('MYDID_EVENT_NOT_RECORDED');
            }
            return $eventId;
        } catch (MyDigitalIdPersistenceException $exception) {
            throw $exception;
        } catch (PDOException) {
            throw new MyDigitalIdPersistenceException('MYDID_EVENT_RECORD_FAILED');
        }
    }

    private function assertUserId(string $userId): void
    {
        if (
            $userId === ''
            || strlen($userId) > 20
            || preg_match('/^[A-Za-z0-9._@-]+$/D', $userId) !== 1
        ) {
            throw new MyDigitalIdPersistenceException('MYDID_USER_ID_INVALID');
        }
    }

    private function assertDigest(string $digest, string $reason): void
    {
        if (preg_match('/^[a-f0-9]{64}$/D', $digest) !== 1) {
            throw new MyDigitalIdPersistenceException($reason);
        }
    }

    private function assertKeyId(string $keyId): void
    {
        if (preg_match('/^[A-Za-z0-9._-]{2,32}$/D', $keyId) !== 1) {
            throw new MyDigitalIdPersistenceException('MYDID_HMAC_KEY_ID_INVALID');
        }
    }
}
