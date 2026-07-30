<?php

declare(strict_types=1);

namespace OneId\App\Auth\UserMfa;

use Throwable;

final class UserMfaPendingLoginCoordinator
{
    /** @var callable(): int */
    private $clock;

    public function __construct(
        private readonly UserMfaPendingLoginPersistenceInterface $persistence,
        ?callable $clock = null
    ) {
        $this->clock = $clock ?? static fn(): int => time();
    }

    /** @return array<string, mixed> */
    public function begin(
        string $userId,
        string $primaryMethod,
        string $sessionId,
        string $userAgent,
        string $ipAddress,
        UserLoginMfaPolicy $policy,
        bool $pilotEligible = false,
        bool $categoryEnforced = true
    ): array {
        $correlationId = bin2hex(random_bytes(16));
        $userId = $this->userId($userId, $correlationId);
        $primaryMethod = strtoupper(trim($primaryMethod));
        $binding = UserMfaRequestBinding::fromRequest($sessionId, $userAgent, $ipAddress);

        if ($primaryMethod !== 'PASSWORD') {
            return [
                'status' => 1,
                'code' => 'USER_MFA_NOT_REQUIRED',
                'reason' => 'PRIMARY_METHOD_OUT_OF_SCOPE',
                'correlation_id' => $correlationId,
            ];
        }
        $required = ($policy->mode === 'ENFORCED' && $categoryEnforced)
            || ($policy->mode === 'PILOT_ENFORCED' && $pilotEligible);
        if (!$required) {
            return [
                'status' => 1,
                'code' => 'USER_MFA_NOT_REQUIRED',
                'reason' => $policy->mode === 'ENROLLMENT' ? 'ENROLLMENT_ONLY' : 'POLICY_OFF_OR_OUT_OF_SCOPE',
                'correlation_id' => $correlationId,
            ];
        }

        $transactionId = bin2hex(random_bytes(32));
        $started = false;
        try {
            $this->persistence->beginTransaction();
            $started = true;
            if ($this->persistence->createPendingLogin([
                'transaction_id' => $transactionId,
                'u_id' => $userId,
                'primary_method' => 'PASSWORD',
                'transaction_status' => 'PENDING',
                'session_binding_hash' => $binding['session_hash'],
                'browser_digest' => $binding['browser_digest'],
                'requesting_ip' => $binding['ip_address'],
                'policy_mode' => $policy->mode,
                'expires_at_epoch' => ($this->clock)() + $policy->pendingTtlSeconds,
                'correlation_id' => $correlationId,
            ]) !== 1) {
                throw new UserMfaPendingLoginException(
                    'USER_MFA_PENDING_CREATE_FAILED',
                    $correlationId
                );
            }
            $this->audit(
                'USER_MFA_PRIMARY_AUTH_PENDING',
                $userId,
                'created',
                'PASSWORD_ACCEPTED',
                $correlationId,
                $ipAddress
            );
            $this->persistence->commit();
            $started = false;
            return [
                'status' => 1,
                'code' => 'USER_MFA_REQUIRED',
                'transaction_id' => $transactionId,
                'expires_in_seconds' => $policy->pendingTtlSeconds,
                'correlation_id' => $correlationId,
            ];
        } catch (Throwable $exception) {
            if ($started) {
                if ($exception instanceof UserMfaPendingLoginException
                    && $exception->reason === 'USER_MFA_PENDING_EXPIRED'
                ) {
                    $this->persistence->commit();
                } else {
                    $this->persistence->rollback();
                }
            }
            if ($exception instanceof UserMfaPendingLoginException) {
                throw $exception;
            }
            throw new UserMfaPendingLoginException('USER_MFA_PENDING_FAILED', $correlationId);
        }
    }

    public function markVerified(
        string $transactionId,
        string $factorType,
        string $sessionId,
        string $userAgent,
        string $ipAddress
    ): void {
        $correlationId = bin2hex(random_bytes(16));
        $factorType = strtoupper(trim($factorType));
        if (!in_array($factorType, ['EMAIL_OTP', 'TOTP'], true)) {
            throw new UserMfaPendingLoginException('USER_MFA_FACTOR_INVALID', $correlationId);
        }
        $binding = UserMfaRequestBinding::fromRequest($sessionId, $userAgent, $ipAddress);
        $started = false;
        try {
            $this->persistence->beginTransaction();
            $started = true;
            $pending = $this->lockedPending($transactionId, $binding, $correlationId);
            if (($pending['transaction_status'] ?? '') !== 'PENDING') {
                throw new UserMfaPendingLoginException('USER_MFA_PENDING_REPLAYED', $correlationId);
            }
            if ($this->persistence->markFactorVerified($transactionId, $factorType) !== 1) {
                throw new UserMfaPendingLoginException('USER_MFA_VERIFY_NOT_APPLIED', $correlationId);
            }
            $this->audit(
                $factorType === 'TOTP' ? 'USER_MFA_TOTP_VERIFY' : 'USER_MFA_EMAIL_VERIFY',
                (string) $pending['u_id'],
                'verified',
                'FACTOR_VERIFIED',
                $correlationId,
                $ipAddress
            );
            $this->persistence->commit();
            $started = false;
        } catch (Throwable $exception) {
            if ($started) {
                if ($exception instanceof UserMfaPendingLoginException
                    && $exception->reason === 'USER_MFA_PENDING_EXPIRED'
                ) {
                    $this->persistence->commit();
                } else {
                    $this->persistence->rollback();
                }
            }
            if ($exception instanceof UserMfaPendingLoginException) {
                throw $exception;
            }
            throw new UserMfaPendingLoginException('USER_MFA_VERIFY_FAILED', $correlationId);
        }
    }

