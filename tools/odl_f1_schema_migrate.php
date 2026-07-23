<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

require_once dirname(__DIR__) . '/lib/config.php';

$mode = $argv[1] ?? '--check';
if (!in_array($mode, ['--check', '--apply', '--rollback'], true)) {
    fwrite(
        STDERR,
        "Usage: php tools/odl_f1_schema_migrate.php [--check|--apply|--rollback]\n"
    );
    exit(2);
}

$root = dirname(__DIR__);
$pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$userContract = $pdo->query(
    "SELECT CONCAT_WS('|', t.ENGINE, t.TABLE_COLLATION, c.COLUMN_TYPE,
                      c.IS_NULLABLE, c.COLLATION_NAME)
     FROM information_schema.TABLES t
     JOIN information_schema.COLUMNS c
       ON c.TABLE_SCHEMA=t.TABLE_SCHEMA AND c.TABLE_NAME=t.TABLE_NAME
     WHERE t.TABLE_SCHEMA=DATABASE()
       AND t.TABLE_NAME='user_tbl'
       AND c.COLUMN_NAME='u_id'"
)->fetchColumn();
if ($userContract !== 'InnoDB|utf8mb4_0900_ai_ci|varchar(20)|NO|utf8mb4_0900_ai_ci') {
    fwrite(STDERR, "FAIL ODL_F1_LIVE_USER_SCHEMA_INCOMPATIBLE\n");
    exit(1);
}

$tableExists = static function (PDO $pdo, string $table): bool {
    $query = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table_name'
    );
    $query->execute([':table_name' => $table]);

    return (int) $query->fetchColumn() === 1;
};

$sourceTable = $tableExists($pdo, 'external_source');
$identityTable = $tableExists($pdo, 'user_external_identity');
$installedTables = (int) $sourceTable + (int) $identityTable;
$sourceState = null;
$memberships = null;
if ($sourceTable) {
    $query = $pdo->prepare(
        'SELECT CONCAT_WS("|", source_family, lifecycle_state,
                          is_required, avail_status)
         FROM external_source WHERE source_code=:source_code'
    );
    $query->execute([':source_code' => 'STUDENT_ODL_PG']);
    $value = $query->fetchColumn();
    $sourceState = $value === false ? null : (string) $value;
}
if ($identityTable) {
    $memberships = (int) $pdo->query(
        'SELECT COUNT(*) FROM user_external_identity'
    )->fetchColumn();
}

$complete = $sourceTable
    && $identityTable
    && $sourceState === 'student|dormant|0|1'
    && $memberships !== null;
$absent = $installedTables === 0;

printf(
    "ODL_F1_SCHEMA tables=%d/2 source=%s memberships=%s mode=%s\n",
    $installedTables,
    $sourceState ?? 'absent',
    $memberships === null ? 'n/a' : (string) $memberships,
    $mode
);

if ($mode === '--check') {
    exit($complete ? 0 : 1);
}

$changeId = getenv('ONEID_ODL_F1_CHANGE_ID') ?: '';
if ($changeId !== 'ONEID-ODL-F1-20260723-01') {
    fwrite(STDERR, "FAIL ODL_F1_CHANGE_ID_REQUIRED\n");
    exit(1);
}

if ($mode === '--apply') {
    if ($complete) {
        echo "PASS ODL F1 schema already installed and dormant\n";
        exit(0);
    }
    if (!$absent) {
        fwrite(STDERR, "FAIL ODL_F1_PARTIAL_SCHEMA_REQUIRES_RECONCILIATION\n");
        exit(1);
    }

    $up = (string) file_get_contents(
        $root . '/docs/migrations/20260723_odl_f1_provenance_up.sql'
    );
    if (trim($up) === '') {
        fwrite(STDERR, "FAIL ODL_F1_UP_MIGRATION_EMPTY\n");
        exit(1);
    }
    $pdo->exec($up);

    echo "PASS ODL F1 schema installed source=STUDENT_ODL_PG lifecycle=dormant memberships=0\n";
    exit(0);
}

if (!$complete) {
    fwrite(STDERR, "FAIL ODL_F1_ROLLBACK_REQUIRES_COMPLETE_DORMANT_SCHEMA\n");
    exit(1);
}

$nonDormant = (int) $pdo->query(
    "SELECT COUNT(*) FROM external_source
     WHERE lifecycle_state <> 'dormant'"
)->fetchColumn();
$sourceCount = (int) $pdo->query(
    'SELECT COUNT(*) FROM external_source'
)->fetchColumn();
if ($memberships !== 0 || $nonDormant !== 0 || $sourceCount !== 1) {
    fwrite(STDERR, "FAIL ODL_F1_ROLLBACK_GUARD_REJECTED\n");
    exit(1);
}

$down = (string) file_get_contents(
    $root . '/docs/migrations/20260723_odl_f1_provenance_down.sql'
);
if (trim($down) === '') {
    fwrite(STDERR, "FAIL ODL_F1_DOWN_MIGRATION_EMPTY\n");
    exit(1);
}
$pdo->exec($down);

echo "PASS ODL F1 schema rolled back memberships=0 sources=1 lifecycle=dormant\n";
