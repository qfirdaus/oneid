<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use OneId\App\Auth\MyDigitalId\MyDigitalIdAccountAuthorizerInterface;
use OneId\App\Auth\MyDigitalId\MyDigitalIdAuthenticationDecision;
use OneId\App\Auth\MyDigitalId\MyDigitalIdAuthorizationTransaction;
use OneId\App\Auth\MyDigitalId\MyDigitalIdAuthorizationTransactionStore;
use OneId\App\Auth\MyDigitalId\MyDigitalIdCallbackOrchestrator;
use OneId\App\Auth\MyDigitalId\MyDigitalIdCallbackRequest;
use OneId\App\Auth\MyDigitalId\MyDigitalIdLocalLoginFinalizer;
use OneId\App\Auth\MyDigitalId\MyDigitalIdLocalLoginFinalizerInterface;
use OneId\App\Auth\MyDigitalId\MyDigitalIdPersistenceException;
use OneId\App\Auth\MyDigitalId\MyDigitalIdProtocolGatewayInterface;
use OneId\App\Auth\MyDigitalId\MyDigitalIdVerifiedIdentity;

final class F4bProtocol implements MyDigitalIdProtocolGatewayInterface
{
    public int $calls = 0;
    public bool $fail = false;

    public function complete(
        MyDigitalIdCallbackRequest $request,
        MyDigitalIdAuthorizationTransaction $transaction
    ): MyDigitalIdVerifiedIdentity {
        $this->calls++;
        if ($this->fail) {
            throw new MyDigitalIdPersistenceException('FIXTURE_PROTOCOL_FAILURE');
        }
        return new MyDigitalIdVerifiedIdentity('fixture-sub', 'Provider Name', '900101011234', 'a.b.c');
    }
}

final class F4bAccounts implements MyDigitalIdAccountAuthorizerInterface
{
    public int $calls = 0;
    public bool $allow = true;

    public function authenticate(
        MyDigitalIdVerifiedIdentity $verified,
        DateTimeImmutable $occurredAt,
        array $context = []
    ): MyDigitalIdAuthenticationDecision {
        $this->calls++;
        return $this->allow
            ? new MyDigitalIdAuthenticationDecision(true, 'MYDID_LOGIN_SUCCESS', [
                'u_id' => 'STAFF-A',
                'u_type' => 0,
                'avail_status' => 1,
                'password_change_required' => 0,
                'data1' => 'Canonical Name',
            ], 1)
            : new MyDigitalIdAuthenticationDecision(false, 'MYDID_USER_NOT_FOUND', null, null);
    }
}

final class F4bFinalizer implements MyDigitalIdLocalLoginFinalizerInterface
{
    public int $calls = 0;
    public function finalize(array $user, string $deviceInfo): void
    {
        $this->calls++;
    }
}

final class F4bLegacyOperation
{
    public int $multiSession = 0;
    public int $revocations = 0;
    public int $inserts = 0;
    public int $compensations = 0;

    public function get_system_config(): array
    {
        return ['multi_session' => $this->multiSession];
    }
    public function update_whole_token_status(string $userId, int $status): int
    {
        $this->revocations++;
        return 2;
    }
    public function add_new_token(string $token, string $userId, string $device): int
    {
        $this->inserts++;
        return 1;
    }
    public function update_specific_token_status(string $userId, string $token, int $status): int
    {
        $this->compensations++;
        return 1;
    }
}

$checks = 0;
$failures = 0;
$check = static function (bool $passed, string $description) use (&$checks, &$failures): void {
    $checks++;
    $failures += $passed ? 0 : 1;
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $description);
};
$makeSession = static function (): array {
    $session = [];
    (new MyDigitalIdAuthorizationTransactionStore())->create(
        $session,
        1_700_000_000,
        '/page/dashboard',
        static fn(int $length): string => str_repeat("\x55", $length)
    );
    return $session;
};
$queryFor = static fn(array $session): array => [
    'code' => 'fixture.code',
    'state' => $session[MyDigitalIdAuthorizationTransactionStore::SESSION_KEY]['state'],
];

