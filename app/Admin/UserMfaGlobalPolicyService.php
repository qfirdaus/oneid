<?php

declare(strict_types=1);

namespace OneId\App\Admin;

use OneId\App\Audit\AuditIdentityResolver;
use PDO;
use Throwable;

final class UserMfaGlobalPolicyService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $runtimeMode,
        private readonly bool $activationAuthorized,
        private readonly bool $runtimeTotpEnabled
    ) {
    }

    public function read(): array
    {
        $policy = $this->policy(false);
        $impact = $this->pendingImpact();
        return [
            'status' => 1,
            'code' => 'USER_MFA_GLOBAL_POLICY_LOADED',
            'data' => [
                'enabled' => $policy['policy_mode'] !== 'OFF',
                'effective_mode' => (string) $policy['policy_mode'],
                'authorized_mode' => $this->authorizedMode(),
                'activation_available' => $this->activationAvailable(),
                'email_enabled' => (int) $policy['email_enabled'],
                'totp_enabled' => (int) $policy['totp_enabled'],
                'configuration_version' => (int) $policy['configuration_version'],
                'active_factors' => (int) $this->pdo->query(
                    "SELECT COUNT(*) FROM user_mfa_factors
                      WHERE factor_type='TOTP' AND factor_status='ACTIVE'"
                )->fetchColumn(),
                'pending_transactions' => $impact['pending_transactions'],
                'pending_challenges' => $impact['pending_challenges'],
            ],
        ];
    }

    public function update(
        mixed $enabledValue,
        mixed $versionValue,
        string $reason,
        string $reference,
        string $typedConfirmation,
        string $adminId,
        string $ipAddress
    ): array {
        $enabled = $this->flag($enabledValue);
        $version = filter_var($versionValue, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $reason = trim($reason);
        $reference = trim($reference);
        $adminId = trim($adminId);
        $expected = $enabled ? 'ENABLE USER MFA' : 'DISABLE USER MFA';
        if ($version === false
            || strlen($reason) < 10 || strlen($reason) > 500
            || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $reason) === 1
            || preg_match('/\A[A-Za-z0-9._-]{8,100}\z/', $reference) !== 1
            || !hash_equals($expected, $typedConfirmation)
            || preg_match('/\A[A-Za-z0-9._@-]{1,20}\z/', $adminId) !== 1
            || filter_var($ipAddress, FILTER_VALIDATE_IP) === false
        ) {
            throw new SsoConfigurationException('USER_MFA_GLOBAL_POLICY_INPUT_INVALID', bin2hex(random_bytes(8)));
        }
        if ($enabled && !$this->activationAvailable()) {
            throw new SsoConfigurationException('USER_MFA_GLOBAL_ACTIVATION_NOT_AUTHORIZED', bin2hex(random_bytes(8)));
        }
        $publicAdminId = (new AuditIdentityResolver($this->pdo))->resolve($adminId);

        $correlation = bin2hex(random_bytes(16));
        $started = false;
        try {
            $this->pdo->beginTransaction();
            $started = true;
            $before = $this->policy(true);
            if ((int) $before['configuration_version'] !== (int) $version) {
                throw new SsoConfigurationException('USER_MFA_GLOBAL_POLICY_STALE', $correlation);
            }
            $target = $enabled ? $this->authorizedMode() : 'OFF';
            if (!in_array((string) $before['policy_mode'], ['OFF', $this->authorizedMode()], true)) {
                throw new SsoConfigurationException('USER_MFA_GLOBAL_POLICY_RUNTIME_MISMATCH', $correlation);
            }
            if ((string) $before['policy_mode'] === $target) {
                $this->pdo->commit();
                return $this->read() + ['changed' => false, 'correlation_id' => $correlation];
            }
            $impact = $this->pendingImpact();
            $nextVersion = (int) $version + 1;
            $totp = $enabled && $this->runtimeTotpEnabled ? 1 : 0;
            $update = $this->pdo->prepare(
                'UPDATE user_login_mfa_policy
                    SET policy_mode=:mode,email_enabled=1,totp_enabled=:totp,
                        configuration_version=:next_version,
                        readiness_reference=:reference,updated_by=:admin
                  WHERE singleton_key=1 AND configuration_version=:version'
            );
            $update->execute([
                ':mode' => $target,
                ':totp' => $totp,
                ':next_version' => $nextVersion,
                ':reference' => $reference,
                ':admin' => $adminId,
                ':version' => (int) $version,
            ]);
            if ($update->rowCount() !== 1) {
                throw new SsoConfigurationException('USER_MFA_GLOBAL_POLICY_STALE', $correlation);
            }
            $revokedTransactions = 0;
            $revokedChallenges = 0;
            if (!$enabled) {
                $revokedChallenges = $this->pdo->exec(
                    'UPDATE user_login_mfa_challenges c
                       JOIN user_login_mfa_transactions t
                         ON t.transaction_id=c.transaction_id
                        SET c.revoked_at=COALESCE(c.revoked_at,NOW(6))
                      WHERE t.transaction_status IN (\'PENDING\',\'VERIFIED\')
                        AND c.consumed_at IS NULL AND c.revoked_at IS NULL'
                );
                $revokedTransactions = $this->pdo->exec(
                    "UPDATE user_login_mfa_transactions
                        SET transaction_status='REVOKED',revoked_at=NOW(6)
                      WHERE transaction_status IN ('PENDING','VERIFIED')"
                );
            }
            $resulting = $before;
            $resulting['policy_mode'] = $target;
            $resulting['email_enabled'] = 1;
            $resulting['totp_enabled'] = $totp;
            $resulting['configuration_version'] = $nextVersion;
            $history = $this->pdo->prepare(
                'INSERT INTO user_login_mfa_policy_history(
                    configuration_version,previous_policy,resulting_policy,
                    changed_by,change_reason,change_reference,correlation_id
                 ) VALUES(:version,:previous,:resulting,:admin,:reason,:reference,:correlation)'
            );
            $history->execute([
                ':version' => $nextVersion,
                ':previous' => json_encode($before, JSON_THROW_ON_ERROR),
                ':resulting' => json_encode($resulting, JSON_THROW_ON_ERROR),
                ':admin' => $adminId,
                ':reason' => $reason,
                ':reference' => $reference,
                ':correlation' => $correlation,
            ]);
            $detail = sprintf(
                'admin=%s action=user_mfa_global_policy from=%s to=%s pending_transactions=%d pending_challenges=%d reference=%s correlation=%s',
                $publicAdminId,
                (string) $before['policy_mode'],
                $target,
                $revokedTransactions,
                $revokedChallenges,
                $reference,
                $correlation
            );
            $audit = $this->pdo->prepare(
                'INSERT INTO syslog(log_type,log_detail,ip_addr,datetime)
                 VALUES(64,:detail,:ip,NOW())'
            );
            $audit->execute([':detail' => $detail, ':ip' => $ipAddress]);
            if ($history->rowCount() !== 1 || $audit->rowCount() !== 1) {
                throw new SsoConfigurationException('USER_MFA_GLOBAL_POLICY_AUDIT_FAILED', $correlation);
            }
            $this->pdo->commit();
            $started = false;
            return [
                'status' => 1,
                'code' => $enabled ? 'USER_MFA_GLOBAL_ENABLED' : 'USER_MFA_GLOBAL_DISABLED',
                'changed' => true,
                'data' => [
                    'enabled' => $enabled,
                    'effective_mode' => $target,
                    'authorized_mode' => $this->authorizedMode(),
                    'configuration_version' => $nextVersion,
                    'active_factors_preserved' => true,
                    'revoked_transactions' => $revokedTransactions,
                    'revoked_challenges' => $revokedChallenges,
                    'preview_pending_transactions' => $impact['pending_transactions'],
                    'preview_pending_challenges' => $impact['pending_challenges'],
                ],
                'correlation_id' => $correlation,
            ];
        } catch (SsoConfigurationException $exception) {
            if ($started && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        } catch (Throwable $exception) {
            if ($started && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('User MFA global policy failed correlation=' . $correlation . ' exception=' . get_class($exception));
            throw new SsoConfigurationException('USER_MFA_GLOBAL_POLICY_FAILED', $correlation);
        }
    }

    private function policy(bool $lock): array
    {
        $row = $this->pdo->query(
            'SELECT * FROM user_login_mfa_policy WHERE singleton_key=1' . ($lock ? ' FOR UPDATE' : '')
        )->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new SsoConfigurationException('USER_MFA_GLOBAL_POLICY_UNAVAILABLE', bin2hex(random_bytes(8)));
        }
        return $row;
    }

    private function pendingImpact(): array
    {
        return [
            'pending_transactions' => (int) $this->pdo->query(
                "SELECT COUNT(*) FROM user_login_mfa_transactions
                  WHERE transaction_status IN ('PENDING','VERIFIED')"
            )->fetchColumn(),
            'pending_challenges' => (int) $this->pdo->query(
                "SELECT COUNT(*) FROM user_login_mfa_challenges c
                  JOIN user_login_mfa_transactions t ON t.transaction_id=c.transaction_id
                 WHERE t.transaction_status IN ('PENDING','VERIFIED')
                   AND c.consumed_at IS NULL AND c.revoked_at IS NULL"
            )->fetchColumn(),
        ];
    }

    private function activationAvailable(): bool
    {
        return $this->activationAuthorized && $this->authorizedMode() !== 'OFF';
    }

    private function authorizedMode(): string
    {
        return in_array($this->runtimeMode, ['ENROLLMENT','PILOT_ENFORCED','ENFORCED'], true)
            ? $this->runtimeMode
            : 'OFF';
    }

    private function flag(mixed $value): bool
    {
        if (!is_scalar($value) || !in_array(trim((string) $value), ['0','1'], true)) {
            throw new SsoConfigurationException('USER_MFA_GLOBAL_POLICY_FLAG_INVALID', bin2hex(random_bytes(8)));
        }
        return trim((string) $value) === '1';
    }
}
