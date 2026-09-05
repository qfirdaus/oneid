<?php

namespace OneId\App\User;

use Throwable;
require_once dirname(__DIR__) . '/Notification/AdminEmailNotificationComposer.php';
require_once dirname(__DIR__) . '/Notification/AdminEmailNotificationException.php';

final class InitialPasswordSetupService
{
    public function __construct(private readonly object $operation) {}

    public function setup(
        string $userId,
        string $new,
        string $confirmation,
        string $ip
    ): array {
        $correlation = bin2hex(random_bytes(8));
        $started = false;
        if ($new === '' || !hash_equals($new, $confirmation)) {
            throw new UserPasswordChangeException('UC2_CONFIRMATION_MISMATCH', $correlation);
        }
        [$valid] = oneid_validate_new_password($new, $userId);
        if (!$valid) {
            throw new UserPasswordChangeException('UC5_PASSWORD_QUALITY_REJECTED', $correlation);
        }
        try {
            $this->operation->beginTransaction();
            $started = true;
            $user = $this->operation->get_user_password_change_for_update($userId);
            if (!is_array($user) || (int) ($user['avail_status'] ?? 0) !== 1) {
                throw new UserPasswordChangeException('UC2_USER_NOT_ACTIVE', $correlation);
            }
            if ((int) ($user['password_change_required'] ?? 0) !== 1) {
                throw new UserPasswordChangeException('UC6_INITIAL_SETUP_NOT_REQUIRED', $correlation);
            }
            $stored = (string) ($user['u_password'] ?? '');
            if (oneid_password_verify($new, $stored)) {
                throw new UserPasswordChangeException('UC2_PASSWORD_REUSE_CURRENT', $correlation);
            }
            foreach ($this->operation->get_password_history_hashes($userId, oneid_password_history_limit()) as $historyHash) {
                if (oneid_password_verify($new, (string) $historyHash)) {
                    throw new UserPasswordChangeException('UC5_PASSWORD_HISTORY_REUSED', $correlation);
                }
            }
            if ($stored !== '' && $this->operation->record_password_history($userId, $stored) !== 1) {
                throw new UserPasswordChangeException('UC5_PASSWORD_HISTORY_WRITE_FAILED', $correlation);
            }
            if ($this->operation->set_user_password($userId, $new, 0) !== 1) {
                throw new UserPasswordChangeException('UC2_PASSWORD_NOT_CHANGED', $correlation);
            }
            $this->operation->prune_password_history($userId, oneid_password_history_limit());
            $revoked = (int) $this->operation->update_whole_token_status($userId, 0, 'PASSWORD_RESET');
            $invalidated = (int) $this->operation->otp_invalidate_active($userId);
            $detail = sprintf(
                'user=%s action=mydigitalid_initial_password_setup tokens_revoked=%d otp_invalidated=%d correlation=%s',
                $userId,
                $revoked,
                $invalidated,
                $correlation
            );
            if ($this->operation->syslog_record(21, $detail, $ip) !== 1) {
                throw new UserPasswordChangeException('UC2_AUDIT_FAILED', $correlation);
            }
            \OneId\App\Notification\AdminEmailNotificationComposer::queueUserEvent($this->operation,'INITIAL_PASSWORD_SET',$userId,$correlation,$correlation,['Action time'=>date('d/m/Y h:i A'),'Reference'=>$correlation]);
            $this->operation->commit();
            $started = false;
            return [
                'status' => 1,
                'code' => 'UC6_INITIAL_PASSWORD_SET_REAUTH_REQUIRED',
                'msg' => 'Initial password successfully set',
                'correlation_id' => $correlation,
                'password_change_required' => 0,
                'reauthentication_required' => true,
            ];
        } catch (Throwable $exception) {
            if ($started) {
                try { $this->operation->rollback(); } catch (Throwable) {}
            }
            if ($exception instanceof UserPasswordChangeException) {
                throw $exception;
            }
            error_log('Initial password setup failed correlation=' . $correlation . ' exception=' . get_class($exception));
            throw new UserPasswordChangeException('UC2_OPERATION_FAILED', $correlation);
        }
    }
}
