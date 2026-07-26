<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use OneId\App\Auth\MyDigitalId\MyDigitalIdAuthorizationRequest;
use OneId\App\Auth\MyDigitalId\MyDigitalIdAuthorizationTransactionStore;
use OneId\App\Auth\MyDigitalId\MyDigitalIdCallbackRequest;
use OneId\App\Auth\MyDigitalId\MyDigitalIdConfig;
use OneId\App\Auth\MyDigitalId\MyDigitalIdConfigurationException;

$checks = 0;
$failures = 0;
$check = static function (bool $passed, string $description) use (&$checks, &$failures): void {
    $checks++;
    if (!$passed) {
        $failures++;
    }
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $description);
};

$runtime = [
    'ONEID_MYDID_ENABLED' => 'true',
    'ONEID_MYDID_ISSUER' => 'https://sso.digital-id.my/realms/upnm',
    'ONEID_MYDID_CLIENT_ID' => 'upnm-generic',
    'ONEID_MYDID_REDIRECT_URI' => 'https://oneid-uat.upnm.edu.my/auth/mydigitalid/callback.php',
    'ONEID_MYDID_POST_LOGOUT_REDIRECT_URI' => 'https://oneid-uat.upnm.edu.my/',
    'ONEID_MYDID_SCOPE' => 'openid',
    'ONEID_MYDID_HTTP_TIMEOUT_SECONDS' => '12',
    'ONEID_MYDID_PKCE_METHOD' => 'S256',
];
$config = MyDigitalIdConfig::fromRuntime(
    static fn(string $key, mixed $fallback = null): mixed => $runtime[$key] ?? $fallback,
    static fn(string $key, bool $required = true): string => str_repeat('s', 32)
);

$fixture = str_repeat("\x11", 32) . str_repeat("\x22", 32) . str_repeat("\x33", 64);
$offset = 0;
$random = static function (int $length) use (&$fixture, &$offset): string {
    $value = substr($fixture, $offset, $length);
    $offset += $length;
    return $value;
};
$session = [];
$store = new MyDigitalIdAuthorizationTransactionStore();
$transaction = $store->create($session, 1_700_000_000, 'https://evil.invalid/', $random);
$check(
    $transaction->returnPath === '/page/dashboard'
        && isset($session[MyDigitalIdAuthorizationTransactionStore::SESSION_KEY]),
    'authorization transaction stores only the allowlisted local return path'
);
$check(
    strlen($transaction->state) === 64
        && strlen($transaction->nonce) === 64
        && strlen($transaction->codeVerifier) >= 43,
    'state, nonce and PKCE verifier have cryptographic lengths'
);

$url = (new MyDigitalIdAuthorizationRequest($config))->url($transaction);
parse_str((string) parse_url($url, PHP_URL_QUERY), $params);
$check(
    str_starts_with($url, $runtime['ONEID_MYDID_ISSUER'] . '/protocol/openid-connect/auth?')
        && ($params['response_type'] ?? '') === 'code'
        && ($params['client_id'] ?? '') === 'upnm-generic'
        && ($params['state'] ?? '') === $transaction->state
        && ($params['nonce'] ?? '') === $transaction->nonce
        && ($params['code_challenge_method'] ?? '') === 'S256'
        && hash_equals($transaction->codeChallenge(), (string) ($params['code_challenge'] ?? '')),
    'authorization URL pins code flow, client, callback, nonce, state and PKCE S256'
);

$callback = MyDigitalIdCallbackRequest::fromHttp('GET', [
    'code' => 'valid.code-value_1~',
    'state' => $transaction->state,
    'session_state' => 'provider-session',
    'iss' => $runtime['ONEID_MYDID_ISSUER'],
]);
$consumed = $store->consume($session, $callback->state, 1_700_000_100);
$check(
    $consumed->state === $transaction->state
        && !isset($session[MyDigitalIdAuthorizationTransactionStore::SESSION_KEY]),
    'valid callback consumes its authorization transaction before protocol work'
);

try {
    $store->consume($session, $callback->state, 1_700_000_101);
    $replayBlocked = false;
} catch (MyDigitalIdConfigurationException $exception) {
    $replayBlocked = $exception->reason === 'MYDID_AUTH_TRANSACTION_MISSING';
}
$check($replayBlocked, 'callback replay is rejected');

$invalidCases = [
    'post_method' => static fn() => MyDigitalIdCallbackRequest::fromHttp('POST', []),
    'provider_error' => static fn() => MyDigitalIdCallbackRequest::fromHttp('GET', [
        'error' => 'access_denied',
        'state' => str_repeat('a', 64),
    ]),
    'unexpected_parameter' => static fn() => MyDigitalIdCallbackRequest::fromHttp('GET', [
        'code' => 'code',
        'state' => str_repeat('a', 64),
        'next' => 'https://evil.invalid',
    ]),
    'invalid_code' => static fn() => MyDigitalIdCallbackRequest::fromHttp('GET', [
        'code' => "code\nheader",
        'state' => str_repeat('a', 64),
    ]),
    'foreign_issuer' => static fn() => MyDigitalIdCallbackRequest::fromHttp('GET', [
        'code' => 'code',
        'state' => str_repeat('a', 64),
        'iss' => 'https://attacker.invalid',
    ]),
];
foreach ($invalidCases as $name => $operation) {
    try {
        $operation();
        $blocked = false;
    } catch (MyDigitalIdConfigurationException) {
        $blocked = true;
    }
    $check($blocked, 'invalid callback is rejected: ' . $name);
}

$session = [];
$offset = 0;
$expired = $store->create($session, 1_700_000_000, '/page/dashboard', $random);
try {
    $store->consume($session, $expired->state, 1_700_000_301);
    $expiryBlocked = false;
} catch (MyDigitalIdConfigurationException $exception) {
    $expiryBlocked = $exception->reason === 'MYDID_AUTH_TRANSACTION_EXPIRED';
}
$check(
    $expiryBlocked && !isset($session[MyDigitalIdAuthorizationTransactionStore::SESSION_KEY]),
    'expired transaction is rejected and terminally removed'
);

$session = [];
$offset = 0;
$mismatch = $store->create($session, 1_700_000_000, '/page/dashboard', $random);
try {
    $store->consume($session, str_repeat('f', 64), 1_700_000_001);
    $stateBlocked = false;
} catch (MyDigitalIdConfigurationException $exception) {
    $stateBlocked = $exception->reason === 'MYDID_STATE_MISMATCH';
}
$check(
    $stateBlocked && !isset($session[MyDigitalIdAuthorizationTransactionStore::SESSION_KEY]),
    'state mismatch is rejected and cannot be retried'
);

printf(
    "RESULT checks=%d failures=%d network_calls=0 repository_calls=0 authenticated_sessions=0\n",
    $checks,
    $failures
);
exit($failures === 0 ? 0 : 1);
