<?php

namespace OneId\App\User;

use Throwable;

final class UserAclManagementService
{
    public function __construct(private readonly object $operation)
    {
    }

    public function allow(string $userId, string $spId, string $adminId, string $ipAddress): array
    {
        return $this->execute('allow', $userId, $spId, 0, $adminId, $ipAddress);
    }

    public function deny(string $userId, string $spId, string $adminId, string $ipAddress): array
    {
        return $this->execute('deny', $userId, $spId, 0, $adminId, $ipAddress);
    }

    public function uplift(string $userId, string $blacklistId, string $adminId, string $ipAddress): array
    {
        $correlationId = bin2hex(random_bytes(8));
        if (preg_match('/^\d{1,20}$/', trim($blacklistId)) !== 1) {
            throw new UserManagementException('M3_BLACKLIST_ID_INVALID', $correlationId);
        }
        return $this->execute('uplift', $userId, '', (int) $blacklistId, $adminId, $ipAddress, $correlationId);
    }

    private function execute(string $action, string $userId, string $spId, int $blacklistId, string $adminId, string $ipAddress, ?string $correlationId = null): array
    {
        $correlationId ??= bin2hex(random_bytes(8));
        $userId = $this->validId($userId, 'M3_USER_ID_INVALID', $correlationId, 20);
        $adminId = $this->validId($adminId, 'M3_ADMIN_ID_INVALID', $correlationId, 20);
        if ($action !== 'uplift') {
            $spId = $this->validId($spId, 'M3_APP_ID_INVALID', $correlationId, 64);
        }

        $started = false;
        try {
            $this->operation->beginTransaction();
            $started = true;
            $user = $this->operation->admin_get_user_for_profile_action($userId, true);
            if (!is_array($user) || $user === []) throw new UserManagementException('M3_USER_NOT_FOUND', $correlationId);
            if ((int) ($user['avail_status'] ?? 0) !== 1) throw new UserManagementException('M3_USER_INACTIVE', $correlationId);

            if ($action === 'uplift') {
                $record = $this->operation->admin_get_blacklist_record_for_action($blacklistId, true);
                if (!is_array($record) || $record === []) throw new UserManagementException('M3_DENY_RECORD_NOT_FOUND', $correlationId);
                if (!hash_equals($userId, (string) ($record['u_id'] ?? ''))) {
                    throw new UserManagementException('M3_DENY_RECORD_OWNER_MISMATCH', $correlationId);
                }
                $spId = (string) $record['sp_id'];
                if ($this->operation->admin_uplift_blacklist_record($blacklistId) !== 1) {
                    throw new UserManagementException('M3_ACL_NOT_CHANGED', $correlationId);
                }
                $event = 28;
                $code = 'M3_ACL_DENY_UPLIFTED';
            } else {
                $app = $this->operation->admin_get_active_service_provider_for_acl($spId);
                if (!is_array($app) || $app === []) throw new UserManagementException('M3_APP_NOT_ACTIVE', $correlationId);
                $state = $this->operation->admin_get_user_acl_state($userId, $spId);
                if ($action === 'allow') {
                    if ((int) $state['denied'] === 1) throw new UserManagementException('M3_ACL_DENIED_UPLIFT_FIRST', $correlationId);
                    if ((int) $state['direct_allow'] === 1 || (int) $state['category_allow'] === 1) {
                        throw new UserManagementException('M3_ACL_ALREADY_ALLOWED', $correlationId);
                    }
                    if ($this->operation->add_new_specific_apps_to_user($userId, $spId) !== 1) {
                        throw new UserManagementException('M3_ACL_NOT_CHANGED', $correlationId);
                    }
                    $event = 28;
                    $code = 'M3_ACL_ALLOWED';
                } else {
                    if ((int) $state['denied'] === 1) throw new UserManagementException('M3_ACL_ALREADY_DENIED', $correlationId);
                    if ((int) $state['direct_allow'] !== 1 && (int) $state['category_allow'] !== 1) {
                        throw new UserManagementException('M3_ACL_NOT_CURRENTLY_ALLOWED', $correlationId);
                    }
                    if ($this->operation->admin_set_deny_access_record($spId, $userId) !== 1) {
                        throw new UserManagementException('M3_ACL_NOT_CHANGED', $correlationId);
                    }
                    $event = 29;
                    $code = 'M3_ACL_DENIED';
                }
            }

            $this->operation->update_whole_token_status($userId, 0);
            $detail = sprintf('admin=%s action=acl_%s user=%s app=%s correlation=%s', $adminId, $action, $userId, $spId, $correlationId);
            if ($this->operation->syslog_record($event, $detail, $ipAddress) !== 1) {
                throw new UserManagementException('M3_AUDIT_NOT_WRITTEN', $correlationId);
            }
            $this->operation->commit();
            return ['status' => 1, 'code' => $code, 'correlation_id' => $correlationId];
        } catch (Throwable $exception) {
            if ($started) {
                try { $this->operation->rollback(); } catch (Throwable $ignored) {
                    error_log('M3 ACL rollback failed correlation_id=' . $correlationId);
                }
            }
            if ($exception instanceof UserManagementException) throw $exception;
            error_log('M3 ACL action failed correlation_id=' . $correlationId . ' exception=' . get_class($exception));
            throw new UserManagementException('M3_ACL_OPERATION_FAILED', $correlationId);
        }
    }

    private function validId(string $value, string $reason, string $correlationId, int $max): string
    {
        $value = trim($value);
        if ($value === '' || strlen($value) > $max || preg_match('/^[A-Za-z0-9._@-]+$/', $value) !== 1) {
            throw new UserManagementException($reason, $correlationId);
        }
        return $value;
    }
}
