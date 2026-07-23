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
    /** @var null|array<string,true> */
    private ?array $sourceUsers = null;

    /** @param list<int> $categoryIds */
    public function __construct(
        private readonly SyncPersistenceInterface $inner,
        private readonly array $categoryIds,
        private readonly ?\Closure $activeSourceUserIds = null,
        private readonly ?\Closure $inactiveSourceUserIds = null,
        private readonly ?\Closure $assertWritableIdentity = null,
        private readonly ?\Closure $recordMembership = null,
        private readonly ?\Closure $deactivateMembership = null,
        private readonly ?\Closure $hasOtherActiveSource = null
    ) {}

    public function begin(): void { $this->inner->begin(); }
    public function commit(): void { $this->inner->commit(); }
    public function rollback(): void { $this->inner->rollback(); }
    public function createHeader(int $type): int { return $this->inner->createHeader($type); }

    public function activeUsers(): array
    {
        $sourceUsers = $this->sourceUserSet();
        return array_values(array_filter(
            $this->inner->activeUsers(),
            fn(array $user): bool => (
                in_array(
                    (int) ($user['u_category'] ?? 0),
                    $this->categoryIds,
                    true
                )
                && ($sourceUsers === null
                    || isset($sourceUsers[(string) ($user['u_id'] ?? '')]))
            ) || (
                ($user['account_source'] ?? '') === 'manual'
                && (int) ($user['sync_protected'] ?? 0) === 1
            )
        ));
    }

    public function inactiveUserIds(): array
    {
        return $this->inactiveSourceUserIds === null
            ? $this->inner->inactiveUserIds()
            : ($this->inactiveSourceUserIds)();
    }

    public function deactivateUser(string $userId): void
    {
        $this->assertSourceOwned($userId);
        if ($this->deactivateMembership !== null) {
            ($this->deactivateMembership)($userId);
        }
        if ($this->hasOtherActiveSource === null
            || !($this->hasOtherActiveSource)($userId)
        ) {
            $this->inner->deactivateUser($userId);
        }
    }

    public function updateUser(string $userId, array $row, string $changeHash): void
    {
        $this->assertSourceOwned($userId);
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
        $userId = (string) ($row['data4'] ?? '');
        if ($this->assertWritableIdentity !== null) {
            ($this->assertWritableIdentity)(
                $userId,
                (string) ($row['data2'] ?? '')
            );
        }
        $this->inner->insertExternalUser($row, $categoryId, $passwordHash, $changeHash);
        if ($this->recordMembership !== null) {
            ($this->recordMembership)($userId, $userId, $changeHash);
        }
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

    /** @return null|array<string,true> */
    private function sourceUserSet(): ?array
    {
        if ($this->activeSourceUserIds === null) {
            return null;
        }
        return $this->sourceUsers ??= array_fill_keys(
            ($this->activeSourceUserIds)(),
            true
        );
    }

    private function assertSourceOwned(string $userId): void
    {
        $sourceUsers = $this->sourceUserSet();
        if ($sourceUsers !== null && !isset($sourceUsers[$userId])) {
            throw new \RuntimeException('SYNC_SOURCE_OWNERSHIP_VIOLATION');
        }
    }
}
