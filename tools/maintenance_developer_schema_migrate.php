<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

define('ONEID_MAINTENANCE_BYPASS', true);
require_once dirname(__DIR__) . '/lib/config.php';

$fail = static function (string $reason): never {
    fwrite(STDERR, "BLOCKED {$reason}\n");
    exit(1);
};
$value = static fn(string $key): string => trim((string) oneid_config($key, ''));

if (($argv[1] ?? '') !== '--apply' || count($argv) !== 2) {
    $fail('MAINTENANCE_DEVELOPER_EXPLICIT_APPLY_ARGUMENT_REQUIRED');
}
if ($value('ONEID_ENVIRONMENT') !== 'staging') {
    $fail('MAINTENANCE_DEVELOPER_STAGING_ENVIRONMENT_REQUIRED');
}
if ($value('ONEID_MAINTENANCE_DEVELOPER_ACCESS_ENABLED') !== 'false') {
    $fail('MAINTENANCE_DEVELOPER_FEATURE_MUST_REMAIN_DISABLED');
}
if ($value('ONEID_MAINTENANCE_DEVELOPER_SCHEMA_APPLY_ENABLED') !== 'true') {
    $fail('MAINTENANCE_DEVELOPER_SCHEMA_APPLY_NOT_APPROVED');
}
foreach (['CHANGE', 'BACKUP'] as $kind) {
    $reference = $value('ONEID_MAINTENANCE_DEVELOPER_SCHEMA_' . $kind . '_REFERENCE');
    if (preg_match('/^[A-Z0-9][A-Z0-9._-]{7,127}$/D', $reference) !== 1) {
        $fail('MAINTENANCE_DEVELOPER_' . $kind . '_REFERENCE_INVALID');
    }
}
$startRaw = $value('ONEID_MAINTENANCE_DEVELOPER_SCHEMA_WINDOW_START');
$endRaw = $value('ONEID_MAINTENANCE_DEVELOPER_SCHEMA_WINDOW_END');
$start = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $startRaw);
$end = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $endRaw);
$now = new DateTimeImmutable('now', new DateTimeZone('Asia/Kuala_Lumpur'));
if (!$start instanceof DateTimeImmutable || !$end instanceof DateTimeImmutable
    || $start->format(DateTimeInterface::ATOM) !== $startRaw
    || $end->format(DateTimeInterface::ATOM) !== $endRaw
    || $end <= $start || ($end->getTimestamp() - $start->getTimestamp()) > 7200
    || $now < $start || $now > $end) {
    $fail('MAINTENANCE_DEVELOPER_CHANGE_WINDOW_INVALID');
}

$pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
if ($database !== 'oneiddb') {
    $fail('MAINTENANCE_DEVELOPER_EXACT_DATABASE_REQUIRED');
}
$userRowsBefore = (int) $pdo->query('SELECT COUNT(*) FROM user_tbl')->fetchColumn();
$userDefinitionBefore = (string) $pdo->query('SHOW CREATE TABLE user_tbl')->fetch(PDO::FETCH_ASSOC)['Create Table'];
$existing = (int) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.TABLES
      WHERE TABLE_SCHEMA=DATABASE()
        AND TABLE_NAME IN ('maintenance_developer_access_grants',
                           'maintenance_developer_access_history')"
)->fetchColumn();
if ($existing === 2) {
    echo "PASS maintenance developer schema already installed tables=2\n";
    exit(0);
}
if ($existing !== 0) {
    $fail('MAINTENANCE_DEVELOPER_SCHEMA_PARTIAL_BASELINE');
}

$root = dirname(__DIR__);
$migration = $root . '/docs/migrations/20260904_maintenance_developer_access_up.sql';
$downMigration = $root . '/docs/migrations/20260904_maintenance_developer_access_down.sql';
try {
    $pdo->exec((string) file_get_contents($migration));
} catch (Throwable $exception) {
    // MySQL DDL auto-commits. Best-effort rollback prevents a partial baseline.
    try {
        $pdo->exec((string) file_get_contents($downMigration));
    } catch (Throwable) {
        error_log('Maintenance developer automatic rollback failed');
    }
    $fail('MAINTENANCE_DEVELOPER_MIGRATION_FAILED_ROLLBACK_ATTEMPTED');
}
$installed = (int) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.TABLES
      WHERE TABLE_SCHEMA=DATABASE()
        AND TABLE_NAME IN ('maintenance_developer_access_grants',
                           'maintenance_developer_access_history')"
)->fetchColumn();
if ($installed !== 2) {
    $fail('MAINTENANCE_DEVELOPER_POST_MIGRATION_RECONCILIATION_FAILED');
}
$foreignKeys = (int) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS
      WHERE CONSTRAINT_SCHEMA=DATABASE()
        AND TABLE_NAME IN ('maintenance_developer_access_grants',
                           'maintenance_developer_access_history')"
)->fetchColumn();
$checks = (int) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
      WHERE CONSTRAINT_SCHEMA=DATABASE()
        AND TABLE_NAME IN ('maintenance_developer_access_grants',
                           'maintenance_developer_access_history')
        AND CONSTRAINT_TYPE='CHECK'"
)->fetchColumn();
$userRowsAfter = (int) $pdo->query('SELECT COUNT(*) FROM user_tbl')->fetchColumn();
$userDefinitionAfter = (string) $pdo->query('SHOW CREATE TABLE user_tbl')->fetch(PDO::FETCH_ASSOC)['Create Table'];
if ($foreignKeys !== 6 || $checks !== 10 || $userRowsAfter !== $userRowsBefore
    || !hash_equals(hash('sha256', $userDefinitionBefore), hash('sha256', $userDefinitionAfter))) {
    $fail('MAINTENANCE_DEVELOPER_POST_MIGRATION_INTEGRITY_FAILED');
}
printf(
    "APPLIED database=oneiddb tables=2 foreign_keys=6 checks=10 user_tbl_unchanged=yes feature_enabled=false migration_sha256=%s\n",
    hash_file('sha256', $migration)
);
