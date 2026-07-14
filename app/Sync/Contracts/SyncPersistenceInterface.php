<?php

namespace OneId\App\Sync\Contracts;

interface SyncPersistenceInterface
{
    public function begin(): void;

    public function commit(): void;

    public function rollback(): void;

    public function createHeader(int $type): int;

    /** @return list<array<string, mixed>> */
    public function activeUsers(): array;

    /** @return list<string> */
    public function inactiveUserIds(): array;

    public function deactivateUser(string $userId): void;

    /** @param array<string, mixed> $row */
    public function updateUser(string $userId, array $row, string $changeHash): void;

    public function updateHeaderStatus(int $headerId, int $status, string $field, int $count): void;

    /** @param array<string, mixed> $row */
    public function stageExternalUser(int $headerId, array $row): int;

    /** @param array<string, mixed> $row */
    public function insertExternalUser(
        array $row,
        int $categoryId,
        string $passwordHash,
        string $changeHash
    ): void;

    public function markStagedUser(int $headerId, int $bodyId, int $status): void;

    /** @param list<array<string, mixed>> $changes */
    public function appendChanges(array $changes): void;

    public function updateSummary(
        int $headerId,
        int $new,
        int $updated,
        int $deactivated,
        int $reactivated,
        string $triggeredBy
    ): void;

    /** @return array<string, mixed> */
    public function header(int $headerId): array;
}
