<?php

declare(strict_types=1);

namespace OneId\App\Auth;

use Throwable;

require_once __DIR__ . '/AdminStepUpRateLimitConfig.php';

final class AdminStepUpEmailOtpService
{
    private const PURPOSES = [
        'ADMIN_ACCESS',
        'SECURITY_CONFIGURATION_CHANGE',
        'ACTIVE_SESSION_REVOCATION',
    ];

    public function __construct(
        private readonly object $operation,
        private readonly AdminStepUpEmailSenderInterface $sender,
        ?AdminStepUpRateLimitConfig $rateLimits = null
    ) {
        $this->rateLimits = $rateLimits ?? AdminStepUpRateLimitConfig::fromRuntime();
    }

    private readonly AdminStepUpRateLimitConfig $rateLimits;

    /** @return array<string, mixed> */
    public function request(
        string $adminId,
        string $purpose,
        string $sessionId,
        string $userAgent,
        string $ipAddress
    ): array {
        $correlationId = bin2hex(random_bytes(8));
        $adminId = $this->identifier($adminId, 'STEP_UP_ADMIN_INVALID', $correlationId);
        $purpose = $this->purpose($purpose, $correlationId);
        $sessionHash = $this->binding($sessionId, 'STEP_UP_SESSION_INVALID', $correlationId);
        $browserDigest = hash('sha256', substr($userAgent, 0, 1000));
        $ipAddress = $this->ip($ipAddress, $correlationId);
        $challengeId = bin2hex(random_bytes(32));
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $otpHash = password_hash($otp, PASSWORD_ARGON2ID);
        if (!is_string($otpHash)) {
            throw new AdminStepUpException('STEP_UP_OTP_HASH_FAILED', $correlationId);
        }

        $started = false;
        $challengeCreated = false;
        $context = null;
        try {
            $this->operation->beginTransaction();
            $started = true;
            $context = $this->operation->admin_step_up_request_context_for_update($adminId);
            if (!is_array($context)
                || (int) ($context['u_type'] ?? 0) !== 1
                || (int) ($context['avail_status'] ?? 0) !== 1
            ) {
                throw new AdminStepUpException('STEP_UP_ADMIN_NOT_ELIGIBLE', $correlationId);
            }
            if ((int) ($context['admin_2fa_enabled'] ?? 0) !== 1) {
                throw new AdminStepUpException('STEP_UP_DISABLED', $correlationId);
            }
            $email = trim((string) ($context['email'] ?? ''));
            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                throw new AdminStepUpException('STEP_UP_FACTOR_UNAVAILABLE', $correlationId);
            }

            $stats = $this->operation->admin_step_up_request_stats(
                $adminId,
                $purpose,
                $sessionHash,
                $ipAddress
            );
            if (!is_array($stats)) {
                throw new AdminStepUpException('STEP_UP_RATE_STATE_INVALID', $correlationId);
            }
            if ((int) ($stats['cooldown_seconds'] ?? 0) > 0) {
                throw new AdminStepUpException('STEP_UP_RESEND_COOLDOWN', $correlationId);
            }
            if ($this->rateLimits->exceeded($stats)) {
                throw new AdminStepUpException('STEP_UP_RATE_LIMITED', $correlationId);
            }

            $this->operation->admin_step_up_revoke_open_challenges(
                $adminId,
                $purpose,
                $sessionHash
            );
            if ($this->operation->admin_step_up_create_email_challenge([
                'challenge_id' => $challengeId,
                'admin_user_id' => $adminId,
                'purpose' => $purpose,
                'otp_hash' => $otpHash,
                'session_binding_hash' => $sessionHash,
                'browser_digest' => $browserDigest,
                'requesting_ip' => $ipAddress,
                'correlation_id' => $correlationId,
            ]) !== 1) {
                throw new AdminStepUpException('STEP_UP_CHALLENGE_CREATE_FAILED', $correlationId);
            }
            $this->audit(37, $adminId, $purpose, 'requested', $correlationId, $ipAddress);
            $this->operation->commit();
            $started = false;
            $challengeCreated = true;

            $sent = false;
            try {
                $sent = $this->sender->send(
                    $otp,
                    $email,
                    trim((string) ($context['display_name'] ?? 'Administrator'))
                );
            } finally {
                unset($otp);
            }
            if (!$sent) {
                $this->operation->admin_step_up_revoke_challenge($challengeId);
                $this->audit(43, $adminId, $purpose, 'delivery_failed', $correlationId, $ipAddress);
                throw new AdminStepUpException('STEP_UP_DELIVERY_FAILED', $correlationId);
            }
            $this->operation->beginTransaction();
            $started = true;
            if ($this->operation->admin_step_up_mark_challenge_sent($challengeId) !== 1) {
                throw new AdminStepUpException('STEP_UP_CHALLENGE_ACTIVATION_FAILED', $correlationId);
            }
            $this->audit(38, $adminId, $purpose, 'sent', $correlationId, $ipAddress);
            $this->operation->commit();
            $started = false;

            return [
                'status' => 1,
                'code' => 'STEP_UP_CHALLENGE_SENT',
                'challenge_id' => $challengeId,
                'purpose' => $purpose,
                'factor' => 'EMAIL_OTP',
                'masked_email' => $this->maskEmail($email),
                'expires_in_seconds' => 300,
                'resend_after_seconds' => 60,
                'correlation_id' => $correlationId,
            ];
        } catch (AdminStepUpException $exception) {
            if ($started) {
                $this->operation->rollback();
            }
            if ($challengeCreated) {
                $this->operation->admin_step_up_revoke_challenge($challengeId);
            }
            if (in_array($exception->reason, ['STEP_UP_RATE_LIMITED', 'STEP_UP_RESEND_COOLDOWN'], true)) {
                $this->audit(42, $adminId, $purpose, $exception->reason, $correlationId, $ipAddress);
            }
            throw $exception;
        } catch (Throwable $exception) {
            if ($started) {
                $this->operation->rollback();
            }
            error_log('F7.2 request failed correlation=' . $correlationId . ' exception=' . get_class($exception));
            throw new AdminStepUpException('STEP_UP_REQUEST_FAILED', $correlationId);
        }
    }

    /** @return array<string, mixed> */
    public function verify(
        string $adminId,
        string $purpose,
        string $challengeId,
        string $submittedOtp,
        string $sessionId,
        string $userAgent,
        string $ipAddress
    ): array {
        $correlationId = bin2hex(random_bytes(8));
        $adminId = $this->identifier($adminId, 'STEP_UP_ADMIN_INVALID', $correlationId);
        $purpose = $this->purpose($purpose, $correlationId);
        if (preg_match('/\A[a-f0-9]{64}\z/', $challengeId) !== 1) {
            throw new AdminStepUpException('STEP_UP_CHALLENGE_INVALID', $correlationId);
        }
        if (preg_match('/\A[0-9]{6}\z/', $submittedOtp) !== 1) {
            throw new AdminStepUpException('STEP_UP_VERIFICATION_FAILED', $correlationId);
        }
        $sessionHash = $this->binding($sessionId, 'STEP_UP_SESSION_INVALID', $correlationId);
        $browserDigest = hash('sha256', substr($userAgent, 0, 1000));
        $ipAddress = $this->ip($ipAddress, $correlationId);
        $started = false;

        try {
            $this->operation->beginTransaction();
            $started = true;
            $challenge = $this->operation->admin_step_up_challenge_for_update($challengeId);
            if (is_array($challenge) && (int) ($challenge['is_expired'] ?? 1) === 1) {
                $this->operation->admin_step_up_revoke_challenge($challengeId);
                $this->audit(41, $adminId, $purpose, 'expired', $correlationId, $ipAddress);
                $this->operation->commit();
                $started = false;
                throw new AdminStepUpException('STEP_UP_EXPIRED', $correlationId);
            }
            if (is_array($challenge)
                && (($challenge['consumed_at'] ?? null) !== null || ($challenge['revoked_at'] ?? null) !== null)
            ) {
                throw new AdminStepUpException('STEP_UP_REPLAYED', $correlationId);
            }
            if (!is_array($challenge)
                || (int) ($challenge['admin_2fa_enabled'] ?? 0) !== 1
                || !hash_equals($adminId, (string) ($challenge['admin_user_id'] ?? ''))
                || !hash_equals($purpose, (string) ($challenge['purpose'] ?? ''))
                || !hash_equals($sessionHash, (string) ($challenge['session_binding_hash'] ?? ''))
                || !hash_equals($browserDigest, (string) ($challenge['browser_digest'] ?? ''))
                || ($challenge['sent_at'] ?? null) === null
                || (int) ($challenge['attempts'] ?? 0) >= (int) ($challenge['max_attempts'] ?? 5)
            ) {
                throw new AdminStepUpException('STEP_UP_VERIFICATION_FAILED', $correlationId);
            }

            if (!password_verify($submittedOtp, (string) ($challenge['otp_hash'] ?? ''))) {
                $this->operation->admin_step_up_record_failed_attempt($challengeId);
                $this->audit(40, $adminId, $purpose, 'invalid_code', $correlationId, $ipAddress);
                $this->operation->commit();
                $started = false;
                throw new AdminStepUpException('STEP_UP_VERIFICATION_FAILED', $correlationId);
            }

            if ($this->operation->admin_step_up_consume_challenge($challengeId) !== 1) {
                throw new AdminStepUpException('STEP_UP_REPLAYED', $correlationId);
            }
            if ($this->operation->admin_step_up_create_grant([
                'grant_id' => bin2hex(random_bytes(32)),
                'admin_user_id' => $adminId,
                'session_binding_hash' => $sessionHash,
                'browser_digest' => $browserDigest,
                'purpose' => $purpose,
                'verified_factor' => 'EMAIL_OTP',
                'lifetime_minutes' => $lifetimeMinutes=(int)($challenge['admin_step_up_lifetime_minutes']??15),
                'correlation_id' => $correlationId,
            ]) !== 1) {
                throw new AdminStepUpException('STEP_UP_GRANT_CREATE_FAILED', $correlationId);
            }
            $this->audit(39, $adminId, $purpose, 'verified', $correlationId, $ipAddress);
            $this->operation->commit();
            $started = false;

            return [
                'status' => 1,
                'code' => 'STEP_UP_VERIFIED',
                'purpose' => $purpose,
                'factor' => 'EMAIL_OTP',
                'grant_expires_in_seconds' => $lifetimeMinutes*60,
                'correlation_id' => $correlationId,
            ];
        } catch (AdminStepUpException $exception) {
            if ($started) {
                $this->operation->rollback();
            }
            if ($exception->reason === 'STEP_UP_AUDIT_FAILED') {
                $this->operation->admin_step_up_revoke_challenge($challengeId);
            }
            throw $exception;
        } catch (Throwable $exception) {
            if ($started) {
                $this->operation->rollback();
            }
            error_log('F7.2 verify failed correlation=' . $correlationId . ' exception=' . get_class($exception));
            throw new AdminStepUpException('STEP_UP_VERIFICATION_FAILED', $correlationId);
        }
    }

    private function audit(int $event, string $admin, string $purpose, string $outcome, string $correlation, string $ip): void
    {
        $safeOutcome = preg_replace('/[^A-Za-z0-9_]/', '_', $outcome) ?: 'unknown';
        if ($this->operation->syslog_record(
            $event,
            sprintf('admin=%s action=admin_step_up purpose=%s outcome=%s correlation=%s', $admin, $purpose, $safeOutcome, $correlation),
            $ip
        ) !== 1) {
            throw new AdminStepUpException('STEP_UP_AUDIT_FAILED', $correlation);
        }
    }

    private function identifier(string $value, string $reason, string $correlation): string
    {
        $value = trim($value);
        if ($value === '' || strlen($value) > 20 || preg_match('/\A[A-Za-z0-9._@-]+\z/', $value) !== 1) {
            throw new AdminStepUpException($reason, $correlation);
        }
        return $value;
    }

    private function purpose(string $purpose, string $correlation): string
    {
        $purpose = strtoupper(trim($purpose));
        if (!in_array($purpose, self::PURPOSES, true)) {
            throw new AdminStepUpException('STEP_UP_PURPOSE_INVALID', $correlation);
        }
        return $purpose;
    }

    private function binding(string $sessionId, string $reason, string $correlation): string
    {
        if ($sessionId === '' || strlen($sessionId) > 256) {
            throw new AdminStepUpException($reason, $correlation);
        }
        return hash('sha256', $sessionId);
    }

    private function ip(string $ip, string $correlation): string
    {
        $ip = trim($ip);
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            throw new AdminStepUpException('STEP_UP_IP_INVALID', $correlation);
        }
        return $ip;
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = explode('@', $email, 2);
        return substr($local, 0, 1) . str_repeat('*', max(2, min(8, strlen($local) - 1))) . '@' . $domain;
    }
}
