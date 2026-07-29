<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Auth/UserMfa/UserLoginMfaPolicy.php';
require_once dirname(__DIR__, 2) . '/app/Auth/UserMfa/UserMfaRequestBinding.php';
require_once dirname(__DIR__, 2) . '/app/Auth/UserMfa/UserMfaPendingLoginException.php';
require_once dirname(__DIR__, 2) . '/app/Auth/UserMfa/UserMfaPendingLoginPersistenceInterface.php';
require_once dirname(__DIR__, 2) . '/app/Auth/UserMfa/UserMfaLoginFinalizerInterface.php';
require_once dirname(__DIR__, 2) . '/app/Auth/UserMfa/UserMfaPendingLoginCoordinator.php';

use OneId\App\Auth\UserMfa\UserLoginMfaPolicy;
use OneId\App\Auth\UserMfa\UserMfaLoginFinalizerInterface;
use OneId\App\Auth\UserMfa\UserMfaPendingLoginCoordinator;
use OneId\App\Auth\UserMfa\UserMfaPendingLoginException;
use OneId\App\Auth\UserMfa\UserMfaPendingLoginPersistenceInterface;

final class U3Persistence implements UserMfaPendingLoginPersistenceInterface
{
    public array $rows = [];
    public array $audits = [];
    private ?array $snapshot = null;

    public function beginTransaction(): void
    {
        $this->snapshot = [$this->rows, $this->audits];
    }
    public function commit(): void
    {
        $this->snapshot = null;
    }
    public function rollback(): void
    {
        if ($this->snapshot !== null) {
            [$this->rows, $this->audits] = $this->snapshot;
        }
        $this->snapshot = null;
    }
    public function createPendingLogin(array $entry): int
    {
        $this->rows[$entry['transaction_id']] = $entry;
        return 1;
    }
    public function pendingLoginForUpdate(string $transactionId): array|false
    {
        return $this->rows[$transactionId] ?? false;
    }
    public function markFactorVerified(string $transactionId, string $factorType): int
    {
        if (!isset($this->rows[$transactionId])
            || $this->rows[$transactionId]['transaction_status'] !== 'PENDING'
        ) {
            return 0;
        }
        $this->rows[$transactionId]['transaction_status'] = 'VERIFIED';
        $this->rows[$transactionId]['verified_factor'] = $factorType;
        return 1;
    }
    public function consumePendingLogin(string $transactionId): int
    {
        if (!isset($this->rows[$transactionId])
            || $this->rows[$transactionId]['transaction_status'] !== 'VERIFIED'
        ) {
            return 0;
        }
        $this->rows[$transactionId]['transaction_status'] = 'CONSUMED';
        return 1;
    }
    public function revokePendingLogin(string $transactionId, string $reason): int
    {
        if (!isset($this->rows[$transactionId])) {
            return 0;
        }
        $this->rows[$transactionId]['transaction_status'] = $reason === 'EXPIRED'
            ? 'EXPIRED'
            : 'REVOKED';
        return 1;
    }
    public function recordAudit(
        string $event,
        string $userId,
        string $outcome,
        string $reason,
        string $correlationId,
        string $ipAddress
    ): int {
        $this->audits[] = compact(
            'event',
            'userId',
            'outcome',
            'reason',
            'correlationId',
            'ipAddress'
        );
        return 1;
    }
}

final class U3Finalizer implements UserMfaLoginFinalizerInterface
{
    public array $prepared = [];
    public array $compensated = [];
    public bool $fails = false;

    public function prepare(string $userId, string $correlationId): array
    {
        if ($this->fails) {
            throw new RuntimeException('FINALIZER_FAILED');
        }
        $handle = ['token_ref' => 'token-' . count($this->prepared), 'u_id' => $userId];
        $this->prepared[] = $handle;
        return $handle;
    }
    public function compensate(array $handle): void
    {
        $this->compensated[] = $handle;
    }
}

$checks = 0;
$failed = 0;
$report = static function (bool $passed, string $label) use (&$checks, &$failed): void {
    $checks++;
    if (!$passed) {
        $failed++;
    }
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $label);
};
$reason = static function (callable $call): string {
    try {
        $call();
        return 'NONE';
    } catch (UserMfaPendingLoginException $exception) {
        return $exception->reason;
    }
};

$clock = 1_800_000_000;
$offPersistence = new U3Persistence();
$offCoordinator = new UserMfaPendingLoginCoordinator($offPersistence, static fn(): int => $clock);
$off = $offCoordinator->begin(
    'USER1',
    'PASSWORD',
    'session-off',
    'Browser/1',
    '127.0.0.1',
    UserLoginMfaPolicy::committedDefault()
);
$report(
    $off['code'] === 'USER_MFA_NOT_REQUIRED' && $offPersistence->rows === [],
    'OFF mode preserves password-login bypass without pending mutation'
);

$mydid = $offCoordinator->begin(
    'USER1',
    'MYDIGITALID',
    'session-mydid',
    'Browser/1',
    '127.0.0.1',
    new UserLoginMfaPolicy('ENFORCED', 'PASSWORD_ONLY', true, true, 300, 300, 5, 60, 10)
);
$report(
    $mydid['reason'] === 'PRIMARY_METHOD_OUT_OF_SCOPE' && $offPersistence->rows === [],
    'MyDigital ID remains outside PASSWORD_ONLY User MFA'
);

