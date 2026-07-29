<?php

declare(strict_types=1);

namespace OneId\App\Auth\UserMfa;

use Throwable;

final class UserMfaTotpService
{
    public function __construct(
        private readonly UserMfaTotpPersistenceInterface $persistence,
        private readonly UserMfaTotpPrimitive $primitive,
        private readonly string $issuer = 'OneID@UPNM'
    ) {
    }

    /** @return array<string, string> */
    public function beginEnrollment(
        string $authenticatedUserId,
        string $accountLabel,
        string $sessionId,
        string $userAgent,
        string $deviceLabel = 'Microsoft Authenticator'
    ): array {
        $correlationId = bin2hex(random_bytes(16));
        $userId = $this->userId($authenticatedUserId, $correlationId);
        $binding = UserMfaRequestBinding::fromRequest($sessionId, $userAgent, '127.0.0.1');
        $material = $this->primitive->enroll($this->issuer, $accountLabel);
        $started = false;

        try {
            $this->persistence->beginTransaction();
            $started = true;
            $policy = $this->persistence->policyForUpdate();
            if (!is_array($policy) || !(bool) ($policy['totp_enabled'] ?? false)) {
                throw new UserMfaTotpException('USER_MFA_TOTP_DISABLED', $correlationId);
            }
            if (is_array($this->persistence->activeFactorForUpdate($userId))) {
                throw new UserMfaTotpException('USER_MFA_TOTP_ALREADY_ACTIVE', $correlationId);
            }
            $factorId = $this->persistence->createPendingFactor([
                'u_id' => $userId,
                'factor_type' => 'TOTP',
                'encrypted_secret' => $material['encrypted_secret'],
                'secret_nonce' => $material['secret_nonce'],
                'key_version' => $material['key_version'],
                'device_label' => mb_substr(trim($deviceLabel), 0, 100),
                'enrollment_session_hash' => $binding['session_hash'],
                'enrollment_browser_digest' => $binding['browser_digest'],
                'correlation_id' => $correlationId,
            ]);
            if ($factorId === false) {
                throw new UserMfaTotpException('USER_MFA_TOTP_ENROLLMENT_FAILED', $correlationId);
            }
            $this->audit('USER_MFA_FACTOR_ENROLL', $userId, $userId, 'pending', 'SELF_SERVICE', '', $correlationId);
            $this->persistence->commit();
            $started = false;

            return [
                'factor_id' => $factorId,
                'provisioning_uri' => $material['provisioning_uri'],
                'correlation_id' => $correlationId,
                'cache_control' => 'no-store',
            ];
        } catch (Throwable $exception) {
            if ($started) {
                $this->persistence->rollback();
            }
            if ($exception instanceof UserMfaTotpException) {
                throw $exception;
            }
            throw new UserMfaTotpException('USER_MFA_TOTP_ENROLLMENT_FAILED', $correlationId);
        } finally {
            unset($material['secret'], $material['provisioning_uri']);
        }
    }

    public function confirmEnrollment(
        string $authenticatedUserId,
        string $factorId,
        string $code,
        string $sessionId,
        string $userAgent
    ): void {
        $correlationId = bin2hex(random_bytes(16));
        $userId = $this->userId($authenticatedUserId, $correlationId);
        $binding = UserMfaRequestBinding::fromRequest($sessionId, $userAgent, '127.0.0.1');
        $started = false;
        try {
            $this->persistence->beginTransaction();
            $started = true;
            $policy = $this->persistence->policyForUpdate();
            if (!is_array($policy) || !(bool) ($policy['totp_enabled'] ?? false)) {
                throw new UserMfaTotpException('USER_MFA_TOTP_DISABLED', $correlationId);
            }
            $factor = $this->persistence->pendingFactorForUpdate($factorId, $userId);
            if (!is_array($factor)
                || !hash_equals((string) $factor['enrollment_session_hash'], $binding['session_hash'])
                || !hash_equals((string) $factor['enrollment_browser_digest'], $binding['browser_digest'])
            ) {
                throw new UserMfaTotpException('USER_MFA_TOTP_ENROLLMENT_INVALID', $correlationId);
            }
            $step = $this->match($factor, $code, null, $correlationId);
            if ($this->persistence->confirmFactor($factorId, $step) !== 1
                || $this->persistence->setPreference($userId, 'TOTP', $correlationId) !== 1
            ) {
                throw new UserMfaTotpException('USER_MFA_TOTP_CONFIRM_FAILED', $correlationId);
            }
            $this->audit('USER_MFA_FACTOR_ENROLL', $userId, $userId, 'confirmed', 'SELF_SERVICE', '', $correlationId);
            $this->persistence->commit();
            $started = false;
        } catch (Throwable $exception) {
            if ($started) {
                $this->persistence->rollback();
            }
            $this->rethrow($exception, 'USER_MFA_TOTP_CONFIRM_FAILED', $correlationId);
        }
    }

