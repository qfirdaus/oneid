<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}
require_once dirname(__DIR__) . '/lib/config.php';

$mode = $argv[1] ?? '--check';
if (!in_array($mode, ['--check', '--apply'], true)) {
    fwrite(STDERR, "Usage: php tools/app_production_readiness_schema_migrate.php [--check|--apply]\n");
    exit(2);
}

$pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$query = $pdo->query(
    "SELECT COLUMN_NAME FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sp_list'
       AND COLUMN_NAME IN ('production_ready','production_domain')"
);
$columns = $query->fetchAll(PDO::FETCH_COLUMN);
$constraints = (int) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
     WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='sp_list'
       AND CONSTRAINT_NAME IN ('chk_sp_list_production_ready','chk_sp_list_production_domain')
       AND CONSTRAINT_TYPE='CHECK'"
)->fetchColumn();
printf("APP_PRODUCTION_READINESS columns=%d/2 constraints=%d/2 mode=%s\n", count($columns), $constraints, $mode);

if ($mode === '--check') {
    exit(count($columns) === 2 && $constraints === 2 ? 0 : 1);
}
if (count($columns) === 2 && $constraints === 2) {
    echo "PASS app production readiness schema already installed\n";
    exit(0);
}
if (count($columns) !== 0 && count($columns) !== 2) {
    fwrite(STDERR, "FAIL partial app production readiness schema\n");
    exit(1);
}

if (count($columns) === 0) {
    $pdo->exec((string) file_get_contents(dirname(__DIR__) . '/docs/migrations/20260814_app_production_readiness_up.sql'));
} else {
    $readyConstraint = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='sp_list' AND CONSTRAINT_NAME='chk_sp_list_production_ready' AND CONSTRAINT_TYPE='CHECK'")->fetchColumn();
    $domainConstraint = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='sp_list' AND CONSTRAINT_NAME='chk_sp_list_production_domain' AND CONSTRAINT_TYPE='CHECK'")->fetchColumn();
    if ($readyConstraint === 0) {
        $pdo->exec("ALTER TABLE sp_list ADD CONSTRAINT chk_sp_list_production_ready CHECK (production_ready IN (0, 1))");
    }
    if ($domainConstraint === 0) {
        $pdo->exec("ALTER TABLE sp_list ADD CONSTRAINT chk_sp_list_production_domain CHECK (production_ready = 0 OR production_domain LIKE 'https://%')");
    }
}
$installed = (int) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sp_list'
       AND COLUMN_NAME IN ('production_ready','production_domain')"
)->fetchColumn();
$unexpectedReady = (int) $pdo->query('SELECT COUNT(*) FROM sp_list WHERE production_ready<>0')->fetchColumn();
$installedConstraints = (int) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
     WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='sp_list'
       AND CONSTRAINT_NAME IN ('chk_sp_list_production_ready','chk_sp_list_production_domain')
       AND CONSTRAINT_TYPE='CHECK'"
)->fetchColumn();
if ($installed !== 2 || $installedConstraints !== 2 || $unexpectedReady !== 0) {
    fwrite(STDERR, "FAIL app production readiness schema verification\n");
    exit(1);
}
echo "PASS app production readiness schema installed existing_apps_ready=0\n";
