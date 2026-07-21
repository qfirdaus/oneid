<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

require_once dirname(__DIR__) . '/lib/config.php';
require_once dirname(__DIR__) . '/app/Auth/TotpKeyring.php';

use OneId\App\Auth\TotpKeyring;

$mode = $argv[1] ?? '--check';
if (!in_array($mode, ['--check', '--apply'], true)) {
    fwrite(STDERR, "Usage: php tools/f7_1_schema_migrate.php [--check|--apply]\n");
    exit(2);
}

$pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$tables = ['admin_mfa_factors', 'admin_mfa_preferences', 'admin_step_up_challenges', 'admin_step_up_grants'];

$columnExists = (int) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sys_config'
       AND COLUMN_NAME='admin_2fa_enabled'"
)->fetchColumn() === 1;
$existingTables = [];
foreach ($tables as $table) {
    $query = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table_name'
    );
    $query->execute([':table_name' => $table]);
    $existingTables[$table] = (int) $query->fetchColumn() === 1;
}

$complete = $columnExists && !in_array(false, $existingTables, true);
printf(
    "F7_1_SCHEMA config_column=%s tables=%d/%d mode=%s\n",
    $columnExists ? 'yes' : 'no',
    count(array_filter($existingTables)),
    count($tables),
    $mode
);

if ($mode === '--check') {
    exit($complete ? 0 : 1);
}

if ($complete) {
    $enabled = (int) $pdo->query('SELECT admin_2fa_enabled FROM sys_config WHERE singleton_key=1')->fetchColumn();
    printf("PASS F7.1 schema already installed admin_2fa_enabled=%d\n", $enabled);
    exit($enabled === 0 ? 0 : 1);
}

$changeId = getenv('ONEID_F7_CHANGE_ID') ?: '';
$backupEvidence = getenv('ONEID_F7_BACKUP_EVIDENCE') ?: '';
$keyringPath = getenv('ONEID_TOTP_KEYRING_FILE') ?: '/etc/oneid/keys/admin-totp-keyring.php';
if ($changeId !== 'ONEID-F7-2FA-20260720-01') {
    fwrite(STDERR, "FAIL F7_CHANGE_ID_REQUIRED\n");
    exit(1);
}
if ($backupEvidence === '' || !is_file($backupEvidence) || !is_readable($backupEvidence)) {
    fwrite(STDERR, "FAIL F7_BACKUP_EVIDENCE_REQUIRED\n");
    exit(1);
}
try {
    TotpKeyring::fromFile($keyringPath);
} catch (Throwable) {
    fwrite(STDERR, "FAIL F7_TOTP_KEYRING_NOT_READY\n");
    exit(1);
}

if ($columnExists || count(array_filter($existingTables)) !== 0) {
    fwrite(STDERR, "FAIL F7_PARTIAL_SCHEMA_REQUIRES_MANUAL_RECONCILIATION\n");
    exit(1);
}

$migration = (string) file_get_contents(
    dirname(__DIR__) . '/docs/migrations/20260720_f7_1_admin_step_up_foundation_up.sql'
);
if (trim($migration) === '') {
    fwrite(STDERR, "FAIL F7_MIGRATION_EMPTY\n");
    exit(1);
}

$pdo->exec($migration);
$enabled = (int) $pdo->query('SELECT admin_2fa_enabled FROM sys_config WHERE singleton_key=1')->fetchColumn();
printf("PASS F7.1 schema installed admin_2fa_enabled=%d\n", $enabled);
exit($enabled === 0 ? 0 : 1);
