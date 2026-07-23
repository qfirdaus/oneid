<?php
declare(strict_types=1);
namespace OneId\App\Sync\Adapters;

use OneId\App\Sync\Contracts\SyncPersistenceInterface;

/**
 * Limits legacy planner reads to one authoritative source family while all
 * writes continue through the existing guarded database adapter.
 */
final class SourceScopedSyncPersistenceAdapter implements SyncPersistenceInterface
{
    /** @param list<int> $categoryIds */
    public function __construct(
        private readonly SyncPersistenceInterface $inner,
        private readonly array $categoryIds,
        private readonly ?\Closure $activeSourceUserIds = null
    ) {}

    public function begin(): void { $this->inner->begin(); }
    public function commit(): void { $this->inner->commit(); }
    public function rollback(): void { $this->inner->rollback(); }
    public function createHeader(int $type): int { return $this->inner->createHeader($type); }

    public function activeUsers(): array
    {
        $sourceUsers = $this->activeSourceUserIds === null
            ? null
            : array_fill_keys(($this->activeSourceUserIds)(), true);
        return array_values(array_filter(
            $this->inner->activeUsers(),
            fn(array $user): bool =>
                in_array(
                    (int) ($user['u_category'] ?? 0),
                    $this->categoryIds,
                    true
                )
                && ($sourceUsers === null
                    || isset($sourceUsers[(string) ($user['u_id'] ?? '')]))
        ));
    }

    public function inactiveUserIds(): array
    {
        // Inactive identities are used only to distinguish NEW from REACTIVATE.
        // The current legacy API does not expose inactive categories.
        return $this->inner->inactiveUserIds();
    }

    public function deactivateUser(string $userId): void
    {
        $this->inner->deactivateUser($userId);
    }

    public function updateUser(string $userId, array $row, string $changeHash): void
    {
        $this->inner->updateUser($userId, $row, $changeHash);
    }

    public function updateHeaderStatus(int $headerId, int $status, string $field, int $count): void
    {
        $this->inner->updateHeaderStatus($headerId, $status, $field, $count);
    }

    public function stageExternalUser(int $headerId, array $row): int
    {
        return $this->inner->stageExternalUser($headerId, $row);
    }

    public function insertExternalUser(
        array $row,
        int $categoryId,
        string $passwordHash,
        string $changeHash
    ): void {
        $this->inner->insertExternalUser($row, $categoryId, $passwordHash, $changeHash);
    }

    public function markStagedUser(int $headerId, int $bodyId, int $status): void
    {
        $this->inner->markStagedUser($headerId, $bodyId, $status);
    }

    public function appendChanges(array $changes): void
    {
        $this->inner->appendChanges($changes);
    }

    public function updateSummary(
        int $headerId,
        int $new,
        int $updated,
        int $deactivated,
        int $reactivated,
        string $triggeredBy
    ): void {
        $this->inner->updateSummary(
            $headerId,
            $new,
            $updated,
            $deactivated,
            $reactivated,
            $triggeredBy
        );
    }

    public function header(int $headerId): array
    {
        return $this->inner->header($headerId);
    }
}
