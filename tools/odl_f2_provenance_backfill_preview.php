<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

require_once dirname(__DIR__) . '/lib/config.php';
require_once dirname(__DIR__) . '/lib/readonly_odbc.php';
require_once dirname(__DIR__) . '/app/Sync/ExternalRowNormalizer.php';
require_once dirname(__DIR__)
    . '/app/Sync/Provenance/ProvenanceBackfillPreview.php';

use OneId\App\Sync\ExternalRowNormalizer;
use OneId\App\Sync\Provenance\ProvenanceBackfillPreview;

if (!function_exists('odbc_connect')) {
    fwrite(STDERR, "FAIL ODBC_EXTENSION_UNAVAILABLE\n");
    exit(1);
}

$proposedSourceCode = 'STUDENT_ASIS_ACTIVE';
$connection = odbc_connect(
    oneid_secret('ONEID_STUDENT_SYNC_ODBC_DSN'),
    oneid_secret('ONEID_STUDENT_SYNC_ODBC_USERNAME'),
    oneid_secret('ONEID_STUDENT_SYNC_ODBC_PASSWORD')
);
if (!$connection) {
    fwrite(STDERR, "FAIL EXTERNAL_STUDENT_CONNECTION_FAILED\n");
    exit(1);
}

try {
    $sql = 'SELECT nama as data1,no_matrik as data4,"" as data3,'
        . 'ISNULL(nokp,"") as data2,ISNULL(email,"") as data5,'
        . 'nama_ptj as data6,program as data7,"" as data8,"" as data9,'
        . '"" as data10,"" as data11,"" as data12,'
        . '"Pelajar" as ext_data_source_category '
        . 'FROM v210_sso_student_aktif';
    $statement = oneid_readonly_odbc_exec($connection, $sql);
    if ($statement === false) {
        throw new RuntimeException('EXTERNAL_STUDENT_QUERY_FAILED');
    }

    $externalRows = [];
    while ($row = odbc_fetch_array($statement)) {
        $externalRows[] = ExternalRowNormalizer::normalize($row);
    }
} finally {
    odbc_close($connection);
}

$pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$users = $pdo->query(
    'SELECT u_id,data2,avail_status,account_source,sync_protected
     FROM user_tbl'
)->fetchAll(PDO::FETCH_ASSOC);
$memberships = $pdo->query(
    'SELECT u_id,source_code,external_user_id
     FROM user_external_identity'
)->fetchAll(PDO::FETCH_ASSOC);

$result = (new ProvenanceBackfillPreview())->preview(
    $proposedSourceCode,
    $externalRows,
    $users,
    $memberships
);

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
exit(0);
