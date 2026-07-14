<?php

namespace OneId\App\User;

use OneId\App\Sync\SyncDataTransformer;
use OneId\App\User\Contracts\UserResyncApprovalStoreInterface;
use Throwable;

final class UserResyncService
{
    private const APPROVAL_TTL_SECONDS = 300;

    private const FIELD_LABELS = [
        'data1' => 'Name',
        'data2' => 'Secondary identity',
        'data3' => 'Staff number',
        'data4' => 'Primary identity',
        'data5' => 'Email',
        'data6' => 'Department',
        'data7' => 'Position / programme',
        'data8' => 'Additional field 8',
        'data9' => 'Additional field 9',
        'data10' => 'Additional field 10',
        'data11' => 'Additional field 11',
        'data12' => 'Additional field 12',
    ];

    /** @var callable(string, string): array */
    private $externalLookup;

    public function __construct(
        private readonly object $operation,
        callable $externalLookup,
        private readonly UserResyncApprovalStoreInterface $approvalStore
    ) {
        $this->externalLookup = $externalLookup;
    }

    /** @return array<string, mixed> */
    public function preview(string $userId, string $adminId): array
    {
        $correlationId = bin2hex(random_bytes(8));
        $userId = $this->validateUserId($userId, $correlationId);
        $adminId = $this->validateAdminId($adminId, $correlationId);
        $current = $this->loadEligibleCurrent($userId, false, $correlationId);
        $externalRows = $this->fetchExternal($userId, $this->sourceFamily($current, $correlationId), $correlationId);
        $plan = $this->buildPlan($userId, $externalRows, false, $correlationId, $current);

        $response = $this->project($plan, $correlationId);
        if ($plan['changes'] === []) {
            $response['code'] = 'NO_CHANGES';
            $response['can_apply'] = false;
            return $response;
        }

        $issuedAt = time();
        $approvalId = bin2hex(random_bytes(32));
        $this->approvalStore->save([
            'approval_id' => $approvalId,
            'admin_id' => $adminId,
            'user_id' => $userId,
            'fingerprint' => $plan['fingerprint'],
            'issued_at' => $issuedAt,
            'expires_at' => $issuedAt + self::APPROVAL_TTL_SECONDS,
            'correlation_id' => $correlationId,
        ]);

        $response['approval_id'] = $approvalId;
        $response['can_apply'] = true;
        $response['code'] = 'PREVIEW_READY';
        $response['expires_at'] = date(DATE_ATOM, $issuedAt + self::APPROVAL_TTL_SECONDS);
        return $response;
    }

