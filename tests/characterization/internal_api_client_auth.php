<?php

declare(strict_types=1);

$failures = 0;
$report = static function (bool $passed, string $label) use (&$failures): void {
    if (!$passed) {
        $failures++;
    }
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $label);
};

putenv('ONEID_API_INTERNAL_CLIENT_ID=oneid-internal-test');
putenv('ONEID_API_INTERNAL_CLIENT_SECRET=test-secret-value');

require_once dirname(__DIR__, 2) . '/lib/integration_security.php';

$headers = oneid_internal_integration_headers();
$report($headers === [
    'Content-Type: text/plain',
    'X-OneID-Client-ID: oneid-internal-test',
    'Authorization: Bearer test-secret-value',
], 'internal API headers include the configured client identity and bearer secret');

$index = (string) file_get_contents(dirname(__DIR__, 2) . '/lib/sso_IDP_index.php');
$sub = (string) file_get_contents(dirname(__DIR__, 2) . '/lib/sso_IDP_sub.php');
$inc = (string) file_get_contents(dirname(__DIR__, 2) . '/lib/SSO_IDP_INC.php');

foreach (['index' => $index, 'sub' => $sub, 'inc' => $inc] as $name => $source) {
    $report(
        str_contains($source, 'oneid_internal_integration_headers()'),
        $name . ' internal API caller sends configured authentication headers'
    );
    $report(
        !str_contains($source, 'CURLOPT_SSL_VERIFYHOST, false')
            && !str_contains($source, 'CURLOPT_SSL_VERIFYPEER, false'),
        $name . ' internal API caller does not disable TLS verification'
    );
}

$report(
    preg_match('/^[ \t]*echo\s+\$_COOKIE\[[^]]+\]/m', $index) !== 1,
    'legacy compatibility route does not output an SSO cookie'
);

printf("RESULT checks=8 failures=%d network_calls=0 secret_output=0\n", $failures);
exit($failures === 0 ? 0 : 1);
