<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

$root = dirname(__DIR__);
$read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);
$gateway = $read('app/Auth/MyDigitalId/MyDigitalIdProtocolGateway.php');
$callbackRequest = $read('app/Auth/MyDigitalId/MyDigitalIdCallbackRequest.php');
$transactions = $read('app/Auth/MyDigitalId/MyDigitalIdAuthorizationTransactionStore.php');
$orchestrator = $read('app/Auth/MyDigitalId/MyDigitalIdCallbackOrchestrator.php');
$matcher = $read('app/Auth/MyDigitalId/PdoMyDigitalIdAccountMatcher.php');
$linking = $read('app/Auth/MyDigitalId/MyDigitalIdAccountLinkingService.php');
$protector = $read('app/Auth/MyDigitalId/MyDigitalIdIdentityProtector.php');
$finalizer = $read('app/Auth/MyDigitalId/MyDigitalIdLocalLoginFinalizer.php');
$callback = $read('app/Auth/MyDigitalId/MyDigitalIdCallbackEndpoint.php');
$runtime = $read('config/runtime.php');
$checks = [];

$checks['state_before_exchange'] = strpos($orchestrator, '->consume(')
    < strpos($orchestrator, '->complete(')
    && str_contains($transactions, 'hash_equals(')
    && str_contains($transactions, 'TTL_SECONDS = 300');
$checks['callback_allowlist'] = str_contains($callbackRequest, 'ALLOWED_KEYS')
    && str_contains($callbackRequest, "if (\$method !== 'GET')")
    && str_contains($callbackRequest, 'https://sso.digital-id.my/realms/upnm');
$checks['token_crypto'] = str_contains($gateway, "(\$idHeader->alg ?? null) !== 'RS256'")
    && str_contains($gateway, "\$idHeader->kid")
    && str_contains($gateway, 'hash_equals($transaction->nonce, $tokenNonce)')
    && str_contains($gateway, 'assertRequiredTimeClaims')
    && str_contains($gateway, 'hash_equals($idSubject, $userSubject)');
$checks['exact_account_gate'] = str_contains($matcher, 'count($active) > 1')
    && str_contains($matcher, "(int) \$row['avail_status'] !== 1")
    && str_contains($linking, 'findActiveBySubject')
    && str_contains($linking, 'findActiveByUser');
$checks['no_registration_overwrite'] = !preg_match('/INSERT\\s+INTO\\s+user_tbl/i', $linking)
    && !preg_match('/UPDATE\\s+user_tbl/i', $linking)
    && !str_contains($linking, 'verified->name');
$checks['pii_minimization'] = str_contains($protector, "hash_hmac('sha256'")
    && !preg_match('/error_log\\s*\\([^;]*(?:nric|name|idToken)/i', $gateway . $linking . $callback);
$checks['token_compensation'] = str_contains($finalizer, 'update_specific_token_status')
    && str_contains($finalizer, 'oneid_clear_local_authenticated_session')
    && str_contains($finalizer, "\$_SESSION['auth_method'] = 'mydigitalid'");
$checks['safe_redirects'] = str_contains(
    $read('app/Auth/MyDigitalId/MyDigitalIdAuthorizationTransaction.php'),
    "\$returnPath !== '/page/dashboard'"
) && str_contains(
    $read('app/Auth/MyDigitalId/MyDigitalIdConfig.php'),
    "\$path !== '/auth/mydigitalid/callback.php'"
);
$checks['generic_errors'] = str_contains($callback, 'redirectWithFlash')
    && !str_contains($read('index.php'), 'MYDID_IDENTITY_MISMATCH')
    && !str_contains($read('index.php'), 'MYDID_USER_NOT_FOUND');
$checks['dormant_default'] = str_contains($runtime, "'ONEID_MYDID_ENABLED' => 'false'")
    && strpos($callback, 'if (!$config->enabled)') < strpos($callback, 'new \\PDO(');
$checks['reference_isolation'] = str_contains(
    $read('.gitignore'),
    '/resources/references/mydigital-id/'
);

$failed = count(array_filter($checks, static fn(bool $passed): bool => !$passed));
foreach ($checks as $name => $passed) {
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $name);
}
printf(
    "RESULT checks=%d failures=%d feature_activation=0 secret_output=0\n",
    count($checks),
    $failed
);
exit($failed === 0 ? 0 : 1);