    /** @return array<string, mixed> */
    public function apply(string $approvalId, string $adminId, string $ipAddress): array
    {
        $correlationId = bin2hex(random_bytes(8));
        if (preg_match('/^[a-f0-9]{64}$/', $approvalId) !== 1) {
            throw new UserResyncException('RESYNC_APPROVAL_INVALID', $correlationId);
        }
        $adminId = $this->validateAdminId($adminId, $correlationId);
        $approval = $this->approvalStore->consume($approvalId);
        if ($approval === null) {
            throw new UserResyncException('RESYNC_APPROVAL_NOT_AVAILABLE', $correlationId);
        }

        $correlationId = (string) ($approval['correlation_id'] ?? $correlationId);
        if ((int) ($approval['expires_at'] ?? 0) <= time()) {
            throw new UserResyncException('RESYNC_APPROVAL_EXPIRED', $correlationId);
        }
        if (!hash_equals((string) ($approval['admin_id'] ?? ''), $adminId)) {
            throw new UserResyncException('RESYNC_APPROVAL_ADMIN_MISMATCH', $correlationId);
        }

        $userId = $this->validateUserId((string) ($approval['user_id'] ?? ''), $correlationId);
        $current = $this->loadEligibleCurrent($userId, false, $correlationId);
        $externalRows = $this->fetchExternal($userId, $this->sourceFamily($current, $correlationId), $correlationId);
        $transactionStarted = false;
        try {
            $this->operation->beginTransaction();
            $transactionStarted = true;
            $plan = $this->buildPlan($userId, $externalRows, true, $correlationId);

            if ($plan['changes'] === []) {
                throw new UserResyncException('RESYNC_NO_LONGER_REQUIRED', $correlationId);
            }
            if (!hash_equals((string) ($approval['fingerprint'] ?? ''), $plan['fingerprint'])) {
                throw new UserResyncException('RESYNC_PREVIEW_MISMATCH', $correlationId);
            }

            $row = $plan['new'];
            $updated = $this->operation->admin_update_specific_user_info_all_data(
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
                $plan['new_hash']
            );
            if ((int) $updated !== 1) {
                throw new UserResyncException('RESYNC_UPDATE_NOT_APPLIED', $correlationId);
            }

            $changedFields = implode(',', array_keys($plan['changes']));
            $audited = $this->operation->syslog_record(
                24,
                $adminId . ' -> ' . $userId . ' -> fields=' . $changedFields
                    . ' -> correlation=' . $correlationId,
                $ipAddress
            );
            if ((int) $audited !== 1) {
                throw new UserResyncException('RESYNC_AUDIT_NOT_WRITTEN', $correlationId);
            }

            $this->operation->commit();
            $transactionStarted = false;
            return [
                'status' => 1,
                'code' => 'RESYNC_APPLIED',
                'correlation_id' => $correlationId,
                'changed_fields' => array_keys($plan['changes']),
            ];
        } catch (Throwable $exception) {
            if ($transactionStarted) {
                try {
                    $this->operation->rollback();
                } catch (Throwable $rollbackError) {
                    error_log('User resync rollback failed correlation_id=' . $correlationId);
                }
            }
            if ($exception instanceof UserResyncException) {
                throw $exception;
            }
            error_log(sprintf(
                'User resync apply failed correlation_id=%s exception=%s',
                $correlationId,
                get_class($exception)
            ));
            throw new UserResyncException('RESYNC_APPLY_FAILED', $correlationId);
        }
    }

    /** @return list<array<string, mixed>> */
    private function fetchExternal(string $userId, string $sourceFamily, string $correlationId): array
    {
        try {
            $rows = ($this->externalLookup)($userId, $sourceFamily);
        } catch (Throwable $exception) {
            error_log(sprintf(
                'User resync source failed correlation_id=%s exception=%s',
                $correlationId,
                get_class($exception)
            ));
            throw new UserResyncException('RESYNC_SOURCE_UNAVAILABLE', $correlationId);
        }
        if (!is_array($rows)) {
            throw new UserResyncException('RESYNC_SOURCE_INVALID', $correlationId);
        }
        return array_values($rows);
    }

    private function sourceFamily(array $current, string $correlationId): string
    {
        if (trim((string) ($current['data3'] ?? '')) !== '') {
            return 'staff';
        }
        if (trim((string) ($current['data2'] ?? '')) !== '') {
            return 'student';
        }
        throw new UserResyncException('RESYNC_SOURCE_IDENTITY_UNDETERMINED', $correlationId);
    }

