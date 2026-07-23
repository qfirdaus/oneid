<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

require_once dirname(__DIR__) . '/lib/config.php';
require_once dirname(__DIR__) . '/bootstrap/sync_runtime.php';

use OneId\App\Sync\Odl\OdlDataQualityAudit;
use OneId\App\Sync\Odl\OdlSourceConfig;
use OneId\App\Sync\Odl\OdlStudentSource;

try {
    $rows = (new OdlStudentSource(
        OdlSourceConfig::fromPrivateRuntime()
    ))->fetchAll();

    $pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $users = $pdo->query(
        'SELECT u_id,data2,avail_status,account_source,sync_protected
         FROM user_tbl'
    )->fetchAll();
    $memberships = $pdo->query(
        'SELECT u_id,source_code,external_user_id
         FROM user_external_identity'
    )->fetchAll();

    $result = (new OdlDataQualityAudit())->audit(
        $rows,
        $users,
        $memberships
    );
    echo json_encode(
        $result,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    ), "\n";
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
        : 'ODL_F4_AUDIT_FAILED';
    printf(
        "RESULT ready=no error=%s mutation_statements=0\n",
        $code
    );
    exit(1);
}
