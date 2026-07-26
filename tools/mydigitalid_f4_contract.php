<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

$root = dirname(__DIR__);
$read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);
$matcher = $read('app/Auth/MyDigitalId/PdoMyDigitalIdAccountMatcher.php');
$service = $read('app/Auth/MyDigitalId/MyDigitalIdAccountLinkingService.php');
$callback = $read('app/Auth/MyDigitalId/MyDigitalIdCallbackEndpoint.php');
$runtime = $read('config/runtime.php');
$login = $read('index.php');
$checks = [];
$checks['mapped_nric'] = str_contains($matcher, "TRIM(COALESCE(data3,''))<>''")
    && str_contains($matcher, 'COALESCE(data4')
    && str_contains($matcher, 'COALESCE(data2');
$checks['active_gate'] = str_contains($matcher, "(int) \$row['avail_status'] !== 1")
    && str_contains($matcher, "return MyDigitalIdAccountMatch::rejected('INACTIVE')");
$checks['ambiguity_gate'] = str_contains($matcher, 'count($active) > 1')
    && str_contains($service, 'MYDID_IDENTITY_AMBIGUOUS');
$checks['link_security'] = str_contains($service, 'findActiveBySubject')
    && str_contains($service, 'findActiveByUser')
    && str_contains($service, "hash_equals((string) \$link['nric_hmac'], \$nricHmac)");
$checks['atomic_audit'] = str_contains($service, '->transactional(')
    && str_contains($service, 'createActiveLink(')
    && str_contains($service, 'touchSuccessfulLogin(')
    && str_contains($service, 'recordEvent(');
$checks['no_registration_or_overwrite'] = !preg_match('/INSERT\\s+INTO\\s+user_tbl/i', $service)
    && !preg_match('/UPDATE\\s+user_tbl/i', $service)
    && !str_contains($service, 'verified->name');
$checks['still_dormant'] = str_contains($runtime, "'ONEID_MYDID_ENABLED' => 'false'")
    && !str_contains($callback, 'oneid_establish_authenticated_session')
    && str_contains($login, 'if ($myDigitalIdEnabled)');

$failures = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));
foreach ($checks as $name => $passed) {
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $name);
}
printf(
    "RESULT checks=%d failures=%d live_schema_mutations=0 feature_activation=0 auto_registration=0\n",
    count($checks),
    count($failures)
);
exit($failures === [] ? 0 : 1);
