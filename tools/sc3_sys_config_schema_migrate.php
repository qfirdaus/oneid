<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/config.php';

$mode = $argv[1] ?? '--check';
if (!in_array($mode, ['--check', '--apply'], true)) {
    fwrite(STDERR, "Usage: php tools/sc3_sys_config_schema_migrate.php [--check|--apply]\n");
    exit(2);
}
$pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$rowCount = (int) $pdo->query('SELECT COUNT(*) FROM sys_config')->fetchColumn();
$column = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sys_config' AND COLUMN_NAME='singleton_key'")->fetchColumn();
$invalid = $column === 1 ? (int) $pdo->query('SELECT COUNT(*) FROM sys_config WHERE singleton_key <> 1')->fetchColumn() : 0;
$check = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='sys_config' AND CONSTRAINT_NAME='chk_sys_config_singleton' AND CONSTRAINT_TYPE='CHECK'")->fetchColumn();
$unique = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sys_config' AND INDEX_NAME='uq_sys_config_singleton' AND NON_UNIQUE=0")->fetchColumn();
printf("SC3 rows=%d invalid_singleton=%d column=%s check=%s unique=%s mode=%s\n", $rowCount, $invalid, $column ? 'yes' : 'no', $check ? 'yes' : 'no', $unique ? 'yes' : 'no', $mode);
if ($rowCount !== 1 || $invalid !== 0) {
    fwrite(STDERR, "FAIL: sys_config must contain exactly one valid baseline row.\n");
    exit(1);
}
if ($mode === '--check') {
    exit($column === 1 && $check === 1 && $unique === 1 ? 0 : 1);
}
if ($column === 0) $pdo->exec('ALTER TABLE sys_config ADD COLUMN singleton_key TINYINT NOT NULL DEFAULT 1 AFTER id');
if ($check === 0) $pdo->exec('ALTER TABLE sys_config ADD CONSTRAINT chk_sys_config_singleton CHECK (singleton_key = 1)');
if ($unique === 0) $pdo->exec('ALTER TABLE sys_config ADD CONSTRAINT uq_sys_config_singleton UNIQUE (singleton_key)');
echo "PASS: SC3 singleton constraints applied.\n";
