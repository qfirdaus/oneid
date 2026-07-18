<?php

namespace OneId\App\Admin;

use Throwable;

final class WebAppCategoryService
{
    public function __construct(private readonly object $operation)
    {
    }

    public function rename(string $categoryId, string $name, string $adminId, string $ipAddress): array
    {
        $correlationId = bin2hex(random_bytes(8));
        $categoryId = trim($categoryId);
        if (preg_match('/^\d{1,20}$/', $categoryId) !== 1) {
            throw new WebAppManagementException('W5_CATEGORY_ID_INVALID', $correlationId);
        }
        if ($categoryId === '0') {
            throw new WebAppManagementException('W5_SYSTEM_CATEGORY_PROTECTED', $correlationId);
        }
        $name = preg_replace('/\s+/u', ' ', trim($name)) ?? '';
        if ($name === '' || mb_strlen($name) > 100 || preg_match('/[\x00-\x1F\x7F]/u', $name) === 1) {
            throw new WebAppManagementException('W5_CATEGORY_NAME_INVALID', $correlationId);
        }
        $adminId = trim($adminId);
        if ($adminId === '' || strlen($adminId) > 20 || preg_match('/^[A-Za-z0-9._@-]+$/', $adminId) !== 1) {
            throw new WebAppManagementException('W5_ADMIN_ID_INVALID', $correlationId);
        }

        $started = false;
        try {
            $this->operation->beginTransaction();
            $started = true;
            $category = $this->operation->admin_get_app_category_for_update((int) $categoryId);
            if (!is_array($category) || $category === []) {
                throw new WebAppManagementException('W5_CATEGORY_NOT_FOUND', $correlationId);
            }
            $oldName = preg_replace('/\s+/u', ' ', trim((string) ($category['sp_group_name'] ?? ''))) ?? '';
            if (mb_strtolower($oldName) === mb_strtolower($name)) {
                throw new WebAppManagementException('W5_CATEGORY_UNCHANGED', $correlationId);
            }
            if ($this->operation->admin_find_other_app_category_by_name_for_update($name, (int) $categoryId) !== false) {
                throw new WebAppManagementException('W5_CATEGORY_DUPLICATE', $correlationId);
            }
            if ($this->operation->admin_rename_app_category((int) $categoryId, $name) !== 1) {
                throw new WebAppManagementException('W5_CATEGORY_NOT_RENAMED', $correlationId);
            }
            $detail = sprintf(
                'admin=%s action=rename_app_category category=%s old=%s new=%s correlation=%s',
                $adminId,
                $categoryId,
                $oldName,
                $name,
                $correlationId
            );
            if ($this->operation->syslog_record(11, $detail, $ipAddress) !== 1) {
                throw new WebAppManagementException('W5_AUDIT_NOT_WRITTEN', $correlationId);
            }
            $this->operation->commit();
            return ['status'=>1,'code'=>'W5_CATEGORY_RENAMED','correlation_id'=>$correlationId];
        } catch (Throwable $exception) {
            if ($started) {
                try {$this->operation->rollback();} catch (Throwable $ignored) {error_log('W5 category rollback failed correlation_id='.$correlationId);}
            }
            if ($exception instanceof WebAppManagementException) throw $exception;
            if ($exception instanceof \PDOException && (string) $exception->getCode() === '23000') {
                throw new WebAppManagementException('W5_CATEGORY_DUPLICATE', $correlationId);
            }
            error_log('W5 category rename failed correlation_id='.$correlationId.' exception='.get_class($exception));
            throw new WebAppManagementException('W5_CATEGORY_OPERATION_FAILED',$correlationId);
        }
    }

