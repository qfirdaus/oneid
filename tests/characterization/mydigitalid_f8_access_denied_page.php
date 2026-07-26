<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

$root = dirname(__DIR__, 2);
$read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);
$callback = $read('app/Auth/MyDigitalId/MyDigitalIdCallbackEndpoint.php');
$page = $read('app/Auth/MyDigitalId/MyDigitalIdAccessDeniedEndpoint.php');
$public = $read('public/auth/mydigitalid/access-denied.php');
$ms = $read('config/locales/ms.php');
$en = $read('config/locales/en.php');

$checks = [];
$checks['rejection_redirect'] = str_contains($callback, 'redirectAccessDenied()')
    && str_contains($callback, "'/auth/mydigitalid/access-denied.php'")
    && !str_contains($callback, "'reason=' .");
$checks['verified_state_gate'] = str_contains(
    $page,
    'MyDigitalIdRejectedLogoutState::isAvailable'
) && str_contains($page, "'mydigitalid_invalid'");
$checks['safe_account_switch'] = str_contains($page, 'oneid_csrf_token()')
    && str_contains($page, '/auth/mydigitalid/switch-account.php')
    && str_contains($page, 'method="post"');
$checks['generic_content'] = str_contains($page, 'mydigitalid.denied.heading')
    && str_contains($page, 'mydigitalid.denied.message')
    && !str_contains($page, 'MYDID_USER_NOT_FOUND')
    && !str_contains($page, 'MYDID_IDENTITY_MISMATCH')
    && !str_contains($page, 'AMBIGUOUS');
$checks['bilingual'] = count(array_filter(
    [
        'mydigitalid.denied.page_title',
        'mydigitalid.denied.eyebrow',
        'mydigitalid.denied.heading',
        'mydigitalid.denied.message',
        'mydigitalid.denied.notice',
        'mydigitalid.denied.password_login',
        'mydigitalid.denied.help',
    ],
    static fn(string $key): bool => str_contains($ms, "'{$key}'")
        && str_contains($en, "'{$key}'")
)) === 7;
$checks['no_store_headers'] = str_contains($page, 'no-store')
    && str_contains($page, 'Referrer-Policy: no-referrer')
    && str_contains($page, "frame-ancestors 'none'");
$checks['thin_public_endpoint'] = str_contains(
    $public,
    'MyDigitalIdAccessDeniedEndpoint::run();'
);

$failed = count(array_filter($checks, static fn(bool $passed): bool => !$passed));
foreach ($checks as $name => $passed) {
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $name);
}
printf(
    "RESULT checks=%d failures=%d network_calls=0 database_mutations=0 reason_output=0\n",
    count($checks),
    $failed
);
exit($failed === 0 ? 0 : 1);
