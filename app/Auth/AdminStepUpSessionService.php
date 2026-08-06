<?php

declare(strict_types=1);

namespace OneId\App\Auth;

use Throwable;

final class AdminStepUpSessionService
{
    public function __construct(private readonly object $operation)
    {
    }

    /** @return array<string, mixed> */
    public function renew(
        string $adminId,
        string $sessionId,
        string $userAgent,
        string $ipAddress
    ): array {
        $correlationId = bin2hex(random_bytes(8));
        $adminId = trim($adminId);
        if ($adminId === '' || $sessionId === '') {
            throw new AdminStepUpException('STEP_UP_RENEWAL_CONTEXT_INVALID', $correlationId);
        }
        if (filter_var($ipAddress, FILTER_VALIDATE_IP) === false) {
            $ipAddress = '0.0.0.0';
        }

        $sessionHash = hash('sha256', $sessionId);
        $browserDigest = hash('sha256', substr($userAgent, 0, 1000));
        $started = false;

        try {
            $this->operation->beginTransaction();
            $started = true;
            $context = $this->operation->admin_step_up_renewal_context_for_update(
                $adminId,
                $sessionHash,
                $browserDigest
            );
            if (!is_array($context)
                || (int) ($context['u_type'] ?? 0) !== 1
                || (int) ($context['avail_status'] ?? 0) !== 1
                || (int) ($context['admin_2fa_enabled'] ?? 0) !== 1
            ) {
                throw new AdminStepUpException('STEP_UP_RENEWAL_UNAVAILABLE', $correlationId);
            }

            $lifetimeMinutes = (int) ($context['admin_step_up_lifetime_minutes'] ?? 0);
            if (!in_array($lifetimeMinutes, [5, 10, 15, 30], true)) {
                throw new AdminStepUpException('STEP_UP_CONFIGURATION_INVALID', $correlationId);
            }
            $factor = (string) ($context['verified_factor'] ?? '');
            if (!in_array($factor, ['EMAIL_OTP', 'TOTP'], true)) {
                throw new AdminStepUpException('STEP_UP_RENEWAL_UNAVAILABLE', $correlationId);
            }

            if ($this->operation->admin_step_up_revoke_active_access_grants(
                $adminId,
                $sessionHash,
                $browserDigest
            ) < 1) {
                throw new AdminStepUpException('STEP_UP_EXPIRED', $correlationId);
            }
            if ($this->operation->admin_step_up_create_grant([
                'grant_id' => bin2hex(random_bytes(32)),
                'admin_user_id' => $adminId,
                'session_binding_hash' => $sessionHash,
                'browser_digest' => $browserDigest,
                'purpose' => 'ADMIN_ACCESS',
                'verified_factor' => $factor,
                'lifetime_minutes' => $lifetimeMinutes,
                'correlation_id' => $correlationId,
            ]) !== 1) {
                throw new AdminStepUpException('STEP_UP_GRANT_CREATE_FAILED', $correlationId);
            }
            if ($this->operation->syslog_record(
                67,
                sprintf(
                    'admin=%s action=admin_access_renew outcome=renewed lifetime_minutes=%d correlation=%s',
                    $adminId,
                    $lifetimeMinutes,
                    $correlationId
                ),
                $ipAddress
            ) !== 1) {
                throw new AdminStepUpException('STEP_UP_AUDIT_FAILED', $correlationId);
            }

            $this->operation->commit();
            $started = false;
            return [
                'status' => 1,
                'code' => 'ADMIN_ACCESS_RENEWED',
                'grant_remaining_seconds' => $lifetimeMinutes * 60,
                'admin_step_up_lifetime_minutes' => $lifetimeMinutes,
                'server_epoch' => time(),
                'correlation_id' => $correlationId,
            ];
        } catch (AdminStepUpException $exception) {
            if ($started) {
                $this->operation->rollback();
            }
            throw $exception;
        } catch (Throwable $exception) {
            if ($started) {
                $this->operation->rollback();
            }
            error_log('Admin access renewal failed correlation=' . $correlationId . ' exception=' . get_class($exception));
            throw new AdminStepUpException('STEP_UP_RENEWAL_FAILED', $correlationId);
        }
    }
}
