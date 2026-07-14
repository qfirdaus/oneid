<?php

namespace OneId\App\Admin;

use Throwable;

final class WebAppService
{
    public function __construct(private readonly object $operation)
    {
    }

    public function archive(string $appId, string $adminId, string $ipAddress): array
    {
        $correlationId = bin2hex(random_bytes(8));
        $appId = trim($appId);
        $adminId = trim($adminId);
        if ($appId === '' || strlen($appId) > 20 || preg_match('/^[A-Za-z0-9_-]+$/', $appId) !== 1) {
            throw new WebAppManagementException('W3_APP_ID_INVALID', $correlationId);
        }
        if ($adminId === '' || strlen($adminId) > 20 || preg_match('/^[A-Za-z0-9._@-]+$/', $adminId) !== 1) {
            throw new WebAppManagementException('W3_ADMIN_ID_INVALID', $correlationId);
        }

        $started = false;
        try {
            $this->operation->beginTransaction();
            $started = true;
            $app = $this->operation->admin_get_service_provider_for_update($appId);
            if (!is_array($app)) {
                throw new WebAppManagementException('W3_APP_NOT_FOUND', $correlationId);
            }
            if ((int) $app['avail_status'] !== 1) {
                throw new WebAppManagementException('W3_APP_ALREADY_INACTIVE', $correlationId);
            }
            if ($this->operation->admin_archive_service_provider($appId) !== 1) {
                throw new WebAppManagementException('W3_APP_NOT_ARCHIVED', $correlationId);
            }

            $removed = [];
            foreach (['acl_group','acl_single','acl_blacklist','user_app_favourite'] as $table) {
                $removed[$table] = $this->operation->admin_delete_app_access_references($table, $appId);
            }
            $detail = sprintf(
                'admin=%s action=archive_app app=%s old_category=%s acl_group=%d acl_single=%d blacklist=%d favourites=%d correlation=%s',
                $adminId,
                $appId,
                (string) $app['sp_group_id'],
                $removed['acl_group'],
                $removed['acl_single'],
                $removed['acl_blacklist'],
                $removed['user_app_favourite'],
                $correlationId
            );
            if ($this->operation->syslog_record(15, $detail, $ipAddress) !== 1) {
                throw new WebAppManagementException('W3_AUDIT_NOT_WRITTEN', $correlationId);
            }
            $this->operation->commit();
            return [
                'status'=>1,
                'code'=>'W3_APP_ARCHIVED',
                'correlation_id'=>$correlationId,
                'removed_references'=>$removed,
            ];
        } catch (Throwable $exception) {
            if ($started) {
                try {
                    $this->operation->rollback();
                } catch (Throwable $ignored) {
                    error_log('W3 app rollback failed correlation_id=' . $correlationId);
                }
            }
            if ($exception instanceof WebAppManagementException) {
                throw $exception;
            }
            error_log('W3 app archive failed correlation_id=' . $correlationId . ' exception=' . get_class($exception));
            throw new WebAppManagementException('W3_APP_OPERATION_FAILED', $correlationId);
        }
    }
}