    /** @return array<string, mixed> */
    private function buildPlan(
        string $userId,
        array $externalRows,
        bool $forUpdate,
        string $correlationId,
        ?array $current = null
    ): array {
        $current = $current ?? $this->loadEligibleCurrent($userId, $forUpdate, $correlationId);
        if (count($externalRows) === 0) {
            throw new UserResyncException('RESYNC_EXTERNAL_USER_NOT_FOUND', $correlationId);
        }
        if (count($externalRows) !== 1) {
            throw new UserResyncException('RESYNC_EXTERNAL_USER_AMBIGUOUS', $correlationId);
        }

        $new = $this->canonicalRow($externalRows[0]);
        if (!hash_equals($userId, $new['data4'])) {
            throw new UserResyncException('RESYNC_EXTERNAL_IDENTITY_MISMATCH', $correlationId);
        }
        $old = $this->canonicalRow($current);
        $changes = [];
        foreach (array_keys(self::FIELD_LABELS) as $field) {
            if ($old[$field] !== $new[$field]) {
                $changes[$field] = [
                    'field' => $field,
                    'label' => self::FIELD_LABELS[$field],
                    'old' => $old[$field],
                    'new' => $new[$field],
                ];
            }
        }

        $newHash = SyncDataTransformer::computeHash(
            $new['data1'], $new['data2'], $new['data3'], $new['data4'],
            $new['data5'], $new['data6'], $new['data7'], $new['data8'],
            $new['data9'], $new['data10'], $new['data11'], $new['data12'],
            $new['ext_data_source_category']
        );
        $fingerprint = hash('sha256', json_encode([
            'user_id' => $userId,
            'old' => $old,
            'new' => $new,
            'stored_hash' => (string) ($current['u_changes_hash'] ?? ''),
            'new_hash' => $newHash,
            'changed_fields' => array_keys($changes),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return [
            'user_id' => $userId,
            'old' => $old,
            'new' => $new,
            'changes' => $changes,
            'new_hash' => $newHash,
            'fingerprint' => $fingerprint,
        ];
    }

    /** @return array<string, mixed> */
    private function loadEligibleCurrent(
        string $userId,
        bool $forUpdate,
        string $correlationId
    ): array {
        $current = $this->operation->admin_get_user_for_resync($userId, $forUpdate);
        if (!is_array($current) || $current === []) {
            throw new UserResyncException('RESYNC_USER_NOT_FOUND', $correlationId);
        }
        if (($current['account_source'] ?? '') !== 'external') {
            $reason = ($current['account_source'] ?? '') === 'manual'
                && (int) ($current['sync_protected'] ?? 0) === 1
                ? 'RESYNC_MANUAL_PROTECTED'
                : 'RESYNC_ACCOUNT_SOURCE_NOT_EXTERNAL';
            throw new UserResyncException($reason, $correlationId);
        }
        if ((int) ($current['avail_status'] ?? 0) !== 1) {
            throw new UserResyncException('RESYNC_USER_INACTIVE', $correlationId);
        }
        return $current;
    }

    /** @return array<string, string> */
    private function canonicalRow(array $row): array
    {
        $canonical = [];
        foreach (array_keys(self::FIELD_LABELS) as $field) {
            $value = $row[$field] ?? '';
            if (is_array($value) || is_object($value)) {
                $value = '';
            }
            $canonical[$field] = trim((string) $value);
        }
        $canonical['ext_data_source_category'] = trim((string) ($row['ext_data_source_category'] ?? ''));
        return $canonical;
    }

    /** @return array<string, mixed> */
    private function project(array $plan, string $correlationId): array
    {
        $projectedChanges = [];
        foreach ($plan['changes'] as $field => $change) {
            if (in_array($field, ['data2', 'data3', 'data4'], true)) {
                $change['old'] = $this->maskIdentity((string) $change['old']);
                $change['new'] = $this->maskIdentity((string) $change['new']);
            }
            $projectedChanges[] = $change;
        }
        return [
            'status' => 1,
            'mode' => 'preview',
            'can_apply' => false,
            'code' => 'PREVIEW_COMPLETE',
            'correlation_id' => $correlationId,
            'change_count' => count($plan['changes']),
            'changes' => $projectedChanges,
            'fingerprint' => $plan['fingerprint'],
        ];
    }

    private function maskIdentity(string $value): string
    {
        $length = strlen($value);
        if ($length <= 4) {
            return $value;
        }
        return str_repeat('•', min(8, $length - 4)) . substr($value, -4);
    }

    private function validateUserId(string $userId, string $correlationId): string
    {
        $userId = trim($userId);
        if ($userId === '' || strlen($userId) > 20
            || preg_match('/^[A-Za-z0-9._@-]+$/', $userId) !== 1
        ) {
            throw new UserResyncException('RESYNC_USER_ID_INVALID', $correlationId);
        }
        return $userId;
    }

    private function validateAdminId(string $adminId, string $correlationId): string
    {
        $adminId = trim($adminId);
        if ($adminId === '') {
            throw new UserResyncException('RESYNC_ADMIN_INVALID', $correlationId);
        }
        return $adminId;
    }
}
