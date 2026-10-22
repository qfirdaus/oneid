<?php

declare(strict_types=1);

putenv('ONEID_API_INTERNAL_CLIENT_ID=compatibility-test');
putenv('ONEID_API_INTERNAL_CLIENT_SECRET=compatibility-secret');
require_once dirname(__DIR__, 2) . '/lib/integration_security.php';

$root = dirname(__DIR__, 2);
$sources = [];
foreach (['sso_IDP_index.php', 'sso_IDP_sub.php', 'SSO_IDP_INC.php'] as $name) {
    $sources[$name] = (string) file_get_contents($root . '/lib/' . $name);
}
$compatibilitySources = $sources['sso_IDP_index.php'] . $sources['sso_IDP_sub.php'];
$allSources = implode("\n", $sources);

$activeDebugOutput = preg_match('/^[ \t]*echo\s+(?:\$_COOKIE|["\'][Xa]\d*["\'])/m', $allSources) === 1;
$checks = [
    'legacy routes delegate to the shared HTTP client' => substr_count(
        $allSources,
        'oneid_internal_integration_post_json('
    ) === 3,
    'compatibility cookies use the secure helper' => substr_count(
        $compatibilitySources,
        'oneid_set_sso_cookie('
    ) === 8 && !str_contains($compatibilitySources, "setcookie('sso_cre'"),
    'legacy cookie lifetimes remain unchanged' => str_contains($sources['sso_IDP_index.php'], '86400 * 30')
        && str_contains($sources['sso_IDP_sub.php'], '60 * 30'),
    'active debug and cookie output is removed' => !$activeDebugOutput,
    'normal response shape remains accepted' => oneid_internal_integration_response_is_valid(
        '{"respond_flag":"1","respond":"1"}'
    ),
    'refresh response shape remains accepted' => oneid_internal_integration_response_is_valid(
        '{"respond_flag":"2","respond_new_token":"fixture","respond_user_packet":{}}'
    ),
    'malformed response is rejected' => !oneid_internal_integration_response_is_valid('not-json')
        && !oneid_internal_integration_response_is_valid('{"respond_flag":"1"}')
        && !oneid_internal_integration_response_is_valid(
            '{"respond_flag":"2","respond_new_token":"fixture"}'
        ),
    'failure response preserves legacy control flow' => json_decode(
        oneid_internal_integration_failure_response(),
        true
    ) === ['respond_flag' => '0', 'respond' => '0'],
    'shared client retains TLS verification and bounded timeouts' => str_contains(
        (string) file_get_contents($root . '/lib/integration_security.php'),
        'CURLOPT_SSL_VERIFYHOST => 2'
    ) && str_contains(
        (string) file_get_contents($root . '/lib/integration_security.php'),
        'CURLOPT_SSL_VERIFYPEER => true'
    ) && str_contains(
        (string) file_get_contents($root . '/lib/integration_security.php'),
        'CURLOPT_CONNECTTIMEOUT => 5'
    ) && str_contains(
        (string) file_get_contents($root . '/lib/integration_security.php'),
        'CURLOPT_TIMEOUT => 12'
    ),
];

$failures = 0;
foreach ($checks as $description => $passed) {
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $description);
    $failures += $passed ? 0 : 1;
}
printf(
    "RESULT checks=%d failures=%d network_calls=0 database_access=0 secret_output=0\n",
    count($checks),
    $failures
);
exit($failures === 0 ? 0 : 1);
