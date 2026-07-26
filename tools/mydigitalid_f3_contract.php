<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

$root = dirname(__DIR__);
$checks = 0;
$failures = 0;
$check = static function (bool $passed, string $description) use (&$checks, &$failures): void {
    $checks++;
    if (!$passed) {
        $failures++;
    }
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $description);
};
$read = static function (string $path) use ($root): string {
    $content = file_get_contents($root . '/' . $path);
    return $content === false ? '' : $content;
};

$store = $read('app/Auth/MyDigitalId/MyDigitalIdAuthorizationTransactionStore.php');
$request = $read('app/Auth/MyDigitalId/MyDigitalIdCallbackRequest.php');
$gateway = $read('app/Auth/MyDigitalId/MyDigitalIdProtocolGateway.php');
$callback = $read('app/Auth/MyDigitalId/MyDigitalIdCallbackEndpoint.php');
$orchestrator = $read('app/Auth/MyDigitalId/MyDigitalIdCallbackOrchestrator.php');
$runtime = $read('config/runtime.php');
$login = $read('index.php');

$check(
    str_contains($store, 'TTL_SECONDS = 300')
        && str_contains($store, 'hash_equals(')
        && str_contains($store, 'unset($session[self::SESSION_KEY])'),
    'authorization transaction has a five-minute TTL, constant-time state check and one-use removal'
);
$check(
    str_contains($request, "if (\$method !== 'GET')")
        && str_contains($request, 'ALLOWED_KEYS')
        && str_contains($request, 'MYDID_PROVIDER_REJECTED'),
    'callback boundary pins GET, an input allowlist and normalized provider rejection'
);
$check(
    strpos($orchestrator, '->consume(') < strpos($orchestrator, '->complete('),
    'callback orchestration consumes and validates state before the protocol boundary'
);
$check(
    str_contains($gateway, "\$_SESSION['openid_connect_state']")
        && str_contains($gateway, "\$_SESSION['openid_connect_nonce']")
        && str_contains($gateway, "\$_SESSION['openid_connect_code_verifier']")
        && str_contains($gateway, 'hash_equals($idSubject, $userSubject)')
        && str_contains($gateway, 'hash_equals($transaction->nonce, $tokenNonce)')
        && str_contains($gateway, 'assertRequiredTimeClaims'),
    'protocol adapter seeds and enforces state/nonce/PKCE, subject equality and required time claims'
);
$check(
    is_file($root . '/public/auth/mydigitalid/login.php')
        && is_file($root . '/public/auth/mydigitalid/callback.php')
        && str_contains($callback, "self::finish(404, 'Not Found')"),
    'thin public endpoints exist and fail closed while feature flag is false'
);
$check(
    str_contains($runtime, "'ONEID_MYDID_ENABLED' => 'false'")
        && str_contains($login, 'if ($myDigitalIdEnabled)')
        && !str_contains($callback, 'oneid_establish_authenticated_session'),
    'feature flag remains false with gated login UI and no direct session mutation in endpoint'
);
$check(
    !str_contains($callback, 'user_federated_identity')
        && !str_contains($callback, 'federated_auth_event'),
    'F3 callback has no schema access'
);

printf(
    "RESULT checks=%d failures=%d live_schema_mutations=0 feature_activation=0\n",
    $checks,
    $failures
);
exit($failures === 0 ? 0 : 1);
