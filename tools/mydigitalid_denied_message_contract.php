<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$ms = require $root . '/config/locales/ms.php';
$en = require $root . '/config/locales/en.php';
$endpoint = (string) file_get_contents(
    $root . '/app/Auth/MyDigitalId/MyDigitalIdAccessDeniedEndpoint.php'
);

$checks = [
    'BM message explains unmatched record and password fallback' =>
        str_contains($ms['mydigitalid.denied.message'] ?? '', 'tidak dapat memadankan')
        && str_contains($ms['mydigitalid.denied.message'] ?? '', 'ID OneID serta kata laluan'),
    'English message explains unmatched record and password fallback' =>
        str_contains($en['mydigitalid.denied.message'] ?? '', 'could not match')
        && str_contains($en['mydigitalid.denied.message'] ?? '', 'OneID ID and password'),
    'message remains generic without account-state reason disclosure' =>
        !preg_match('/NOT_FOUND|INACTIVE|AMBIGUOUS/', $ms['mydigitalid.denied.message'] ?? '')
        && !preg_match('/NOT_FOUND|INACTIVE|AMBIGUOUS/', $en['mydigitalid.denied.message'] ?? ''),
    'denied page still exposes password-login and account-switch actions' =>
        str_contains($endpoint, "oneid_translate('mydigitalid.denied.password_login')")
        && str_contains($endpoint, "oneid_translate('login.mydigitalid.switch_account')"),
];

$failures = 0;
foreach ($checks as $description => $passed) {
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $description);
    if (!$passed) {
        $failures++;
    }
}
printf("RESULT checks=%d failures=%d\n", count($checks), $failures);
exit($failures === 0 ? 0 : 1);
