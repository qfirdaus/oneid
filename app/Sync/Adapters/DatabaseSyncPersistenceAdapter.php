<?php

namespace OneId\App\Sync\Adapters;

use OneId\App\Sync\Contracts\SyncPersistenceInterface;
use OneId\App\Sync\SyncDatabaseStageException;
use PDOException;

/** Bridge from the sync persistence contract to the legacy Database API. */
final class DatabaseSyncPersistenceAdapter implements SyncPersistenceInterface
{
    public function __construct(private object $operation)
    {
    }

    public function begin(): void
    {
        $this->guard('begin_transaction', fn() => $this->operation->beginTransaction());
    }

    public function commit(): void
    {
        $this->guard('commit_transaction', fn() => $this->operation->commit());
    }

    public function rollback(): void
    {
        $this->guard('rollback_transaction', fn() => $this->operation->rollback());
    }

    public function createHeader(int $type): int
    {
        $headerId = (int) $this->guard('create_header', fn() => $this->operation->action_add_new_ext_header($type));
        if ($headerId < 1) {
            throw new \RuntimeException('SYNC_HEADER_CREATE_FAILED');
        }
        return $headerId;
    }

    public function activeUsers(): array
    {
        return $this->operation->sync_get_all_sso_user();
    }

    public function inactiveUserIds(): array
    {
        return $this->operation->sync_get_inactive_user_ids();
    }

    public function deactivateUser(string $userId): void
    {
        $result = $this->guard('deactivate_user', fn() => $this->operation->admin_update_user_status($userId, 0));
        $this->assertAffected($result, 'SYNC_DEACTIVATE_NOT_APPLIED');
    }

    public function updateUser(string $userId, array $row, string $changeHash): void
    {
        $result = $this->guard('update_user', fn() => $this->operation->admin_update_specific_user_info_all_data(
            $userId,
            $row['data1'],
            $row['data2'],
            $row['data3'],
            $row['data4'],
            $row['data5'],
            $row['data6'],
            $row['data7'],
            $row['data8'],
            $row['data9'],
            $row['data10'],
            $row['data11'],
            $row['data12'],
            $changeHash
        ));
        $this->assertAffected($result, 'SYNC_UPDATE_NOT_APPLIED');
    }

    public function updateHeaderStatus(int $headerId, int $status, string $field, int $count): void
    {
        $this->guard('update_header_status', fn() => $this->operation->admin_update_ext_header_status($headerId, $status, $field, $count));
    }

    public function stageExternalUser(int $headerId, array $row): int
    {
        $bodyId = (int) $this->guard('stage_external_user', fn() => $this->operation->action_add_external_temp_body(
            $headerId,
            $row['data1'],
            $row['data2'],
            $row['data3'],
            $row['data4'],
            $row['data5'],
            $row['data6'],
            $row['data7'],
            $row['data8'],
            $row['data9'],
            $row['data10'],
            $row['data11'],
            $row['data12']
        ));
        if ($bodyId < 1) {
            throw new \RuntimeException('SYNC_STAGE_CREATE_FAILED');
        }
        return $bodyId;
    }

    public function insertExternalUser(
        array $row,
        int $categoryId,
        string $passwordHash,
        string $changeHash
    ): void {
        $result = $this->guard('insert_external_user', fn() => $this->operation->action_add_new_user_from_external_source(
            $row['data4'],
            $categoryId,
            $passwordHash,
            $row['data1'],
            $row['data2'],
            $row['data3'],
            $row['data4'],
            $row['data5'],
            $row['data6'],
            $row['data7'],
            $row['data8'],
            $row['data9'],
            $row['data10'],
            $row['data11'],
            $row['data12'],
            $changeHash
        ));
        $this->assertAffected($result, 'SYNC_INSERT_NOT_APPLIED');
    }

    public function markStagedUser(int $headerId, int $bodyId, int $status): void
    {
        $result = $this->guard('mark_staged_user', fn() => $this->operation->admin_update_ext_body_status($headerId, $bodyId, $status));
        $this->assertAffected($result, 'SYNC_STAGE_STATUS_NOT_APPLIED');
    }

    public function appendChanges(array $changes): void
    {
        $result = $this->guard('append_change_audit', fn() => $this->operation->sync_log_change_batch($changes));
        if ($result !== null && (int) $result !== count($changes)) {
            throw new \RuntimeException('SYNC_AUDIT_WRITE_MISMATCH');
        }
    }

    public function updateSummary(
        int $headerId,
        int $new,
        int $updated,
        int $deactivated,
        int $reactivated,
        string $triggeredBy
    ): void {
        $this->guard('update_header_summary', fn() => $this->operation->sync_update_header_summary(
            $headerId,
            $new,
            $updated,
            $deactivated,
            $reactivated,
            $triggeredBy
        ));
    }

    public function header(int $headerId): array
    {
        return $this->guard('read_committed_header', fn() => $this->operation->action_get_ext_header($headerId));
    }

    /**
     * Legacy test spies historically returned void. Real Database methods
     * return rowCount, which the safe writer must not silently ignore.
     */
    private function assertAffected(mixed $result, string $failureCode): void
    {
        if ($result !== null && (int) $result < 1) {
            throw new \RuntimeException($failureCode);
        }
    }

    private function guard(string $stage, callable $operation): mixed
    {
        try {
            return $operation();
        } catch (PDOException $exception) {
            throw SyncDatabaseStageException::fromPdo($stage, $exception);
        }
    }
}
