<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

require_once dirname(__DIR__) . '/lib/secrets.php';

$targets = [
    'staff' => ['ONEID_STAFF_ODBC_DSN', 'ONEID_STAFF_ODBC_USERNAME', 'ONEID_STAFF_ODBC_PASSWORD'],
    'student_sync' => ['ONEID_STUDENT_SYNC_ODBC_DSN', 'ONEID_STUDENT_SYNC_ODBC_USERNAME', 'ONEID_STUDENT_SYNC_ODBC_PASSWORD'],
    'student_lookup' => ['ONEID_STUDENT_LOOKUP_ODBC_DSN', 'ONEID_STUDENT_LOOKUP_ODBC_USERNAME', 'ONEID_STUDENT_LOOKUP_ODBC_PASSWORD'],
    'skp' => ['ONEID_SKP_ODBC_DSN', 'ONEID_SKP_ODBC_USERNAME', 'ONEID_SKP_ODBC_PASSWORD'],
    'idms' => ['ONEID_IDMS_ODBC_CONNECTION', 'ONEID_IDMS_ODBC_USERNAME', 'ONEID_IDMS_ODBC_PASSWORD'],
];

if (!function_exists('odbc_connect')) {
    fwrite(STDERR, "FAIL extension=odbc unavailable\n");
    exit(1);
}

$failed = 0;
foreach ($targets as $label => [$dsnKey, $usernameKey, $passwordKey]) {
    $connection = @odbc_connect(
        oneid_secret($dsnKey),
        oneid_secret($usernameKey),
        oneid_secret($passwordKey)
    );

    if ($connection === false) {
        $failed++;
        printf("FAIL %-16s connect sqlstate=%s\n", $label, odbc_error() ?: 'UNKNOWN');
        continue;
    }

    $versionResult = @odbc_exec($connection, 'SELECT @@version AS server_version');
    if ($versionResult === false) {
        $failed++;
        printf("FAIL %-16s version-query sqlstate=%s\n", $label, odbc_error($connection) ?: 'UNKNOWN');
        odbc_close($connection);
        continue;
    }

    $versionRow = odbc_fetch_array($versionResult);
    $version = is_array($versionRow) ? (string) array_values($versionRow)[0] : '';
    preg_match('/Adaptive Server Enterprise\/([^\/\s]+)/i', $version, $matches);
    $serverVersion = $matches[1] ?? 'unknown';

    // Capability probe only: SELECT has no mutation and the value is constant.
    $statement = @odbc_prepare($connection, 'SELECT ? AS probe_value');
    $preparedState = 'unsupported';
    if ($statement !== false) {
        $preparedState = @odbc_execute($statement, ['oneid-odbc-audit']) ? 'supported' : 'execute-failed';
    }

    printf(
        "PASS %-16s server=ASE/%s prepared=%s mutation_statements=0\n",
        $label,
        $serverVersion,
        $preparedState
    );
    odbc_close($connection);
}

printf("RESULT checks=%d failed=%d mutation_statements=0\n", count($targets), $failed);
exit($failed === 0 ? 0 : 1);
