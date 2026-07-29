<?php

declare(strict_types=1);

namespace OneId\App\Auth\UserMfa;

interface UserMfaTotpPersistenceInterface
{
    public function beginTransaction(): void;

    public function commit(): void;

    public function rollback(): void;

    /** @return array<string, mixed>|false */
    public function policyForUpdate(): array|false;

    /** @return array<string, mixed>|false */
    public function pendingFactorForUpdate(string $factorId, string $userId): array|false;

    /** @return array<string, mixed>|false */
    public function activeFactorForUpdate(string $userId): array|false;

    /** @param array<string, mixed> $factor */
    public function createPendingFactor(array $factor): string|false;

    public function confirmFactor(string $factorId, int $timeStep): int;

    public function updateLastUsedStep(string $factorId, int $expectedPreviousStep, int $newStep): int;

    public function setPreference(string $userId, string $factorType, string $correlationId): int;

    public function revokeFactors(string $userId, string $reason): int;

    public function revokePendingChallenges(string $userId, string $reason): int;

    public function revokeUserSessions(string $userId, string $reason): int;

    public function recordAudit(
        string $event,
        string $targetUserId,
        string $actorUserId,
        string $outcome,
        string $reason,
        string $reference,
        string $correlationId
    ): int;
}
