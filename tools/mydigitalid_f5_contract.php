<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

$root = dirname(__DIR__);
$read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);
$index = $read('index.php');
$ms = $read('config/locales/ms.php');
$en = $read('config/locales/en.php');
$callback = $read('app/Auth/MyDigitalId/MyDigitalIdCallbackEndpoint.php');
$orchestrator = $read('app/Auth/MyDigitalId/MyDigitalIdCallbackOrchestrator.php');
$logoutEndpoint = $read('app/Auth/LogoutEndpoint.php');
$logoutHandler = $read('app/Auth/LogoutHandler.php');
$runtime = $read('config/runtime.php');
$checks = [];
$checks['flagged_ui'] = str_contains($index, 'if ($myDigitalIdEnabled)')
    && str_contains($index, 'href="auth/mydigitalid/login.php"')
    && str_contains($index, 'mydigitalid-preview')
    && str_contains($runtime, "'ONEID_MYDID_ENABLED' => 'false'");
$checks['accessible_ui'] = str_contains($index, 'aria-live="polite"')
    && str_contains($index, 'login.mydigitalid.submit')
    && str_contains($index, 'login.mydigitalid.help');
$keys = [
    'login.mydigitalid.divider',
    'login.mydigitalid.submit',
    'login.mydigitalid.help',
    'login.mydigitalid.invalid',
    'login.mydigitalid.unavailable',
    'login.mydigitalid.temporary',
];
$checks['bilingual'] = count(array_filter(
    $keys,
    static fn(string $key): bool => str_contains($ms, "'{$key}'")
        && str_contains($en, "'{$key}'")
)) === count($keys);
$checks['generic_flash'] = str_contains($callback, 'redirectWithFlash')
    && str_contains($callback, "'mydigitalid_invalid'")
    && str_contains($callback, "'mydigitalid_unavailable'")
    && str_contains($callback, "'mydigitalid_temporary'")
    && !str_contains($index, 'MYDID_USER_NOT_FOUND')
    && !str_contains($index, 'MYDID_IDENTITY_MISMATCH');
$checks['logout_state'] = str_contains($orchestrator, "\$session['mydigitalid_id_token']")
    && str_contains($logoutEndpoint, "\$_SESSION['auth_method']")
    && str_contains($logoutEndpoint, 'MyDigitalIdLogoutUrl');
$checks['local_first_logout'] = strpos($logoutHandler, 'update_specific_token_status')
    < strpos($logoutHandler, "header('Location:");
$checks['password_logout_compatible'] = str_contains(
    $logoutHandler,
    '?string $federatedLogoutUrl = null'
) && str_contains($logoutHandler, '$federatedLogoutUrl ?? $redirectUrl');

$failed = count(array_filter($checks, static fn(bool $passed): bool => !$passed));
foreach ($checks as $name => $passed) {
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $name);
}
printf(
    "RESULT checks=%d failures=%d feature_activation=0 raw_error_detail_output=0\n",
    count($checks),
    $failed
);
exit($failed === 0 ? 0 : 1);
