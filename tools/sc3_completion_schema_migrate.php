<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

require_once dirname(__DIR__) . '/lib/config.php';

$mode = $argv[1] ?? '--check';
if (!in_array($mode, ['--check', '--apply'], true)) {
    fwrite(STDERR, "Usage: php tools/sc3_completion_schema_migrate.php [--check|--apply]\n");
    exit(2);
}

$pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$versionColumnExists = (int) $pdo->query(
    "SELECT COUNT(*)
     FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'sys_config'
       AND COLUMN_NAME = 'configuration_version'"
)->fetchColumn() === 1;

$historyTableExists = (int) $pdo->query(
    "SELECT COUNT(*)
     FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'configuration_change_history'"
)->fetchColumn() === 1;

printf(
    "SC3_COMPLETION version_column=%s history_table=%s mode=%s\n",
    $versionColumnExists ? 'yes' : 'no',
    $historyTableExists ? 'yes' : 'no',
    $mode
);

if ($mode === '--check') {
    exit($versionColumnExists && $historyTableExists ? 0 : 1);
}

if (!$versionColumnExists) {
    $pdo->exec(
        'ALTER TABLE sys_config
         ADD COLUMN configuration_version BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER singleton_key'
    );
}

if (!$historyTableExists) {
    $pdo->exec(
        "CREATE TABLE configuration_change_history (
            history_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            configuration_version_before BIGINT UNSIGNED NULL,
            configuration_version_after BIGINT UNSIGNED NULL,
            actor_id VARCHAR(20) NOT NULL,
            ip_address VARCHAR(50) NOT NULL,
            action_name VARCHAR(64) NOT NULL,
            outcome VARCHAR(16) NOT NULL,
            reason_code VARCHAR(64) NOT NULL,
            change_reason VARCHAR(500) NULL,
            before_json JSON NULL,
            after_json JSON NULL,
            correlation_id CHAR(16) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (history_id),
            UNIQUE KEY uq_configuration_history_correlation (correlation_id),
            KEY idx_configuration_history_created (created_at, history_id),
            KEY idx_configuration_history_outcome (outcome, created_at),
            CONSTRAINT chk_configuration_history_outcome
                CHECK (outcome IN ('SUCCESS', 'REJECTED'))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"
    );
}

echo "PASS: SC3 completion schema applied.\n";