    public function create(string $name, string $adminId, string $ipAddress): array
    {
        $correlationId = bin2hex(random_bytes(8));
        $name = preg_replace('/\s+/u', ' ', trim($name)) ?? '';
        if ($name === '' || mb_strlen($name) > 100 || preg_match('/[\x00-\x1F\x7F]/u', $name) === 1) {
            throw new WebAppManagementException('W4_CATEGORY_NAME_INVALID', $correlationId);
        }
        $adminId = trim($adminId);
        if ($adminId === '' || strlen($adminId) > 20 || preg_match('/^[A-Za-z0-9._@-]+$/', $adminId) !== 1) {
            throw new WebAppManagementException('W4_ADMIN_ID_INVALID', $correlationId);
        }
        $started = false;
        try {
            $this->operation->beginTransaction();
            $started = true;
            if ($this->operation->admin_find_app_category_by_name_for_update($name) !== false) {
                throw new WebAppManagementException('W4_CATEGORY_DUPLICATE', $correlationId);
            }
            if ($this->operation->admin_create_app_category($name) !== 1) {
                throw new WebAppManagementException('W4_CATEGORY_NOT_CREATED', $correlationId);
            }
            $detail = sprintf('admin=%s action=create_app_category name=%s correlation=%s',$adminId,$name,$correlationId);
            if ($this->operation->syslog_record(11,$detail,$ipAddress) !== 1) {
                throw new WebAppManagementException('W4_AUDIT_NOT_WRITTEN', $correlationId);
            }
            $this->operation->commit();
            return ['status'=>1,'code'=>'W4_CATEGORY_CREATED','correlation_id'=>$correlationId];
        } catch (Throwable $exception) {
            if ($started) {
                try {$this->operation->rollback();} catch (Throwable $ignored) {error_log('W4 category rollback failed correlation_id='.$correlationId);}
            }
            if ($exception instanceof WebAppManagementException) throw $exception;
            error_log('W4 category create failed correlation_id='.$correlationId.' exception='.get_class($exception));
            throw new WebAppManagementException('W4_CATEGORY_OPERATION_FAILED',$correlationId);
        }
    }

    public function remove(string $categoryId, string $adminId, string $ipAddress): array
    {
        $correlationId = bin2hex(random_bytes(8));
        $categoryId = trim($categoryId);
        if (preg_match('/^\d{1,20}$/', $categoryId) !== 1) {
            throw new WebAppManagementException('W1_CATEGORY_ID_INVALID', $correlationId);
        }
        if ($categoryId === '0') {
            throw new WebAppManagementException('W1_SYSTEM_CATEGORY_PROTECTED', $correlationId);
        }
        $adminId = trim($adminId);
        if ($adminId === '' || strlen($adminId) > 20 || preg_match('/^[A-Za-z0-9._@-]+$/', $adminId) !== 1) {
            throw new WebAppManagementException('W1_ADMIN_ID_INVALID', $correlationId);
        }

        $started = false;
        try {
            $this->operation->beginTransaction();
            $started = true;
            $category = $this->operation->admin_get_app_category_for_update((int) $categoryId);
            if (!is_array($category) || $category === []) {
                throw new WebAppManagementException('W1_CATEGORY_NOT_FOUND', $correlationId);
            }

            $assignedCount = $this->operation->admin_count_apps_assigned_to_category((int) $categoryId);
            if ($assignedCount !== 0) {
                throw new WebAppManagementException(
                    'W1_CATEGORY_NOT_EMPTY',
                    $correlationId,
                    ['assigned_count' => $assignedCount]
                );
            }
            if ($this->operation->admin_delete_empty_app_category((int) $categoryId) !== 1) {
                throw new WebAppManagementException('W1_CATEGORY_NOT_REMOVED', $correlationId);
            }

            $detail = sprintf(
                'admin=%s action=remove_app_category category=%s correlation=%s',
                $adminId,
                $categoryId,
                $correlationId
            );
            if ($this->operation->syslog_record(12, $detail, $ipAddress) !== 1) {
                throw new WebAppManagementException('W1_AUDIT_NOT_WRITTEN', $correlationId);
            }
            $this->operation->commit();
            return [
                'status' => 1,
                'code' => 'W1_CATEGORY_REMOVED',
                'correlation_id' => $correlationId,
            ];
        } catch (Throwable $exception) {
            if ($started) {
                try {
                    $this->operation->rollback();
                } catch (Throwable $ignored) {
                    error_log('W1 category rollback failed correlation_id=' . $correlationId);
                }
            }
            if ($exception instanceof WebAppManagementException) {
                throw $exception;
            }
            error_log('W1 category removal failed correlation_id=' . $correlationId . ' exception=' . get_class($exception));
            throw new WebAppManagementException('W1_CATEGORY_OPERATION_FAILED', $correlationId);
        }
    }
}
