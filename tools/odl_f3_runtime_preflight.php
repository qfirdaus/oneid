<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

require_once dirname(__DIR__) . '/lib/config.php';
require_once dirname(__DIR__) . '/bootstrap/sync_runtime.php';

use OneId\App\Sync\Odl\OdlSourceConfig;
use OneId\App\Sync\Odl\OdlStudentSource;

$requiredKeys = [
    'ONEID_ODL_MYSQL_HOST',
    'ONEID_ODL_MYSQL_PORT',
    'ONEID_ODL_MYSQL_DATABASE',
    'ONEID_ODL_MYSQL_USERNAME',
    'ONEID_ODL_MYSQL_PASSWORD',
    'ONEID_ODL_MYSQL_SSL_CA',
    'ONEID_ODL_MYSQL_CONNECT_TIMEOUT',
];
$missing = [];
foreach ($requiredKeys as $key) {
    try {
        if (trim(oneid_secret($key)) === '') {
            $missing[] = $key;
        }
    } catch (Throwable) {
        $missing[] = $key;
    }
}
if ($missing !== []) {
    printf(
        "RESULT ready=no missing_keys=%s mutation_statements=0\n",
        implode(',', $missing)
    );
    exit(1);
}

try {
    $rows = (new OdlStudentSource(
        OdlSourceConfig::fromPrivateRuntime()
    ))->fetchAll();
    $blankMatric = 0;
    $blankIc = 0;
    $wrongCategory = 0;
    $wrongSource = 0;
    foreach ($rows as $row) {
        $blankMatric += trim((string) ($row['data4'] ?? '')) === '' ? 1 : 0;
        $blankIc += trim((string) ($row['data2'] ?? '')) === '' ? 1 : 0;
        $wrongCategory += ($row['ext_data_source_category'] ?? '') !== 'Pelajar'
            ? 1
            : 0;
        $wrongSource += ($row['source_code'] ?? '') !== 'STUDENT_ODL_PG'
            ? 1
            : 0;
    }
    printf(
        "RESULT ready=yes rows=%d blank_matric=%d blank_ic=%d wrong_category=%d wrong_source=%d mutation_statements=0\n",
        count($rows),
        $blankMatric,
        $blankIc,
        $wrongCategory,
        $wrongSource
    );
    exit(
        $blankMatric === 0
        && $blankIc === 0
        && $wrongCategory === 0
        && $wrongSource === 0
            ? 0
            : 1
    );
} catch (Throwable $exception) {
    $allowed = [
        'ODL_PDO_MYSQL_TLS_UNAVAILABLE',
        'ODL_SOURCE_CONNECTION_FAILED',
        'ODL_TLS_NOT_ACTIVE',
        'ODL_SOURCE_QUERY_FAILED',
        'ODL_SOURCE_EMPTY',
        'ODL_SOURCE_ROW_INVALID',
    ];
    $code = in_array($exception->getMessage(), $allowed, true)
        ? $exception->getMessage()
        : 'ODL_RUNTIME_PREFLIGHT_FAILED';
    printf(
        "RESULT ready=no error=%s mutation_statements=0\n",
        $code
    );
    exit(1);
}
