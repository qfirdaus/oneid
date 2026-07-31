<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$dashboard = (string) file_get_contents($root . '/page/dashboard.php');
$security = (string) file_get_contents($root . '/page/user_mfa_security.php');

$checks = [
    'dashboard loads application autoloader before policy reader' =>
        ($autoload = strpos($dashboard, "vendor/autoload.php")) !== false
        && ($reader = strpos($dashboard, 'PdoUserMfaPolicyReader')) !== false
        && $autoload < $reader,
    'Account Security page loads application autoloader before policy reader' =>
        ($securityAutoload = strpos($security, "vendor/autoload.php")) !== false
        && ($securityReader = strpos($security, 'PdoUserMfaPolicyReader')) !== false
        && $securityAutoload < $securityReader,
    'dashboard permits self-service in full enforcement mode' =>
        str_contains($dashboard, "['ENROLLMENT', 'PILOT_ENFORCED', 'ENFORCED']")
        && str_contains($dashboard, 'selfServiceEligible($userMfaUser)'),
    'Account Security still enforces eligible active normal account' =>
        str_contains($security, 'selfServiceEligible($user)')
        && str_contains($security, "exit('Account Security access is not available')"),
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
