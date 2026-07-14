<?php

namespace OneId\App\User;

use Throwable;

final class UserSecurityActionService
{
    public function __construct(private readonly object $operation)
    {
    }

    /** @return array<string, mixed> */
    public function resetPassword(string $userId, string $adminId, string $ipAddress): array
    {
        return $this->execute('reset_password', $userId, $adminId, $ipAddress);
    }

    /** @return array<string, mixed> */
    public function deactivate(string $userId, string $adminId, string $ipAddress): array
    {
        return $this->execute('deactivate', $userId, $adminId, $ipAddress);
    }

    /** @return array<string, mixed> */
    public function reactivate(string $userId, string $adminId, string $ipAddress): array
    {
        return $this->execute('reactivate', $userId, $adminId, $ipAddress);
    }

    /** @return array<string, mixed> */
    private function execute(string $action, string $userId, string $adminId, string $ipAddress): array
    {
        $correlationId = bin2hex(random_bytes(8));
        $userId = $this->validId($userId, 'M2_USER_ID_INVALID', $correlationId);
        $adminId = $this->validId($adminId, 'M2_ADMIN_ID_INVALID', $correlationId);
        if ($userId === $adminId && in_array($action, ['reset_password', 'deactivate'], true)) {
            throw new UserSecurityActionException('M2_SELF_ACTION_FORBIDDEN', $correlationId);
        }

        $started = false;
        try {
            $this->operation->beginTransaction();
            $started = true;
            $current = $this->operation->admin_get_user_for_security_action($userId, true);
            if (!is_array($current) || $current === []) {
                throw new UserSecurityActionException('M2_USER_NOT_FOUND', $correlationId);
            }

            $event = 0;
            if ($action === 'reset_password') {
                if ((int) ($current['avail_status'] ?? 0) !== 1) {
                    throw new UserSecurityActionException('M2_USER_INACTIVE', $correlationId);
                }
                $temporarySecret = bin2hex(random_bytes(32));
                if ($this->operation->set_user_password($userId, $temporarySecret, 1) !== 1) {
                    throw new UserSecurityActionException('M2_PASSWORD_NOT_RESET', $correlationId);
                }
                $event = 10;
            } elseif ($action === 'deactivate') {
                if ((int) ($current['avail_status'] ?? 0) !== 1) {
                    throw new UserSecurityActionException('M2_ALREADY_INACTIVE', $correlationId);
                }
                if ($this->operation->admin_update_user_status($userId, 0) !== 1) {
                    throw new UserSecurityActionException('M2_STATUS_NOT_CHANGED', $correlationId);
                }
                $event = 25;
            } elseif ($action === 'reactivate') {
                if ((int) ($current['avail_status'] ?? 0) !== 0) {
                    throw new UserSecurityActionException('M2_ALREADY_ACTIVE', $correlationId);
                }
                if ($this->operation->admin_update_user_status($userId, 1) !== 1) {
                    throw new UserSecurityActionException('M2_STATUS_NOT_CHANGED', $correlationId);
                }
                $event = 26;
            } else {
                throw new UserSecurityActionException('M2_ACTION_INVALID', $correlationId);
            }

            // Zero affected rows is valid when no active token/OTP exists. A
            // database error still throws and rolls the whole transaction back.
            $this->operation->update_whole_token_status($userId, 0);
            $this->operation->otp_invalidate_active($userId);

            $detail = sprintf(
                'admin=%s action=%s user=%s correlation=%s',
                $adminId,
                $action,
                $userId,
                $correlationId
            );
            if ($this->operation->syslog_record($event, $detail, $ipAddress) !== 1) {
                throw new UserSecurityActionException('M2_AUDIT_NOT_WRITTEN', $correlationId);
            }

            $this->operation->commit();
            return [
                'status' => 1,
                'code' => match ($action) {
                    'reset_password' => 'M2_PASSWORD_RESET',
                    'deactivate' => 'M2_USER_DEACTIVATED',
                    default => 'M2_USER_REACTIVATED',
                },
                'source_status' => 1,
                'correlation_id' => $correlationId,
            ];
        } catch (Throwable $exception) {
            if ($started) {
                try {
                    $this->operation->rollback();
                } catch (Throwable $rollbackError) {
                    error_log('M2 rollback failed correlation_id=' . $correlationId);
                }
            }
            if ($exception instanceof UserSecurityActionException) {
                throw $exception;
            }
            error_log(sprintf(
                'M2 security action failed correlation_id=%s action=%s exception=%s',
                $correlationId,
                $action,
                get_class($exception)
            ));
            throw new UserSecurityActionException('M2_OPERATION_FAILED', $correlationId);
        }
    }

    private function validId(string $value, string $reason, string $correlationId): string
    {
        $value = trim($value);
        if ($value === '' || strlen($value) > 20 || preg_match('/^[A-Za-z0-9._@-]+$/', $value) !== 1) {
            throw new UserSecurityActionException($reason, $correlationId);
        }
        return $value;
    }
}