$protocol = new F4bProtocol();
$accounts = new F4bAccounts();
$finalizer = new F4bFinalizer();
$orchestrator = new MyDigitalIdCallbackOrchestrator(
    new MyDigitalIdAuthorizationTransactionStore(),
    $protocol,
    $accounts,
    $finalizer
);
$session = $makeSession();
$result = $orchestrator->handle(
    'GET',
    $queryFor($session),
    $session,
    1_700_000_010,
    '192.0.2.1',
    'Fixture Browser',
    'Fixture Device'
);
$check(
    $result === [
        'allowed' => true,
        'reason' => 'MYDID_LOGIN_SUCCESS',
        'redirect_path' => '/page/dashboard',
    ]
        && $protocol->calls === 1
        && $accounts->calls === 1
        && $finalizer->calls === 1
        && ($session['mydigitalid_id_token'] ?? '') === 'a.b.c',
    'successful callback verifies, authorizes, finalizes and retains transient ID token for logout'
);
$check(
    !isset($session[MyDigitalIdAuthorizationTransactionStore::SESSION_KEY]),
    'successful callback consumes the transaction'
);

$accounts->allow = false;
$session = $makeSession();
$rejected = $orchestrator->handle(
    'GET',
    $queryFor($session),
    $session,
    1_700_000_010,
    '',
    '',
    ''
);
$check(
    !$rejected['allowed']
        && $rejected['reason'] === 'MYDID_USER_NOT_FOUND'
        && $finalizer->calls === 1,
    'rejected account never creates a local token or session'
);

$protocol->fail = true;
$session = $makeSession();
$accountCallsBefore = $accounts->calls;
try {
    $orchestrator->handle('GET', $queryFor($session), $session, 1_700_000_010, '', '', '');
    $protocolFailureBlocked = false;
} catch (MyDigitalIdPersistenceException) {
    $protocolFailureBlocked = true;
}
$check(
    $protocolFailureBlocked
        && $accounts->calls === $accountCallsBefore
        && !isset($session[MyDigitalIdAuthorizationTransactionStore::SESSION_KEY]),
    'protocol failure is terminal and cannot reach account authorization'
);

$_SESSION = [];
$operation = new F4bLegacyOperation();
$cookieCalls = 0;
$sessionCalls = 0;
$finalizerService = new MyDigitalIdLocalLoginFinalizer(
    $operation,
    static fn(): string => str_repeat('a', 64),
    static function (string $token) use (&$cookieCalls): void {
        $cookieCalls++;
    },
    static function (array $user) use (&$sessionCalls): void {
        $sessionCalls++;
        $_SESSION['login_status'] = 'true';
    }
);
$user = [
    'u_id' => 'STAFF-A',
    'u_type' => 0,
    'avail_status' => 1,
    'password_change_required' => 0,
    'data1' => 'Canonical Name',
];
$finalizerService->finalize($user, 'Fixture Device');
$check(
    $operation->revocations === 1
        && $operation->inserts === 1
        && $cookieCalls === 1
        && $sessionCalls === 1
        && ($_SESSION['auth_method'] ?? '') === 'mydigitalid',
    'local finalizer enforces single-session policy, token, cookie, session and auth_method'
);

$_SESSION = ['stale' => 'value'];
$failingOperation = new F4bLegacyOperation();
$failingFinalizer = new MyDigitalIdLocalLoginFinalizer(
    $failingOperation,
    static fn(): string => str_repeat('b', 64),
    static function (string $token): void {
        throw new RuntimeException('EXPECTED_COOKIE_FAILURE');
    },
    static fn(array $user): null => null
);
try {
    $failingFinalizer->finalize($user, 'Fixture Device');
    $compensated = false;
} catch (MyDigitalIdPersistenceException $exception) {
    $compensated = $exception->reason === 'MYDID_LOCAL_LOGIN_FINALIZE_FAILED';
}
$check(
    $compensated
        && $failingOperation->compensations === 1
        && $_SESSION === [],
    'post-insert failure revokes the new token and clears partial session state'
);

printf(
    "RESULT checks=%d failures=%d network_calls=0 live_schema_mutations=0\n",
    $checks,
    $failures
);
exit($failures === 0 ? 0 : 1);