    public function verify(string $authenticatedUserId, string $code): int
    {
        $correlationId = bin2hex(random_bytes(16));
        $userId = $this->userId($authenticatedUserId, $correlationId);
        $started = false;
        try {
            $this->persistence->beginTransaction();
            $started = true;
            $policy = $this->persistence->policyForUpdate();
            if (!is_array($policy) || !(bool) ($policy['totp_enabled'] ?? false)) {
                throw new UserMfaTotpException('USER_MFA_TOTP_DISABLED_USE_EMAIL', $correlationId);
            }
            $factor = $this->persistence->activeFactorForUpdate($userId);
            if (!is_array($factor)) {
                throw new UserMfaTotpException('USER_MFA_TOTP_UNAVAILABLE_USE_EMAIL', $correlationId);
            }
            $lastStep = isset($factor['last_used_time_step']) ? (int) $factor['last_used_time_step'] : null;
            $step = $this->match($factor, $code, $lastStep, $correlationId);
            if ($this->persistence->updateLastUsedStep(
                (string) $factor['factor_id'],
                $lastStep ?? -1,
                $step
            ) !== 1) {
                throw new UserMfaTotpException('USER_MFA_TOTP_REPLAYED', $correlationId);
            }
            $this->audit('USER_MFA_TOTP_VERIFY', $userId, $userId, 'verified', 'TOTP_VERIFIED', '', $correlationId);
            $this->persistence->commit();
            $started = false;
            return $step;
        } catch (Throwable $exception) {
            if ($started) {
                $this->persistence->rollback();
            }
            $this->rethrow($exception, 'USER_MFA_TOTP_VERIFY_FAILED', $correlationId);
        }
    }

    public function setPreference(string $authenticatedUserId, string $factorType): void
    {
        $correlationId = bin2hex(random_bytes(16));
        $userId = $this->userId($authenticatedUserId, $correlationId);
        $factorType = strtoupper(trim($factorType));
        $started = false;
        try {
            $this->persistence->beginTransaction();
            $started = true;
            $policy = $this->persistence->policyForUpdate();
            if (!in_array($factorType, ['EMAIL_OTP', 'TOTP'], true)
                || ($factorType === 'TOTP'
                    && (!is_array($policy) || !(bool) ($policy['totp_enabled'] ?? false)
                        || !is_array($this->persistence->activeFactorForUpdate($userId))))
            ) {
                throw new UserMfaTotpException('USER_MFA_FACTOR_UNAVAILABLE', $correlationId);
            }
            if ($this->persistence->setPreference($userId, $factorType, $correlationId) !== 1) {
                throw new UserMfaTotpException('USER_MFA_PREFERENCE_FAILED', $correlationId);
            }
            $this->audit('USER_MFA_FACTOR_PREFERENCE', $userId, $userId, 'updated', $factorType, '', $correlationId);
            $this->persistence->commit();
            $started = false;
        } catch (Throwable $exception) {
            if ($started) {
                $this->persistence->rollback();
            }
            $this->rethrow($exception, 'USER_MFA_PREFERENCE_FAILED', $correlationId);
        }
    }

    public function selfRevoke(string $authenticatedUserId, bool $freshFactorVerified, string $reason): void
    {
        $userId = trim($authenticatedUserId);
        $this->revoke($userId, $userId, $freshFactorVerified, true, 'SELF_SERVICE', $reason, '', '');
    }