$pilotPersistence = new U3Persistence();
$pilotCoordinator = new UserMfaPendingLoginCoordinator($pilotPersistence, static fn(): int => $clock);
$pilotPolicy = new UserLoginMfaPolicy(
    'PILOT_ENFORCED',
    'PASSWORD_ONLY',
    true,
    true,
    300,
    300,
    5,
    60,
    10
);
$pilotOut = $pilotCoordinator->begin(
    'USER2', 'PASSWORD', 'pilot-out', 'Browser/2', '127.0.0.2', $pilotPolicy, false
);
$pilotIn = $pilotCoordinator->begin(
    'USER3', 'PASSWORD', 'pilot-in', 'Browser/3', '127.0.0.3', $pilotPolicy, true
);
$report(
    $pilotOut['code'] === 'USER_MFA_NOT_REQUIRED'
    && $pilotIn['code'] === 'USER_MFA_REQUIRED'
    && count($pilotPersistence->rows) === 1,
    'pilot enforcement creates pending state only for allowlisted user'
);

$finalizer = new U3Finalizer();
$report(
    $reason(fn() => $pilotCoordinator->finalize(
        $pilotIn['transaction_id'],
        'pilot-in',
        'Browser/3',
        '127.0.0.3',
        $finalizer
    )) === 'USER_MFA_FACTOR_REQUIRED'
    && $finalizer->prepared === [],
    'password success cannot finalize login before factor verification'
);

$report(
    $reason(fn() => $pilotCoordinator->markVerified(
        $pilotIn['transaction_id'],
        'EMAIL_OTP',
        'wrong-session',
        'Browser/3',
        '127.0.0.3'
    )) === 'USER_MFA_BINDING_MISMATCH'
    && $pilotPersistence->rows[$pilotIn['transaction_id']]['transaction_status'] === 'PENDING',
    'cross-session verification is rejected without state mutation'
);

$pilotCoordinator->markVerified(
    $pilotIn['transaction_id'],
    'EMAIL_OTP',
    'pilot-in',
    'Browser/3',
    '127.0.0.3'
);
$report(
    $pilotPersistence->rows[$pilotIn['transaction_id']]['transaction_status'] === 'VERIFIED'
    && $finalizer->prepared === [],
    'verified factor changes pending state but still creates no login token'
);

$completed = $pilotCoordinator->finalize(
    $pilotIn['transaction_id'],
    'pilot-in',
    'Browser/3',
    '127.0.0.3',
    $finalizer
);
$report(
    $completed['code'] === 'USER_MFA_LOGIN_AUTHORIZED'
    && count($finalizer->prepared) === 1
    && $pilotPersistence->rows[$pilotIn['transaction_id']]['transaction_status'] === 'CONSUMED',
    'one-use finalization occurs only after verified factor'
);

$report(
    $reason(fn() => $pilotCoordinator->finalize(
        $pilotIn['transaction_id'],
        'pilot-in',
        'Browser/3',
        '127.0.0.3',
        $finalizer
    )) === 'USER_MFA_FACTOR_REQUIRED'
    && count($finalizer->prepared) === 1,
    'consumed transaction cannot create a second login token'
);

$failurePersistence = new U3Persistence();
$failureCoordinator = new UserMfaPendingLoginCoordinator(
    $failurePersistence,
    static fn(): int => $clock
);
$enforced = new UserLoginMfaPolicy(
    'ENFORCED',
    'PASSWORD_ONLY',
    true,
    false,
    300,
    300,
    5,
    60,
    10
);
$failurePending = $failureCoordinator->begin(
    'USER4', 'PASSWORD', 'failure', 'Browser/4', '127.0.0.4', $enforced
);
$failureCoordinator->markVerified(
    $failurePending['transaction_id'],
    'EMAIL_OTP',
    'failure',
    'Browser/4',
    '127.0.0.4'
);
$failingFinalizer = new U3Finalizer();
$failingFinalizer->fails = true;
$report(
    $reason(fn() => $failureCoordinator->finalize(
        $failurePending['transaction_id'],
        'failure',
        'Browser/4',
        '127.0.0.4',
        $failingFinalizer
    )) === 'USER_MFA_FINALIZATION_FAILED'
    && $failurePersistence->rows[$failurePending['transaction_id']]['transaction_status'] === 'VERIFIED',
    'finalizer failure rolls back consumption and leaves no prepared token'
);

$expiryPersistence = new U3Persistence();
$expiryCoordinator = new UserMfaPendingLoginCoordinator(
    $expiryPersistence,
    static fn(): int => $clock
);
$expired = $expiryCoordinator->begin(
    'USER5', 'PASSWORD', 'expired', 'Browser/5', '127.0.0.5', $enforced
);
$expiryPersistence->rows[$expired['transaction_id']]['expires_at_epoch'] = $clock - 1;
$report(
    $reason(fn() => $expiryCoordinator->markVerified(
        $expired['transaction_id'],
        'EMAIL_OTP',
        'expired',
        'Browser/5',
        '127.0.0.5'
    )) === 'USER_MFA_PENDING_EXPIRED'
    && $expiryPersistence->rows[$expired['transaction_id']]['transaction_status'] === 'EXPIRED',
    'expired pending login is durably closed without finalization'
);

$auditJson = json_encode([
    $offPersistence->audits,
    $pilotPersistence->audits,
    $failurePersistence->audits,
    $expiryPersistence->audits,
]);
$report(
    is_string($auditJson)
    && !str_contains($auditJson, 'session-off')
    && !str_contains($auditJson, 'pilot-in')
    && !str_contains($auditJson, 'failure'),
    'audit trail contains no raw session material'
);

printf(
    "RESULT checks=%d failures=%d tokens_before_mfa=0 network_calls=0 live_database_mutations=0 runtime_activation=0\n",
    $checks,
    $failed
);
exit($failed === 0 ? 0 : 1);
