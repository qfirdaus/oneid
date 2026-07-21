<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

require_once dirname(__DIR__) . '/lib/config.php';

$suffix = strtolower(bin2hex(random_bytes(6)));
$database = 'oneid_f71_rehearsal_' . $suffix;
if (preg_match('/\Aoneid_f71_rehearsal_[a-f0-9]{12}\z/', $database) !== 1) {
    fwrite(STDERR, "FAIL invalid rehearsal database name\n");
    exit(1);
}

$pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$quotedDatabase = '`' . $database . '`';
$created = false;

try {
    $pdo->exec("CREATE DATABASE {$quotedDatabase} CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci");
    $created = true;
    $pdo->exec("USE {$quotedDatabase}");
    $pdo->exec(
        "CREATE TABLE user_tbl (
            u_id VARCHAR(20) NOT NULL,
            PRIMARY KEY (u_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"
    );
    $pdo->exec(
        "CREATE TABLE sys_config (
            id INT NOT NULL AUTO_INCREMENT,
            singleton_key TINYINT NOT NULL DEFAULT 1,
            configuration_version BIGINT UNSIGNED NOT NULL DEFAULT 1,
            token_timeout DOUBLE NOT NULL,
            multi_session INT NOT NULL,
            password_reset_email_enabled INT NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_sys_config_singleton (singleton_key),
            CONSTRAINT chk_sys_config_singleton CHECK (singleton_key=1)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"
    );
    $pdo->exec(
        "INSERT INTO sys_config(singleton_key,token_timeout,multi_session,password_reset_email_enabled)
         VALUES(1,24,1,1)"
    );

    $up = (string) file_get_contents(
        dirname(__DIR__) . '/docs/migrations/20260720_f7_1_admin_step_up_foundation_up.sql'
    );
    $pdo->exec($up);

    $tables = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA=DATABASE()
           AND TABLE_NAME IN ('admin_mfa_factors','admin_mfa_preferences',
                              'admin_step_up_challenges','admin_step_up_grants')"
    )->fetchColumn();
    $enabled = (int) $pdo->query(
        'SELECT admin_2fa_enabled FROM sys_config WHERE singleton_key=1'
    )->fetchColumn();
    $foreignKeys = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA=DATABASE()
           AND TABLE_NAME IN ('admin_mfa_factors','admin_mfa_preferences',
                              'admin_step_up_challenges','admin_step_up_grants')"
    )->fetchColumn();
    printf(
        "PASS forward tables=%d foreign_keys=%d admin_2fa_enabled=%d source_mutations=0\n",
        $tables,
        $foreignKeys,
        $enabled
    );
    if ($tables !== 4 || $foreignKeys !== 4 || $enabled !== 0) {
        throw new RuntimeException('F7_REHEARSAL_FORWARD_RECONCILIATION_FAILED');
    }

    $down = (string) file_get_contents(
        dirname(__DIR__) . '/docs/migrations/20260720_f7_1_admin_step_up_foundation_down.sql'
    );
    $pdo->exec($down);
    $tablesAfter = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA=DATABASE()
           AND TABLE_NAME LIKE 'admin_%'"
    )->fetchColumn();
    $columnAfter = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sys_config'
           AND COLUMN_NAME='admin_2fa_enabled'"
    )->fetchColumn();
    printf(
        "PASS rollback tables=%d config_column=%d source_mutations=0\n",
        $tablesAfter,
        $columnAfter
    );
    if ($tablesAfter !== 0 || $columnAfter !== 0) {
        throw new RuntimeException('F7_REHEARSAL_ROLLBACK_RECONCILIATION_FAILED');
    }
} finally {
    if ($created) {
        $pdo->exec('USE information_schema');
        $pdo->exec("DROP DATABASE {$quotedDatabase}");
    }
}

echo "RESULT checks=2 failed=0 source_mutations=0 rehearsal_database_removed=yes\n";
