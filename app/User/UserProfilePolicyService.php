<?php

namespace OneId\App\User;

use Throwable;

final class UserProfilePolicyService
{
    public function __construct(private readonly object $operation)
    {
    }

    /** @return array<string, mixed> */
    public function save(string $userId, string $name, string $categoryId, string $adminId, string $ipAddress): array
    {
        $correlationId = bin2hex(random_bytes(8));
        $userId = $this->validId($userId, 'M3_USER_ID_INVALID', $correlationId);
        $adminId = $this->validId($adminId, 'M3_ADMIN_ID_INVALID', $correlationId);
        $name = $this->validName($name, $correlationId);
        if (preg_match('/^\d{1,10}$/', trim($categoryId)) !== 1) {
            throw new UserManagementException('M3_CATEGORY_INVALID', $correlationId);
        }
        $category = (int) $categoryId;

        $started = false;
        try {
            $this->operation->beginTransaction();
            $started = true;
            $current = $this->operation->admin_get_user_for_profile_action($userId, true);
            if (!is_array($current) || $current === []) {
                throw new UserManagementException('M3_USER_NOT_FOUND', $correlationId);
            }
            if ((int) ($current['avail_status'] ?? 0) !== 1) {
                throw new UserManagementException('M3_USER_INACTIVE', $correlationId);
            }
            $categoryRow = $this->operation->admin_get_active_user_category($category);
            if (!is_array($categoryRow) || $categoryRow === []) {
                throw new UserManagementException('M3_CATEGORY_NOT_ACTIVE', $correlationId);
            }

            $oldName = trim((string) ($current['data1'] ?? ''));
            $oldCategory = (int) ($current['u_category'] ?? 0);
            $nameChanged = !hash_equals($oldName, $name);
            $categoryChanged = $oldCategory !== $category;
            if ($nameChanged && strtolower(trim((string) ($current['account_source'] ?? 'legacy'))) !== 'manual') {
                throw new UserManagementException('M3_EXTERNAL_NAME_READ_ONLY', $correlationId);
            }
            if (!$nameChanged && !$categoryChanged) {
                $this->operation->rollback();
                $started = false;
                return [
                    'status' => 1,
                    'code' => 'M3_PROFILE_UNCHANGED',
                    'correlation_id' => $correlationId,
                    'changed' => [],
                ];
            }

            if ($this->operation->admin_update_user_profile_category($userId, $name, $category) !== 1) {
                throw new UserManagementException('M3_PROFILE_NOT_SAVED', $correlationId);
            }
            if ($categoryChanged) {
                $this->operation->update_whole_token_status($userId, 0);
            }

            $changed = [];
            if ($nameChanged) $changed[] = 'name';
            if ($categoryChanged) $changed[] = 'category';
            $detail = sprintf(
                'admin=%s action=profile_save user=%s changed=%s category=%d->%d role_preserved=%d correlation=%s',
                $adminId,
                $userId,
                implode(',', $changed),
                $oldCategory,
                $category,
                (int) ($current['u_type'] ?? 0),
                $correlationId
            );
            if ($this->operation->syslog_record(18, $detail, $ipAddress) !== 1) {
                throw new UserManagementException('M3_AUDIT_NOT_WRITTEN', $correlationId);
            }
            $this->operation->commit();
            return [
                'status' => 1,
                'code' => 'M3_PROFILE_SAVED',
                'correlation_id' => $correlationId,
                'changed' => $changed,
                'role_preserved' => true,
            ];
        } catch (Throwable $exception) {
            if ($started) {
                try { $this->operation->rollback(); } catch (Throwable $ignored) {
                    error_log('M3 profile rollback failed correlation_id=' . $correlationId);
                }
            }
            if ($exception instanceof UserManagementException) throw $exception;
            error_log('M3 profile save failed correlation_id=' . $correlationId . ' exception=' . get_class($exception));
            throw new UserManagementException('M3_PROFILE_OPERATION_FAILED', $correlationId);
        }
    }

    private function validName(string $value, string $correlationId): string
    {
        $value = trim($value);
        if ($value === '' || strlen($value) > 200 || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
            throw new UserManagementException('M3_NAME_INVALID', $correlationId);
        }
        return $value;
    }

    private function validId(string $value, string $reason, string $correlationId): string
    {
        $value = trim($value);
        if ($value === '' || strlen($value) > 20 || preg_match('/^[A-Za-z0-9._@-]+$/', $value) !== 1) {
            throw new UserManagementException($reason, $correlationId);
        }
        return $value;
    }
}
