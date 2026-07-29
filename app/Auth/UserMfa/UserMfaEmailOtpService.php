<?php

declare(strict_types=1);

namespace OneId\App\Auth\UserMfa;

use Throwable;

final class UserMfaEmailOtpService
{
    /** @var callable(): int */
    private $clock;

    public function __construct(
        private readonly UserMfaEmailOtpPersistenceInterface $persistence,
        private readonly UserMfaEmailSenderInterface $sender,
        private readonly UserMfaRateLimitConfig $rateLimits = new UserMfaRateLimitConfig(),
        ?callable $clock = null
    ) {
        $this->clock = $clock ?? static fn(): int => time();
    }

    /** @return array<string, mixed> */
    public function request(
        string $transactionId,
        string $userId,
        string $sessionId,
        string $userAgent,
        string $ipAddress,
        string $locale = 'ms'
    ): array {
        $correlationId = bin2hex(random_bytes(16));
        $transactionId = $this->transactionId($transactionId, $correlationId);
        $userId = $this->userId($userId, $correlationId);
        $binding = UserMfaRequestBinding::fromRequest($sessionId, $userAgent, $ipAddress);
        $locale = in_array($locale, ['ms', 'en'], true) ? $locale : 'ms';
        $challengeId = bin2hex(random_bytes(32));
        $otp = UserMfaOtp::generate();
        $otpHash = UserMfaOtp::hash($otp);
        $started = false;
        $challengeCreated = false;
        $context = null;

        try {
            $this->persistence->beginTransaction();
            $started = true;
            $context = $this->persistence->emailChallengeContextForUpdate(
                $transactionId,
                $userId
            );
            if (!is_array($context)
                || ($context['transaction_status'] ?? '') !== 'PENDING'
                || (int) ($context['email_enabled'] ?? 0) !== 1
            ) {
                throw new UserMfaEmailOtpException(
                    'USER_MFA_EMAIL_UNAVAILABLE',
                    $correlationId
                );
            }
            $this->assertContextBinding($context, $binding, $correlationId);
            if ((int) ($context['expires_at_epoch'] ?? 0) < ($this->clock)()) {
                throw new UserMfaEmailOtpException(
                    'USER_MFA_PENDING_EXPIRED',
                    $correlationId
                );
            }
            $email = trim((string) ($context['email'] ?? ''));
            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                throw new UserMfaEmailOtpException(
                    'USER_MFA_EMAIL_UNAVAILABLE',
                    $correlationId
                );
            }
            $destinationHmac = hash_hmac(
                'sha256',
                strtolower($email),
                $binding['session_hash']
            );
            $stats = $this->persistence->emailRequestStatsForUpdate(
                $userId,
                $binding['session_hash'],
                $ipAddress,
                $destinationHmac
            );
            if ($this->rateLimits->cooldownActive($stats)) {
                throw new UserMfaEmailOtpException(
                    'USER_MFA_RESEND_COOLDOWN',
                    $correlationId
                );
            }
            if ($this->rateLimits->exceeded($stats)) {
                throw new UserMfaEmailOtpException(
                    'USER_MFA_RATE_LIMITED',
                    $correlationId
                );
            }
            $this->persistence->revokeOpenEmailChallenges($transactionId);
            if ($this->persistence->createEmailChallenge([
                'challenge_id' => $challengeId,
                'transaction_id' => $transactionId,
                'u_id' => $userId,
                'factor_type' => 'EMAIL_OTP',
                'otp_hash' => $otpHash,
                'destination_hmac' => $destinationHmac,
                'max_attempts' => (int) ($context['max_attempts'] ?? 5),
                'expires_at_epoch' => min(
                    (int) $context['expires_at_epoch'],
                    ($this->clock)() + (int) ($context['otp_ttl_seconds'] ?? 300)
                ),
                'correlation_id' => $correlationId,
            ]) !== 1) {
                throw new UserMfaEmailOtpException(
                    'USER_MFA_CHALLENGE_CREATE_FAILED',
                    $correlationId
                );
            }
            $challengeCreated = true;
            $this->audit(
                'USER_MFA_EMAIL_CHALLENGE',
                $userId,
                'requested',
                'OTP_REQUESTED',
                $correlationId,
                $ipAddress
            );
            $this->persistence->commit();
            $started = false;

            $sent = false;
            try {
                $sent = $this->sender->send(
                    $otp,
                    $email,
                    trim((string) ($context['display_name'] ?? 'OneID User')),
                    $locale
                );
            } finally {
                unset($otp);
            }
            if (!$sent) {
                $this->persistence->revokeChallenge($challengeId, 'DELIVERY_FAILED');
                $this->audit(
                    'USER_MFA_EMAIL_CHALLENGE',
                    $userId,
                    'delivery_failed',
                    'SMTP_FAILURE',
                    $correlationId,
                    $ipAddress
                );
                throw new UserMfaEmailOtpException(
                    'USER_MFA_DELIVERY_FAILED',
                    $correlationId
                );
            }

            $this->persistence->beginTransaction();
            $started = true;
            if ($this->persistence->markChallengeSent($challengeId) !== 1) {
                throw new UserMfaEmailOtpException(
                    'USER_MFA_CHALLENGE_ACTIVATION_FAILED',
                    $correlationId
                );
            }
            $this->audit(
                'USER_MFA_EMAIL_CHALLENGE',
                $userId,
                'sent',
                'OTP_SENT',
                $correlationId,
                $ipAddress
            );
            $this->persistence->commit();
            $started = false;

            return [
                'status' => 1,
                'code' => 'USER_MFA_CHALLENGE_SENT',
                'challenge_id' => $challengeId,
                'masked_email' => $this->maskEmail($email),
                'expires_in_seconds' => (int) ($context['otp_ttl_seconds'] ?? 300),
                'resend_after_seconds' => $this->rateLimits->resendCooldownSeconds,
                'correlation_id' => $correlationId,
            ];
        } catch (Throwable $exception) {
            if ($started) {
                $this->persistence->rollback();
            }
            if ($challengeCreated
                && !($exception instanceof UserMfaEmailOtpException
                    && $exception->reason === 'USER_MFA_DELIVERY_FAILED')
            ) {
                $this->persistence->revokeChallenge($challengeId, 'REQUEST_FAILED');
            }
            if ($exception instanceof UserMfaEmailOtpException) {
                throw $exception;
            }
            throw new UserMfaEmailOtpException('USER_MFA_EMAIL_REQUEST_FAILED', $correlationId);
        }
    }

    /** @return array<string, mixed> */
    public function verify(
        string $transactionId,
        string $challengeId,
        string $submittedOtp,
        string $sessionId,
        string $userAgent,
        string $ipAddress
    ): array {
        $correlationId = bin2hex(random_bytes(16));
        $transactionId = $this->transactionId($transactionId, $correlationId);
        $challengeId = $this->challengeId($challengeId, $correlationId);
        $binding = UserMfaRequestBinding::fromRequest($sessionId, $userAgent, $ipAddress);
        $started = false;

        try {
            $this->persistence->beginTransaction();
            $started = true;
            $challenge = $this->persistence->emailChallengeForUpdate($challengeId);
            if (!is_array($challenge)
                || !hash_equals($transactionId, (string) ($challenge['transaction_id'] ?? ''))
                || ($challenge['factor_type'] ?? '') !== 'EMAIL_OTP'
            ) {
                throw new UserMfaEmailOtpException(
                    'USER_MFA_CHALLENGE_INVALID',
                    $correlationId
                );
            }
            $this->assertContextBinding($challenge, $binding, $correlationId);
            if (($challenge['transaction_status'] ?? '') !== 'PENDING'
                || ($challenge['consumed_at'] ?? null) !== null
                || ($challenge['revoked_at'] ?? null) !== null
            ) {
                throw new UserMfaEmailOtpException(
                    'USER_MFA_CHALLENGE_REPLAYED',
                    $correlationId
                );
            }
            if ((int) ($challenge['expires_at_epoch'] ?? 0) < ($this->clock)()) {
                $this->persistence->revokeChallenge($challengeId, 'EXPIRED');
                $this->audit(
                    'USER_MFA_EMAIL_VERIFY',
                    (string) $challenge['u_id'],
                    'expired',
                    'TTL_EXCEEDED',
                    $correlationId,
                    $ipAddress
                );
                $this->persistence->commit();
                $started = false;
                throw new UserMfaEmailOtpException(
                    'USER_MFA_CHALLENGE_EXPIRED',
                    $correlationId
                );
            }
            if (!UserMfaOtp::verify($submittedOtp, (string) ($challenge['otp_hash'] ?? ''))) {
                if ($this->persistence->recordFailedAttempt($challengeId) !== 1) {
                    throw new UserMfaEmailOtpException(
                        'USER_MFA_ATTEMPT_NOT_RECORDED',
                        $correlationId
                    );
                }
                $this->audit(
                    'USER_MFA_EMAIL_VERIFY',
                    (string) $challenge['u_id'],
                    'rejected',
                    'OTP_INVALID',
                    $correlationId,
                    $ipAddress
                );
                $this->persistence->commit();
                $started = false;
                throw new UserMfaEmailOtpException(
                    'USER_MFA_VERIFICATION_FAILED',
                    $correlationId
                );
            }
            if ($this->persistence->consumeEmailChallenge($challengeId) !== 1
                || $this->persistence->markPendingLoginVerified(
                    $transactionId,
                    'EMAIL_OTP'
                ) !== 1
            ) {
                throw new UserMfaEmailOtpException(
                    'USER_MFA_VERIFICATION_NOT_APPLIED',
                    $correlationId
                );
            }
            $this->audit(
                'USER_MFA_EMAIL_VERIFY',
                (string) $challenge['u_id'],
                'verified',
                'OTP_VERIFIED',
                $correlationId,
                $ipAddress
            );
            $this->persistence->commit();
            $started = false;

            return [
                'status' => 1,
                'code' => 'USER_MFA_FACTOR_VERIFIED',
                'transaction_id' => $transactionId,
                'factor' => 'EMAIL_OTP',
                'correlation_id' => $correlationId,
            ];
        } catch (Throwable $exception) {
            if ($started) {
                $this->persistence->rollback();
            }
            if ($exception instanceof UserMfaEmailOtpException) {
                throw $exception;
            }
            throw new UserMfaEmailOtpException('USER_MFA_EMAIL_VERIFY_FAILED', $correlationId);
        }
    }

    /** @param array<string, mixed> $context
     *  @param array{session_hash:string,browser_digest:string,ip_address:string} $binding
     */
    private function assertContextBinding(
        array $context,
        array $binding,
        string $correlationId
    ): void {
        if (!hash_equals((string) ($context['session_binding_hash'] ?? ''), $binding['session_hash'])
            || !hash_equals((string) ($context['browser_digest'] ?? ''), $binding['browser_digest'])
        ) {
            throw new UserMfaEmailOtpException(
                'USER_MFA_BINDING_MISMATCH',
                $correlationId
            );
        }
    }

    private function audit(
        string $event,
        string $userId,
        string $outcome,
        string $reason,
        string $correlationId,
        string $ipAddress
    ): void {
        if ($this->persistence->recordAudit(
            $event,
            $userId,
            $outcome,
            $reason,
            $correlationId,
            $ipAddress
        ) !== 1) {
            throw new UserMfaEmailOtpException('USER_MFA_AUDIT_FAILED', $correlationId);
        }
    }

    private function transactionId(string $value, string $correlationId): string
    {
        if (preg_match('/\A[a-f0-9]{64}\z/', $value) !== 1) {
            throw new UserMfaEmailOtpException('USER_MFA_PENDING_INVALID', $correlationId);
        }
        return $value;
    }

    private function challengeId(string $value, string $correlationId): string
    {
        if (preg_match('/\A[a-f0-9]{64}\z/', $value) !== 1) {
            throw new UserMfaEmailOtpException('USER_MFA_CHALLENGE_INVALID', $correlationId);
        }
        return $value;
    }

    private function userId(string $value, string $correlationId): string
    {
        $value = trim($value);
        if ($value === ''
            || strlen($value) > 20
            || preg_match('/\A[A-Za-z0-9._@-]+\z/', $value) !== 1
        ) {
            throw new UserMfaEmailOtpException('USER_MFA_USER_INVALID', $correlationId);
        }
        return $value;
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = explode('@', $email, 2);
        $visible = substr($local, 0, min(2, strlen($local)));
        return $visible . str_repeat('*', max(3, strlen($local) - strlen($visible))) . '@' . $domain;
    }
}
