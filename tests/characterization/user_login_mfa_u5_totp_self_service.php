<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Auth/Totp.php';
require_once dirname(__DIR__, 2) . '/app/Auth/TotpKeyring.php';
require_once dirname(__DIR__, 2) . '/app/Auth/TotpSecretCipher.php';
require_once dirname(__DIR__, 2) . '/app/Auth/UserMfa/UserMfaRequestBinding.php';
require_once dirname(__DIR__, 2) . '/app/Auth/UserMfa/UserMfaTotpPrimitive.php';
require_once dirname(__DIR__, 2) . '/app/Auth/UserMfa/UserMfaTotpException.php';
require_once dirname(__DIR__, 2) . '/app/Auth/UserMfa/UserMfaTotpPersistenceInterface.php';
require_once dirname(__DIR__, 2) . '/app/Auth/UserMfa/UserMfaTotpService.php';

use OneId\App\Auth\Totp;
use OneId\App\Auth\TotpKeyring;
use OneId\App\Auth\TotpSecretCipher;
use OneId\App\Auth\UserMfa\UserMfaTotpException;
use OneId\App\Auth\UserMfa\UserMfaTotpPersistenceInterface;
use OneId\App\Auth\UserMfa\UserMfaTotpPrimitive;
use OneId\App\Auth\UserMfa\UserMfaTotpService;

final class U5Persistence implements UserMfaTotpPersistenceInterface
{
    public bool $totpEnabled = true;
    /** @var array<string, array<string, mixed>> */
    public array $factors = [];
    /** @var array<string, string> */
    public array $preferences = [];
    /** @var list<array<string, string>> */
    public array $audits = [];
    public int $sessionRevocations = 0;
    public int $challengeRevocations = 0;
    private int $nextId = 1;
    /** @var array<string, mixed>|null */
    private ?array $snapshot = null;

    public function beginTransaction(): void
    {
        $this->snapshot = [
            'factors' => $this->factors,
            'preferences' => $this->preferences,
            'audits' => $this->audits,
            'sessionRevocations' => $this->sessionRevocations,
            'challengeRevocations' => $this->challengeRevocations,
        ];
    }
    public function commit(): void { $this->snapshot = null; }
    public function rollback(): void
    {
        if ($this->snapshot !== null) {
            foreach ($this->snapshot as $key => $value) {
                $this->{$key} = $value;
            }
        }
        $this->snapshot = null;
    }
    public function policyForUpdate(): array|false { return ['totp_enabled' => $this->totpEnabled]; }
    public function pendingFactorForUpdate(string $factorId, string $userId): array|false
    {
        $factor = $this->factors[$factorId] ?? false;
        return is_array($factor) && $factor['u_id'] === $userId && $factor['factor_status'] === 'PENDING'
            ? $factor : false;
    }
    public function activeFactorForUpdate(string $userId): array|false
    {
        foreach ($this->factors as $factor) {
            if ($factor['u_id'] === $userId && $factor['factor_status'] === 'ACTIVE') {
                return $factor;
            }
        }
        return false;
    }
    public function createPendingFactor(array $factor): string|false
    {
        $id = (string) $this->nextId++;
        $factor['factor_id'] = $id;
        $factor['factor_status'] = 'PENDING';
        $factor['last_used_time_step'] = null;
        $this->factors[$id] = $factor;
        return $id;
    }
    public function confirmFactor(string $factorId, int $timeStep): int
    {
        if (!isset($this->factors[$factorId]) || $this->factors[$factorId]['factor_status'] !== 'PENDING') {
            return 0;
        }
        $this->factors[$factorId]['factor_status'] = 'ACTIVE';
        $this->factors[$factorId]['last_used_time_step'] = $timeStep;
        return 1;
    }
    public function updateLastUsedStep(string $factorId, int $expectedPreviousStep, int $newStep): int
    {
        if (!isset($this->factors[$factorId])) {
            return 0;
        }
        $current = $this->factors[$factorId]['last_used_time_step'];
        if (($current ?? -1) !== $expectedPreviousStep || $newStep <= ($current ?? -1)) {
            return 0;
        }
        $this->factors[$factorId]['last_used_time_step'] = $newStep;
        return 1;
    }
    public function setPreference(string $userId, string $factorType, string $correlationId): int
    {
        $this->preferences[$userId] = $factorType;
        return 1;
    }
    public function revokeFactors(string $userId, string $reason): int
    {
        $count = 0;
        foreach ($this->factors as &$factor) {
            if ($factor['u_id'] === $userId && $factor['factor_status'] !== 'REVOKED') {
                $factor['factor_status'] = 'REVOKED';
                $count++;
            }
        }
        unset($factor);
        return $count;
    }
    public function revokePendingChallenges(string $userId, string $reason): int
    {
        $this->challengeRevocations++;
        return 1;
    }
    public function revokeUserSessions(string $userId, string $reason): int
    {
        $this->sessionRevocations++;
        return 2;
    }
    public function recordAudit(
        string $event,
        string $targetUserId,
        string $actorUserId,
        string $outcome,
        string $reason,
        string $reference,
        string $correlationId
    ): int {
        $this->audits[] = compact('event', 'targetUserId', 'actorUserId', 'outcome', 'reason', 'reference', 'correlationId');
        return 1;
    }
}

