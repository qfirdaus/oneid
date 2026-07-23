<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

require_once dirname(__DIR__) . '/lib/config.php';

$root = dirname(__DIR__);
$suffix = strtolower(bin2hex(random_bytes(6)));
$database = 'oneid_odl_f1_rehearsal_' . $suffix;
if (preg_match('/\Aoneid_odl_f1_rehearsal_[a-f0-9]{12}\z/', $database) !== 1) {
    fwrite(STDERR, "FAIL invalid rehearsal database name\n");
    exit(1);
}

$pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$quotedDatabase = '`' . $database . '`';
$created = false;

try {
    $pdo->exec(
        "CREATE DATABASE {$quotedDatabase}
         CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci"
    );
    $created = true;
    $pdo->exec("USE {$quotedDatabase}");
    $pdo->exec(
        "CREATE TABLE user_tbl (
            u_id VARCHAR(20) NOT NULL,
            avail_status INT NOT NULL,
            account_source VARCHAR(16) NOT NULL DEFAULT 'external',
            sync_protected TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (u_id)
        ) ENGINE=InnoDB
          DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"
    );
    $pdo->exec(
        "INSERT INTO user_tbl
            (u_id, avail_status, account_source, sync_protected)
         VALUES ('F1-UNCHANGED', 1, 'external', 0)"
    );

    $beforeUser = $pdo->query(
        "SELECT CONCAT_WS('|', u_id, avail_status, account_source, sync_protected)
         FROM user_tbl WHERE u_id='F1-UNCHANGED'"
    )->fetchColumn();
    $up = (string) file_get_contents(
        $root . '/docs/migrations/20260723_odl_f1_provenance_up.sql'
    );
    $pdo->exec($up);

    $tables = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA=DATABASE()
           AND TABLE_NAME IN ('external_source','user_external_identity')"
    )->fetchColumn();
    $foreignKeys = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA=DATABASE()
           AND TABLE_NAME='user_external_identity'"
    )->fetchColumn();
    $source = $pdo->query(
        "SELECT CONCAT_WS('|', source_code, source_family, lifecycle_state,
                          is_required, avail_status)
         FROM external_source WHERE source_code='STUDENT_ODL_PG'"
    )->fetchColumn();
    $memberships = (int) $pdo->query(
        'SELECT COUNT(*) FROM user_external_identity'
    )->fetchColumn();
    $afterUser = $pdo->query(
        "SELECT CONCAT_WS('|', u_id, avail_status, account_source, sync_protected)
         FROM user_tbl WHERE u_id='F1-UNCHANGED'"
    )->fetchColumn();

    printf(
        "PASS forward tables=%d foreign_keys=%d source=%s memberships=%d user_unchanged=%s\n",
        $tables,
        $foreignKeys,
        (string) $source,
        $memberships,
        hash_equals((string) $beforeUser, (string) $afterUser) ? 'yes' : 'no'
    );
    if (
        $tables !== 2
        || $foreignKeys !== 2
        || $source !== 'STUDENT_ODL_PG|student|dormant|0|1'
        || $memberships !== 0
        || !hash_equals((string) $beforeUser, (string) $afterUser)
    ) {
        throw new RuntimeException('ODL_F1_FORWARD_RECONCILIATION_FAILED');
    }

    $down = (string) file_get_contents(
        $root . '/docs/migrations/20260723_odl_f1_provenance_down.sql'
    );
    $pdo->exec($down);
    $tablesAfter = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA=DATABASE()
           AND TABLE_NAME IN ('external_source','user_external_identity')"
    )->fetchColumn();
    $userAfterRollback = $pdo->query(
        "SELECT CONCAT_WS('|', u_id, avail_status, account_source, sync_protected)
         FROM user_tbl WHERE u_id='F1-UNCHANGED'"
    )->fetchColumn();

    printf(
        "PASS rollback tables=%d user_unchanged=%s\n",
        $tablesAfter,
        hash_equals((string) $beforeUser, (string) $userAfterRollback)
            ? 'yes'
            : 'no'
    );
    if (
        $tablesAfter !== 0
        || !hash_equals((string) $beforeUser, (string) $userAfterRollback)
    ) {
        throw new RuntimeException('ODL_F1_ROLLBACK_RECONCILIATION_FAILED');
    }
} finally {
    if ($created) {
        $pdo->exec('USE information_schema');
        $pdo->exec("DROP DATABASE {$quotedDatabase}");
    }
}

echo "RESULT checks=2 failed=0 user_mutations=0 rehearsal_database_removed=yes\n";