    /** @return array<string, mixed> */
    public function finalize(
        string $transactionId,
        string $sessionId,
        string $userAgent,
        string $ipAddress,
        UserMfaLoginFinalizerInterface $finalizer
    ): array {
        $correlationId = bin2hex(random_bytes(16));
        $binding = UserMfaRequestBinding::fromRequest($sessionId, $userAgent, $ipAddress);
        $started = false;
        $handle = null;
        try {
            $this->persistence->beginTransaction();
            $started = true;
            $pending = $this->lockedPending($transactionId, $binding, $correlationId);
            if (($pending['transaction_status'] ?? '') !== 'VERIFIED') {
                throw new UserMfaPendingLoginException('USER_MFA_FACTOR_REQUIRED', $correlationId);
            }
            $handle = $finalizer->prepare((string) $pending['u_id'], $correlationId);
            if ($this->persistence->consumePendingLogin($transactionId) !== 1) {
                $finalizer->compensate($handle);
                $handle = null;
                throw new UserMfaPendingLoginException('USER_MFA_PENDING_REPLAYED', $correlationId);
            }
            $this->audit(
                'USER_MFA_LOGIN_COMPLETE',
                (string) $pending['u_id'],
                'success',
                'MFA_VERIFIED',
                $correlationId,
                $ipAddress
            );
            $this->persistence->commit();
            $started = false;
            return [
                'status' => 1,
                'code' => 'USER_MFA_LOGIN_AUTHORIZED',
                'user_id' => (string) $pending['u_id'],
                'completion_handle' => $handle,
                'correlation_id' => $correlationId,
            ];
        } catch (Throwable $exception) {
            if ($started) {
                if ($exception instanceof UserMfaPendingLoginException
                    && $exception->reason === 'USER_MFA_PENDING_EXPIRED'
                ) {
                    $this->persistence->commit();
                } else {
                    $this->persistence->rollback();
                }
            }
            if ($handle !== null) {
                $finalizer->compensate($handle);
            }
            if ($exception instanceof UserMfaPendingLoginException) {
                throw $exception;
            }
            throw new UserMfaPendingLoginException('USER_MFA_FINALIZATION_FAILED', $correlationId);
        }
    }

    /** @param array{session_hash:string,browser_digest:string,ip_address:string} $binding */
    private function lockedPending(
        string $transactionId,
        array $binding,
        string $correlationId
    ): array {
        if (preg_match('/\A[a-f0-9]{64}\z/', $transactionId) !== 1) {
            throw new UserMfaPendingLoginException('USER_MFA_PENDING_INVALID', $correlationId);
        }
        $pending = $this->persistence->pendingLoginForUpdate($transactionId);
        if (!is_array($pending)) {
            throw new UserMfaPendingLoginException('USER_MFA_PENDING_NOT_FOUND', $correlationId);
        }
        if (!hash_equals((string) $pending['session_binding_hash'], $binding['session_hash'])
            || !hash_equals((string) $pending['browser_digest'], $binding['browser_digest'])
        ) {
            throw new UserMfaPendingLoginException('USER_MFA_BINDING_MISMATCH', $correlationId);
        }
        if ((int) ($pending['expires_at_epoch'] ?? 0) < ($this->clock)()) {
            if ($this->persistence->revokePendingLogin($transactionId, 'EXPIRED') !== 1) {
                throw new UserMfaPendingLoginException(
                    'USER_MFA_EXPIRY_NOT_APPLIED',
                    $correlationId
                );
            }
            $this->audit(
                'USER_MFA_PRIMARY_AUTH_PENDING',
                (string) $pending['u_id'],
                'expired',
                'TTL_EXCEEDED',
                $correlationId,
                $binding['ip_address']
            );
            throw new UserMfaPendingLoginException('USER_MFA_PENDING_EXPIRED', $correlationId);
        }
        return $pending;
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
            throw new UserMfaPendingLoginException('USER_MFA_AUDIT_FAILED', $correlationId);
        }
    }

    private function userId(string $userId, string $correlationId): string
    {
        $userId = trim($userId);
        if ($userId === ''
            || strlen($userId) > 20
            || preg_match('/\A[A-Za-z0-9._@-]+\z/', $userId) !== 1
        ) {
            throw new UserMfaPendingLoginException('USER_MFA_USER_INVALID', $correlationId);
        }
        return $userId;
    }
}
