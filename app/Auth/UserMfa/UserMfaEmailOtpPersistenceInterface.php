<?php

declare(strict_types=1);

namespace OneId\App\Auth\UserMfa;

interface UserMfaEmailOtpPersistenceInterface
{
    public function beginTransaction(): void;

    public function commit(): void;

    public function rollback(): void;

    /** @return array<string, mixed>|false */
    public function emailChallengeContextForUpdate(string $transactionId, string $userId): array|false;

    /** @return array<string, mixed> */
    /**
     * Implementations must lock/reserve the user, session, IP and destination
     * counters until the surrounding transaction commits.
     *
     * @return array<string, mixed>
     */
    public function emailRequestStatsForUpdate(
        string $userId,
        string $sessionHash,
        string $ipAddress,
        string $destinationHmac
    ): array;

    public function revokeOpenEmailChallenges(string $transactionId): int;

    /** @param array<string, mixed> $entry */
    public function createEmailChallenge(array $entry): int;

    public function markChallengeSent(string $challengeId): int;

    public function revokeChallenge(string $challengeId, string $reason): int;

    /** @return array<string, mixed>|false */
    public function emailChallengeForUpdate(string $challengeId): array|false;

    public function recordFailedAttempt(string $challengeId): int;

    public function consumeEmailChallenge(string $challengeId): int;

    public function markPendingLoginVerified(string $transactionId, string $factorType): int;

    public function recordAudit(
        string $event,
        string $userId,
        string $outcome,
        string $reason,
        string $correlationId,
        string $ipAddress
    ): int;
}
