<?php

declare(strict_types=1);

namespace OneId\App\Auth;

use RuntimeException;
use Throwable;

final class UserPortalSessionService
{
    public function __construct(private readonly object $operation)
    {
    }

    /** @return array<string, int|string|bool> */
    public function status(): array
    {
        return $this->response('USER_SESSION_ACTIVE');
    }

    /** @return array<string, int|string|bool> */
    public function renew(string $userId, string $ipAddress): array
    {
        $userId = trim($userId);
        if ($userId === '' || !\oneid_is_authenticated()) {
            throw new RuntimeException('USER_SESSION_EXPIRED');
        }

        $this->audit(69, sprintf(
            'user=%s action=user_portal_session_renew outcome=renewed token_rotated=0 token_revoked=0',
            $userId
        ), $ipAddress, true);
        \oneid_refresh_session_activity();
        \oneid_refresh_configured_sso_cookie($this->operation);

        return $this->response('USER_SESSION_RENEWED');
    }

    /** @return array<string, int|string|bool> */
    public function expire(string $userId, string $ipAddress): array
    {
        $userId = trim($userId);
        $this->audit(70, sprintf(
            'user=%s action=user_portal_session_end outcome=ended token_revoked=0',
            $userId
        ), $ipAddress, false);

        \oneid_clear_sso_cookie();
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['oneid_portal_session_expired_at'] = time();

        return [
            'status' => 1,
            'authenticated' => false,
            'code' => 'USER_SESSION_EXPIRED',
            'reason' => 'PORTAL_SESSION_ENDED',
            'server_epoch' => time(),
        ];
    }

    /** @return array<string, int|string|bool> */
    private function response(string $code): array
    {
        $deadline = \oneid_current_session_deadline_state($this->operation);
        return [
            'status' => 1,
            'authenticated' => true,
            'code' => $code,
            'reason' => $code === 'USER_SESSION_RENEWED' ? 'USER_CONFIRMED_ACTIVITY' : 'SESSION_VALID',
        ] + $deadline;
    }

    private function audit(int $eventId, string $detail, string $ipAddress, bool $required): void
    {
        if (filter_var($ipAddress, FILTER_VALIDATE_IP) === false) {
            $ipAddress = '0.0.0.0';
        }
        try {
            $written = method_exists($this->operation, 'syslog_record')
                && $this->operation->syslog_record($eventId, $detail, $ipAddress) === 1;
        } catch (Throwable) {
            $written = false;
        }
        if (!$written && $required) {
            throw new RuntimeException('SESSION_STATUS_UNAVAILABLE');
        }
        if (!$written) {
            error_log('User portal session end audit unavailable');
        }
    }
}
