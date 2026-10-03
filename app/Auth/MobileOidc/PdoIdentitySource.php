<?php
declare(strict_types=1);
namespace OneId\App\Auth\MobileOidc;

use PDO;
use RuntimeException;
use OneId\App\Auth\UserMfa\PdoUserMfaPolicyReader;

final class PdoIdentitySource implements PasswordIdentitySource
{
    private readonly PdoUserMfaPolicyReader $policies;
    private readonly \Closure $availability;

    /** $availability must check maintenance + feature/runtime readiness; no permissive default. */
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $runtimeMfaMode,
        private readonly bool $mfaActivationAuthorized,
        callable $availability,
        private readonly bool $requireLifecycle = false,
        string $environment = 'staging'
    ) {
        $this->policies = new PdoUserMfaPolicyReader($pdo, $environment);
        $this->availability = \Closure::fromCallable($availability);
    }

    public function available(): bool
    {
        if (($this->availability)() !== true) return false;
        if (!$this->requireLifecycle) return true; // Phase B fixtures only; hosted factory always requires lifecycle.
        return LifecycleSchema::ready($this->pdo);
    }

    public function candidates(string $identifier): array
    {
        $this->locked();
        $q = $this->pdo->prepare('SELECT u_id FROM user_tbl WHERE data3=:staff OR data4=:matric OR u_id=:uid ORDER BY u_id LIMIT 10 FOR UPDATE');
        $q->execute(['staff' => $identifier, 'matric' => $identifier, 'uid' => $identifier]);
        $ids = $q->fetchAll(PDO::FETCH_COLUMN);
        if (count($ids) >= 10) return []; // Ambiguous, never truncate to a chosen identity.
        return array_values(array_filter(array_map(fn($id) => $this->account((string) $id), $ids)));
    }

    public function account(string $userId): ?array
    {
        $this->locked();
        $q = $this->pdo->prepare('SELECT u_id,u_category,u_type,avail_status,u_password,password_change_required,data1,data2,data3,data4,data5,data6,data7 FROM user_tbl WHERE u_id=:u FOR UPDATE');
        $q->execute(['u' => $userId]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        if ($this->requireLifecycle) {
            $epoch = $this->pdo->prepare('SELECT generation,security_version,deleted FROM mobile_oidc_identity_epoch WHERE u_id=:u FOR UPDATE');
            $epoch->execute(['u' => $userId]);
            $state = $epoch->fetch(PDO::FETCH_ASSOC);
            if (!$state || (int) $state['deleted'] !== 0) throw new RuntimeException('MOBILE_IDENTITY_EPOCH_UNAVAILABLE');
            $row['mobile_generation'] = $state['generation'];
            $row['mobile_version'] = (int) $state['security_version'];
            $row['mobile_policy_epoch'] = (int) $this->pdo->query('SELECT policy_epoch FROM mobile_oidc_control WHERE singleton_id=1 FOR SHARE')->fetchColumn();
        }
        $q = $this->pdo->prepare("SELECT s.source_family FROM user_external_identity i JOIN external_source s ON s.source_code=i.source_code WHERE i.u_id=:u AND i.source_active=1 FOR UPDATE");
        $q->execute(['u' => $userId]);
        $row['families'] = array_values(array_unique($q->fetchAll(PDO::FETCH_COLUMN)));
        sort($row['families']);
        $q = $this->pdo->prepare("SELECT factor_id,encrypted_secret,secret_nonce,key_version,last_used_time_step FROM user_mfa_factors WHERE u_id=:u AND factor_type='TOTP' AND factor_status='ACTIVE' FOR UPDATE");
        $q->execute(['u' => $userId]);
        $factors = $q->fetchAll(PDO::FETCH_ASSOC);
        if (count($factors) > 1) throw new RuntimeException('MOBILE_FACTOR_AMBIGUOUS');
        $row['totp'] = $factors[0] ?? null;
        return $row;
    }

    public function policy(array $account): array
    {
        $this->locked();
        if ($this->runtimeMfaMode !== 'OFF' && !$this->mfaActivationAuthorized) {
            throw new RuntimeException('MOBILE_MFA_RUNTIME_UNAVAILABLE');
        }
        $this->pdo->query('SELECT singleton_key FROM user_login_mfa_policy WHERE singleton_key=1 FOR UPDATE')->fetchColumn();
        if ($this->runtimeMfaMode !== 'OFF') $this->policies->assertRuntimeParity($this->runtimeMfaMode);
        $policy = $this->policies->policy();
        $id = (string) $account['u_id'];
        // Mobile is a user login: an admin role must not override user MFA policy.
        $category = strtoupper($account['account_type']);
        $q = $this->pdo->prepare('SELECT enforcement_enabled FROM user_login_mfa_category_policy WHERE category_code=:c FOR UPDATE');
        $q->execute(['c' => $category]);
        $setting = $q->fetchColumn();
        $enforced = $setting === false || (int) $setting === 1;
        $exempt = (int) $account['u_type'] === 0 && $this->policies->temporarilyExempt($id);
        $mode = $this->runtimeMfaMode === 'OFF' ? 'OFF' : $policy->mode;
        $required = !$exempt && $enforced && ($mode === 'ENFORCED'
            || ($mode === 'PILOT_ENFORCED' && $this->policies->pilotEligible($id)));
        return ['scope' => $policy->scope, 'required' => $required, 'email' => $policy->emailEnabled,
            'totp' => $policy->totpEnabled, 'ttl' => $policy->pendingTtlSeconds,
            'otp_ttl' => $policy->otpTtlSeconds, 'attempts' => $policy->maxAttempts,
            'cooldown' => $policy->resendCooldownSeconds, 'hourly' => $policy->hourlySendLimit,
            'mode' => $mode, 'exempt' => $exempt];
    }

    public function consumeTotp(array $factor, int $step): bool
    {
        $this->locked();
        $q = $this->pdo->prepare("UPDATE user_mfa_factors SET last_used_time_step=:step,last_used_at=NOW(6) WHERE factor_id=:id AND factor_status='ACTIVE' AND COALESCE(last_used_time_step,-1)=:previous AND COALESCE(last_used_time_step,-1)<:compare");
        $q->execute(['step' => $step, 'id' => $factor['factor_id'],
            'previous' => $factor['last_used_time_step'] ?? -1, 'compare' => $step]);
        return $q->rowCount() === 1;
    }

    private function locked(): void
    {
        if (!$this->pdo->inTransaction()) throw new RuntimeException('MOBILE_TRANSACTION_REQUIRED');
    }

    public function changePassword(array $account, string $newPassword): ?string
    {
        $this->locked();
        if (!$this->requireLifecycle) throw new RuntimeException('MOBILE_PASSWORD_WRITER_REQUIRES_LIFECYCLE');
        $id = (string) $account['u_id'];
        if (\oneid_password_verify($newPassword, $account['u_password'])) return 'PASSWORD_REUSED';
        $limit = \oneid_password_history_limit();
        $q = $this->pdo->prepare('SELECT password_hash FROM user_password_history WHERE user_id=:u ORDER BY id DESC LIMIT ' . $limit . ' FOR UPDATE');
        $q->execute(['u' => $id]);
        foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $hash) if (\oneid_password_verify($newPassword, $hash)) return 'PASSWORD_REUSED';
        $q = $this->pdo->prepare('INSERT INTO user_password_history(user_id,password_hash,changed_at) VALUES(:u,:hash,NOW())');
        $q->execute(['u' => $id, 'hash' => $account['u_password']]);
        $q = $this->pdo->prepare('UPDATE user_tbl SET u_password=:hash,password_change_required=0 WHERE u_id=:u AND avail_status=1');
        $q->execute(['hash' => \oneid_password_hash($newPassword), 'u' => $id]);
        if ($q->rowCount() !== 1) throw new RuntimeException('MOBILE_PASSWORD_NOT_CHANGED');
        $q = $this->pdo->prepare('DELETE FROM user_password_history WHERE user_id=:u AND id NOT IN (SELECT id FROM (SELECT id FROM user_password_history WHERE user_id=:inner_u ORDER BY id DESC LIMIT ' . $limit . ') retained)');
        $q->execute(['u' => $id, 'inner_u' => $id]);
        // Same security semantics as a password reset: revoke old web sessions, never mint a new web token.
        $q = $this->pdo->prepare("UPDATE token_tbl SET status=0,ended_at=COALESCE(ended_at,NOW()),end_reason=COALESCE(end_reason,'PASSWORD_RESET') WHERE user_id=:u");
        $q->execute(['u' => $id]);
        $q = $this->pdo->prepare('UPDATE otp_codes SET otp_consumed_at=NOW() WHERE u_id=:u AND otp_consumed_at IS NULL');
        $q->execute(['u' => $id]);
        return null;
    }
}
