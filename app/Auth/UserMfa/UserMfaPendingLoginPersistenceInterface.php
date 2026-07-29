<?php

declare(strict_types=1);

namespace OneId\App\Auth\UserMfa;

interface UserMfaPendingLoginPersistenceInterface
{
    public function beginTransaction(): void;

    public function commit(): void;

    public function rollback(): void;

    /** @param array<string, mixed> $entry */
    public function createPendingLogin(array $entry): int;

    /** @return array<string, mixed>|false */
    public function pendingLoginForUpdate(string $transactionId): array|false;

    public function markFactorVerified(string $transactionId, string $factorType): int;

    public function consumePendingLogin(string $transactionId): int;

    public function revokePendingLogin(string $transactionId, string $reason): int;

    public function recordAudit(
        string $event,
        string $userId,
        string $outcome,
        string $reason,
        string $correlationId,
        string $ipAddress
    ): int;
}
