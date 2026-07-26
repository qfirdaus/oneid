<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

$root = dirname(__DIR__);
$read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);
$orchestrator = $read('app/Auth/MyDigitalId/MyDigitalIdCallbackOrchestrator.php');
$finalizer = $read('app/Auth/MyDigitalId/MyDigitalIdLocalLoginFinalizer.php');
$endpoint = $read('app/Auth/MyDigitalId/MyDigitalIdCallbackEndpoint.php');
$runtime = $read('config/runtime.php');
$login = $read('index.php');
$checks = [];
$checks['ordered_callback'] = strpos($orchestrator, '->consume(') < strpos($orchestrator, '->complete(')
    && strpos($orchestrator, '->complete(') < strpos($orchestrator, '->authenticate(')
    && strpos($orchestrator, '->authenticate(') < strpos($orchestrator, '->finalize(');
$checks['reject_has_no_finalization'] = str_contains($orchestrator, 'if (!$decision->allowed')
    && str_contains($orchestrator, "'redirect_path' => null");
$checks['token_policy'] = str_contains($finalizer, "['multi_session']")
    && str_contains($finalizer, 'update_whole_token_status')
    && str_contains($finalizer, 'add_new_token');
$checks['session_security'] = str_contains($finalizer, 'oneid_establish_authenticated_session')
    && str_contains($finalizer, "\$_SESSION['auth_method'] = 'mydigitalid'");
$checks['compensation'] = str_contains($finalizer, 'update_specific_token_status')
    && str_contains($finalizer, 'oneid_clear_local_authenticated_session');
$checks['production_seam'] = str_contains($endpoint, 'MyDigitalIdCallbackOrchestrator')
    && str_contains($endpoint, 'MyDigitalIdAccountLinkingService')
    && str_contains($endpoint, 'MyDigitalIdLocalLoginFinalizer');
$checks['account_switch_guard'] = str_contains(
    $read('app/Auth/MyDigitalId/MyDigitalIdLoginEndpoint.php'),
    "\$_SESSION['login_status'] ?? ''"
);
$checks['dormant'] = str_contains($runtime, "'ONEID_MYDID_ENABLED' => 'false'")
    && strpos($endpoint, 'if (!$config->enabled)') < strpos($endpoint, 'new MyDigitalIdCallbackOrchestrator')
    && str_contains($login, 'if ($myDigitalIdEnabled)');

$failed = count(array_filter($checks, static fn(bool $passed): bool => !$passed));
foreach ($checks as $name => $passed) {
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $name);
}
printf(
    "RESULT checks=%d failures=%d feature_activation=0 live_schema_mutations=0\n",
    count($checks),
    $failed
);
exit($failed === 0 ? 0 : 1);
