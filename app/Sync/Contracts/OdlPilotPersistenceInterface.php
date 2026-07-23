<?php
declare(strict_types=1);
namespace OneId\App\Sync\Contracts;

interface OdlPilotPersistenceInterface
{
    public function acquireLock(): bool;
    public function releaseLock(): void;
    public function begin(): void;
    public function commit(): void;
    public function rollback(): void;
    /** @param array<string,mixed> $row */
    public function insertStudent(array $row, string $passwordHash, string $changeHash): void;
    public function insertMembership(
        string $userId,
        string $externalUserId,
        string $sourceHash
    ): void;
    public function appendEvent(
        string $correlationId,
        string $userId,
        string $externalUserId,
        string $eventType
    ): void;
    /** @return array{users:int,memberships:int,events:int} */
    public function reconciliation(string $correlationId): array;
    public function rollbackCorrelation(string $correlationId): int;
}
