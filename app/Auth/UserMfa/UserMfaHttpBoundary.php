<?php

declare(strict_types=1);

namespace OneId\App\Auth\UserMfa;

use OneId\App\Auth\AdminStepUpException;
use RuntimeException;
use Throwable;

final class UserMfaHttpBoundary
{
    /** @var list<string> */
    private const ACTIONS = [
        'user_mfa_email_request',
        'user_mfa_email_resend',
        'user_mfa_email_verify',
        'user_mfa_totp_verify_login',
        'user_mfa_totp_enroll',
        'user_mfa_totp_confirm',
        'user_mfa_totp_preference',
        'user_mfa_totp_revoke',
        'user_mfa_totp_recovery_email_request',
        'user_mfa_totp_recovery_email_verify',
        'user_mfa_admin_recovery',
    ];

    /**
     * @param array<string, mixed> $post
     * @return array{action:string,user_id:string,session_id:string,user_agent:string,ip_address:string}
     */
    public function guard(
        string $method,
        array $post,
        string $expectedCsrf,
        string $sessionId,
        string $authenticatedUserId,
        string $userAgent,
        string $ipAddress
    ): array {
        UserMfaWebSecurityGate::assertStateChangingRequest(
            $method,
            $expectedCsrf,
            is_string($post['_csrf_token'] ?? null) ? $post['_csrf_token'] : ''
        );
        $matched = array_values(array_filter(
            self::ACTIONS,
            static fn(string $action): bool => array_key_exists($action, $post)
        ));
        if (count($matched) !== 1) {
            throw new RuntimeException('USER_MFA_ACTION_INVALID');
        }
        $userId = trim($authenticatedUserId);
        if ($userId === '' || $sessionId === '' || filter_var($ipAddress, FILTER_VALIDATE_IP) === false) {
            throw new RuntimeException('USER_MFA_REQUEST_CONTEXT_INVALID');
        }
        return [
            'action' => $matched[0],
            'user_id' => $userId,
            'session_id' => $sessionId,
            'user_agent' => substr($userAgent, 0, 1000),
            'ip_address' => $ipAddress,
        ];
    }

    /** @param array<string, mixed> $payload
     *  @return array<string, mixed>
     */
    public function safeError(Throwable $exception, array $payload = []): array
    {
        $correlationId = $exception instanceof AdminStepUpException
            || $exception instanceof UserMfaEmailOtpException
            || $exception instanceof UserMfaTotpException
            || $exception instanceof UserMfaPendingLoginException
            ? $exception->correlationId
            : bin2hex(random_bytes(16));
        unset(
            $payload['code'],
            $payload['otp'],
            $payload['secret'],
            $payload['password'],
            $payload['email'],
            $payload['session_id']
        );
        $reason = $exception instanceof AdminStepUpException
            || $exception instanceof UserMfaEmailOtpException
            || $exception instanceof UserMfaTotpException
            || $exception instanceof UserMfaPendingLoginException
            ? $exception->getMessage()
            : '';
        $safeReasons = [
            'USER_MFA_RESEND_COOLDOWN',
            'USER_MFA_RATE_LIMITED',
            'USER_MFA_DELIVERY_FAILED',
            'USER_MFA_EMAIL_UNAVAILABLE',
            'USER_MFA_PENDING_EXPIRED',
            'USER_MFA_CHALLENGE_INVALID',
            'USER_MFA_CHALLENGE_REPLAYED',
            'USER_MFA_CHALLENGE_EXPIRED',
            'USER_MFA_VERIFICATION_FAILED',
            'USER_MFA_TOTP_DISABLED_USE_EMAIL',
            'USER_MFA_TOTP_UNAVAILABLE_USE_EMAIL',
            'USER_MFA_TOTP_REPLAYED',
            'USER_MFA_TOTP_VERIFY_FAILED',
            'STEP_UP_VERIFICATION_FAILED',
            'STEP_UP_REPLAYED',
            'USER_MFA_RECOVERY_UNAVAILABLE',
            'USER_MFA_RECOVERY_PASSWORD_INVALID',
            'USER_MFA_RECOVERY_CHALLENGE_INVALID',
            'USER_MFA_RECOVERY_FACTOR_UNAVAILABLE',
        ];
        $safeCode = in_array($reason, ['STEP_UP_VERIFICATION_FAILED','STEP_UP_REPLAYED'], true)
            ? 'USER_MFA_VERIFICATION_FAILED'
            : (in_array($reason, $safeReasons, true) ? $reason : 'USER_MFA_REQUEST_REJECTED');
        return [
            'status' => 0,
            'code' => $safeCode,
            'message_key' => 'user_mfa.state.error',
            'correlation_id' => $correlationId,
        ];
    }
}