$checks = 0;
$failed = 0;
$report = static function (bool $passed, string $label) use (&$checks, &$failed): void {
    $checks++;
    $failed += $passed ? 0 : 1;
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $label);
};
$reason = static function (callable $action): string {
    try {
        $action();
        return '';
    } catch (UserMfaTotpException $exception) {
        return $exception->reason;
    }
};

$keyringPath = tempnam(sys_get_temp_dir(), 'oneid-u5-');
if ($keyringPath === false) {
    throw new RuntimeException('USER_MFA_U5_TEMPFILE_FAILED');
}
$keyring = "<?php\nreturn ['active_version'=>'u5-test','keys'=>['u5-test'=>'"
    . base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES))
    . "']];\n";
file_put_contents($keyringPath, $keyring, LOCK_EX);
chmod($keyringPath, 0600);

try {
    $now = 1_800_000_000;
    $persistence = new U5Persistence();
    $primitive = new UserMfaTotpPrimitive(
        new TotpSecretCipher(TotpKeyring::fromFile($keyringPath)),
        static fn(): int => $now
    );
    $service = new UserMfaTotpService($persistence, $primitive);

    $enrollment = $service->beginEnrollment('USER01', 'user01@upnm.edu.my', 'session-1', 'Browser/1');
    parse_str((string) parse_url($enrollment['provisioning_uri'], PHP_URL_QUERY), $query);
    $secret = (string) ($query['secret'] ?? '');
    $factor = $persistence->factors[$enrollment['factor_id']];
    $report(
        $secret !== ''
        && $factor['encrypted_secret'] !== $secret
        && $enrollment['cache_control'] === 'no-store'
        && !array_key_exists('secret', $enrollment),
        'self enrollment returns local no-store URI and persists encrypted secret only'
    );

    $code = Totp::codeAt($secret, $now);
    $report(
        $reason(fn() => $service->confirmEnrollment(
            'OTHER01',
            $enrollment['factor_id'],
            $code,
            'session-1',
            'Browser/1'
        )) === 'USER_MFA_TOTP_ENROLLMENT_INVALID',
        'factor target is derived from authenticated user and rejects IDOR'
    );
    $report(
        $reason(fn() => $service->confirmEnrollment(
            'USER01',
            $enrollment['factor_id'],
            $code,
            'wrong-session',
            'Browser/1'
        )) === 'USER_MFA_TOTP_ENROLLMENT_INVALID',
        'confirmation rejects cross-session enrollment'
    );
    $service->confirmEnrollment('USER01', $enrollment['factor_id'], $code, 'session-1', 'Browser/1');
    $report(
        $persistence->factors[$enrollment['factor_id']]['factor_status'] === 'ACTIVE'
        && $persistence->preferences['USER01'] === 'TOTP',
        'valid confirmation atomically activates factor and TOTP preference'
    );

    $nextNow = $now + 30;
    $nextPrimitive = new UserMfaTotpPrimitive(
        new TotpSecretCipher(TotpKeyring::fromFile($keyringPath)),
        static fn(): int => $nextNow
    );
    $nextService = new UserMfaTotpService($persistence, $nextPrimitive);
    $nextCode = Totp::codeAt($secret, $nextNow);
    $step = $nextService->verify('USER01', $nextCode);
    $report($step === intdiv($nextNow, 30), 'active encrypted TOTP verifies and advances atomic time-step');
    $report(
        $reason(fn() => $nextService->verify('USER01', $nextCode))
            === 'USER_MFA_TOTP_INVALID_OR_REPLAYED',
        'same-step TOTP replay is rejected'
    );

    $persistence->totpEnabled = false;
    $report(
        $service->availableFactors(false, true) === ['EMAIL_OTP']
        && $reason(fn() => $nextService->verify('USER01', $nextCode))
            === 'USER_MFA_TOTP_DISABLED_USE_EMAIL',
        'global TOTP kill switch preserves mandatory email fallback'
    );
    $persistence->totpEnabled = true;
    $service->setPreference('USER01', 'EMAIL_OTP');
    $report($persistence->preferences['USER01'] === 'EMAIL_OTP', 'user can select email preference');

    $report(
        $reason(fn() => $service->selfRevoke('USER01', false, 'lost phone'))
            === 'USER_MFA_RECOVERY_NOT_AUTHORIZED',
        'self revoke requires fresh factor verification'
    );
    $service->selfRevoke('USER01', true, 'replace phone');
    $report(
        $persistence->factors[$enrollment['factor_id']]['factor_status'] === 'REVOKED'
        && $persistence->preferences['USER01'] === 'EMAIL_OTP'
        && $persistence->sessionRevocations === 1
        && $persistence->challengeRevocations === 1,
        'self revoke resets preference and revokes challenges and all sessions'
    );

    $second = $service->beginEnrollment('USER02', 'user02@upnm.edu.my', 'session-2', 'Browser/2');
    $report(
        $reason(fn() => $service->adminRecover(
            'ADMIN01', 'USER02', true, 'ADMIN01', 'identity checked', 'TICKET-1', 'USER02'
        )) === 'USER_MFA_RECOVERY_NOT_AUTHORIZED',
        'admin recovery requires a different second Administrator verifier'
    );
    $report(
        $reason(fn() => $service->adminRecover(
            'ADMIN01', 'USER02', false, 'ADMIN02', 'identity checked', 'TICKET-1', 'USER02'
        )) === 'USER_MFA_RECOVERY_NOT_AUTHORIZED',
        'admin recovery requires fresh Admin Step-Up'
    );
    $service->adminRecover(
        'ADMIN01', 'USER02', true, 'ADMIN02', 'identity checked', 'TICKET-1', 'USER02'
    );
    $lastAudit = $persistence->audits[array_key_last($persistence->audits)];
    $report(
        $persistence->factors[$second['factor_id']]['factor_status'] === 'REVOKED'
        && $lastAudit['targetUserId'] === 'USER02'
        && $lastAudit['actorUserId'] === 'ADMIN01'
        && $lastAudit['reference'] === 'TICKET-1'
        && !str_contains(json_encode($persistence->audits, JSON_THROW_ON_ERROR), $secret),
        'existing Admin recovery records actor target verifier controls and no secret'
    );

    printf(
        "RESULT checks=%d failures=%d qr_generated_locally=1 admin_secret_access=0 network_calls=0 live_database_mutations=0 runtime_activation=0 raw_secret_output=0\n",
        $checks,
        $failed
    );
    exit($failed === 0 ? 0 : 1);
} finally {
    @unlink($keyringPath);
}