    public function adminRecover(
        string $administratorId,
        string $targetUserId,
        bool $freshAdminStepUp,
        string $verifierAdministratorId,
        string $reason,
        string $ticketReference,
        string $typedConfirmation
    ): void {
        $authorized = $freshAdminStepUp
            && $administratorId !== $verifierAdministratorId
            && trim($verifierAdministratorId) !== ''
            && hash_equals($targetUserId, $typedConfirmation);
        $this->revoke(
            $administratorId,
            $targetUserId,
            $authorized,
            false,
            'ADMIN_RECOVERY',
            $reason,
            $ticketReference,
            $verifierAdministratorId
        );
    }

    /** @return list<string> */
    public function availableFactors(bool $totpEnabled, bool $activeTotp): array
    {
        return $totpEnabled && $activeTotp ? ['TOTP', 'EMAIL_OTP'] : ['EMAIL_OTP'];
    }

    private function revoke(
        string $actorId,
        string $targetId,
        bool $authorized,
        bool $selfService,
        string $mode,
        string $reason,
        string $reference,
        string $verifier
    ): void {
        $correlationId = bin2hex(random_bytes(16));
        $actorId = $this->userId($actorId, $correlationId);
        $targetId = $this->userId($targetId, $correlationId);
        if (!$authorized
            || ($selfService && !hash_equals($actorId, $targetId))
            || trim($reason) === ''
            || (!$selfService && (trim($reference) === '' || trim($verifier) === ''))
        ) {
            throw new UserMfaTotpException('USER_MFA_RECOVERY_NOT_AUTHORIZED', $correlationId);
        }
        $started = false;
        try {
            $this->persistence->beginTransaction();
            $started = true;
            $this->persistence->revokeFactors($targetId, $mode);
            $this->persistence->revokePendingChallenges($targetId, $mode);
            if ($this->persistence->setPreference($targetId, 'EMAIL_OTP', $correlationId) !== 1
                || $this->persistence->revokeUserSessions($targetId, $mode) < 0
            ) {
                throw new UserMfaTotpException('USER_MFA_RECOVERY_FAILED', $correlationId);
            }
            $this->audit('USER_MFA_FACTOR_REVOKE', $targetId, $actorId, 'revoked', $mode . ':' . trim($reason), $reference, $correlationId);
            $this->persistence->commit();
            $started = false;
        } catch (Throwable $exception) {
            if ($started) {
                $this->persistence->rollback();
            }
            $this->rethrow($exception, 'USER_MFA_RECOVERY_FAILED', $correlationId);
        }
    }

    /** @param array<string, mixed> $factor */
    private function match(array $factor, string $code, ?int $lastStep, string $correlationId): int
    {
        try {
            return $this->primitive->matchEncrypted(
                (string) $factor['encrypted_secret'],
                (string) $factor['secret_nonce'],
                (string) $factor['key_version'],
                $code,
                $lastStep
            );
        } catch (Throwable) {
            throw new UserMfaTotpException('USER_MFA_TOTP_INVALID_OR_REPLAYED', $correlationId);
        }
    }

    private function audit(string $event, string $target, string $actor, string $outcome, string $reason, string $reference, string $correlationId): void
    {
        if ($this->persistence->recordAudit($event, $target, $actor, $outcome, $reason, $reference, $correlationId) !== 1) {
            throw new UserMfaTotpException('USER_MFA_AUDIT_FAILED', $correlationId);
        }
    }

    private function userId(string $value, string $correlationId): string
    {
        $value = trim($value);
        if ($value === '' || strlen($value) > 20 || preg_match('/\A[A-Za-z0-9._@-]+\z/', $value) !== 1) {
            throw new UserMfaTotpException('USER_MFA_USER_INVALID', $correlationId);
        }
        return $value;
    }

    private function rethrow(Throwable $exception, string $fallback, string $correlationId): never
    {
        if ($exception instanceof UserMfaTotpException) {
            throw $exception;
        }
        throw new UserMfaTotpException($fallback, $correlationId);
    }
}
