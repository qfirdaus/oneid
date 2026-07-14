<?php

/**
 * Read-only HTTP smoke test for restructuring R0-R3.
 *
 * Usage:
 *   php tools/restructure_smoke.php http://127.0.0.1:18081
 *   php tools/restructure_smoke.php https://oneid-next.local --insecure
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(2);
}

$baseUrl = rtrim((string) ($argv[1] ?? ''), '/');
$insecure = in_array('--insecure', $argv, true);
if ($baseUrl === '' || filter_var($baseUrl, FILTER_VALIDATE_URL) === false) {
    fwrite(STDERR, "Usage: php tools/restructure_smoke.php <base-url> [--insecure]\n");
    exit(2);
}

$checks = [
    ['path' => '/', 'statuses' => [200], 'content_type' => 'text/html'],
    ['path' => '/index.php', 'statuses' => [302], 'content_type' => 'text/html'],
    ['path' => '/page/dashboard.php', 'statuses' => [302], 'content_type' => 'text/html'],
    ['path' => '/admin/dashboard.php', 'statuses' => [302], 'content_type' => 'text/html'],
    ['path' => '/api.php', 'statuses' => [400], 'content_type' => 'application/json'],
    ['path' => '/idms.php', 'statuses' => [200], 'content_type' => 'application/json'],
    ['path' => '/skp_api.php', 'statuses' => [400], 'content_type' => 'application/json'],
    ['path' => '/lib/q_func.php', 'statuses' => [405], 'content_type' => 'application/json'],
    ['path' => '/assetsM/css/custom.css', 'statuses' => [200], 'content_type' => 'text/css'],
    ['path' => '/public_docs/MANUAL_SALAM.pdf', 'statuses' => [200], 'content_type' => 'application/pdf'],
];

$failed = 0;
foreach ($checks as $check) {
    $handle = curl_init($baseUrl . $check['path']);
    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => false,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYHOST => $insecure ? 0 : 2,
        CURLOPT_SSL_VERIFYPEER => !$insecure,
    ]);
    curl_exec($handle);
    $error = curl_error($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
    $contentType = strtolower((string) curl_getinfo($handle, CURLINFO_CONTENT_TYPE));
    curl_close($handle);

    $statusOk = in_array($status, $check['statuses'], true);
    $contentTypeOk = str_starts_with($contentType, $check['content_type']);
    $ok = $error === '' && $statusOk && $contentTypeOk;
    if (!$ok) {
        $failed++;
    }

    printf(
        "%s %-34s status=%d type=%s%s\n",
        $ok ? 'PASS' : 'FAIL',
        $check['path'],
        $status,
        $contentType === '' ? '-' : $contentType,
        $error === '' ? '' : ' error=' . $error
    );
}

printf("RESULT checks=%d failed=%d\n", count($checks), $failed);
exit($failed === 0 ? 0 : 1);

