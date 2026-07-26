<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

require_once dirname(__DIR__) . '/lib/config.php';
require_once dirname(__DIR__) . '/lib/secrets.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

use OneId\App\Auth\MyDigitalId\MyDigitalIdIdentityProtector;

$applyRequested = ($argv[1] ?? '') === '--apply' && count($argv) === 2;
$fail = static function (string $reason): never {
    fwrite(STDERR, "BLOCKED {$reason}\n");
    exit(1);
};
$value = static fn(string $key): string => trim((string) oneid_config($key, ''));

if (!$applyRequested) {
    $fail('MYDID_EXPLICIT_APPLY_ARGUMENT_REQUIRED');
}
if ($value('ONEID_ENVIRONMENT') !== 'staging') {
    $fail('MYDID_STAGING_ENVIRONMENT_REQUIRED');
}
if ($value('ONEID_MYDID_ENABLED') !== 'false') {
    $fail('MYDID_AUTH_FEATURE_MUST_REMAIN_DISABLED');
}
if ($value('ONEID_MYDID_SCHEMA_APPLY_ENABLED') !== 'true') {
    $fail('MYDID_SCHEMA_APPLY_NOT_APPROVED');
}

$changeReference = $value('ONEID_MYDID_SCHEMA_CHANGE_REFERENCE');
$backupReference = $value('ONEID_MYDID_SCHEMA_BACKUP_REFERENCE');
$retentionReference = $value('ONEID_MYDID_AUDIT_RETENTION_REFERENCE');
foreach ([
    'CHANGE' => $changeReference,
    'BACKUP' => $backupReference,
    'RETENTION' => $retentionReference,
] as $label => $reference) {
    if (preg_match('/^[A-Z0-9][A-Z0-9._-]{7,127}$/D', $reference) !== 1) {
        $fail('MYDID_' . $label . '_REFERENCE_INVALID');
    }
}

$windowStartRaw = $value('ONEID_MYDID_SCHEMA_WINDOW_START');
$windowEndRaw = $value('ONEID_MYDID_SCHEMA_WINDOW_END');
$windowStart = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $windowStartRaw);
$windowEnd = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $windowEndRaw);
if (
    !$windowStart instanceof DateTimeImmutable
    || !$windowEnd instanceof DateTimeImmutable
    || $windowStart->format(DateTimeInterface::ATOM) !== $windowStartRaw
    || $windowEnd->format(DateTimeInterface::ATOM) !== $windowEndRaw
) {
    $fail('MYDID_CHANGE_WINDOW_INVALID');
}
$now = new DateTimeImmutable('now', new DateTimeZone('Asia/Kuala_Lumpur'));
if ($windowEnd <= $windowStart || $now < $windowStart || $now > $windowEnd) {
    $fail('MYDID_OUTSIDE_APPROVED_CHANGE_WINDOW');
}
if (($windowEnd->getTimestamp() - $windowStart->getTimestamp()) > 7200) {
    $fail('MYDID_CHANGE_WINDOW_TOO_WIDE');
}

try {
    MyDigitalIdIdentityProtector::fromRuntime();
} catch (Throwable) {
    $fail('MYDID_HMAC_KEY_NOT_READY');
}

$root = dirname(__DIR__);
$upPath = $root . '/docs/migrations/20260726_mydigitalid_f2_identity_audit_up.sql';
$downPath = $root . '/docs/migrations/20260726_mydigitalid_f2_identity_audit_down.sql';
$up = (string) file_get_contents($upPath);
$down = (string) file_get_contents($downPath);
if ($up === '' || $down === '') {
    $fail('MYDID_MIGRATION_FILE_MISSING');
}

$pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
if ($database !== 'oneiddb') {
    $fail('MYDID_DATABASE_TARGET_INVALID');
}
$existing = (int) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.TABLES
     WHERE TABLE_SCHEMA=DATABASE()
       AND TABLE_NAME IN ('user_federated_identity','federated_auth_event')"
)->fetchColumn();
if ($existing !== 0) {
    $fail('MYDID_SCHEMA_NOT_AT_EXPECTED_BASELINE');
}

$userBefore = (int) $pdo->query('SELECT COUNT(*) FROM user_tbl')->fetchColumn();
$userStructureBefore = (string) $pdo->query(
    "SELECT SHA2(GROUP_CONCAT(
        CONCAT_WS('|',COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,EXTRA)
        ORDER BY ORDINAL_POSITION SEPARATOR '\n'
     ),256)
     FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_tbl'"
)->fetchColumn();

try {
    $pdo->exec($up);
    $tables = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA=DATABASE()
           AND TABLE_NAME IN ('user_federated_identity','federated_auth_event')"
    )->fetchColumn();
    $foreignKeys = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA=DATABASE()
           AND TABLE_NAME IN ('user_federated_identity','federated_auth_event')"
    )->fetchColumn();
    $checks = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA=DATABASE()
           AND CONSTRAINT_TYPE='CHECK'
           AND TABLE_NAME IN ('user_federated_identity','federated_auth_event')"
    )->fetchColumn();
    $forbidden = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA=DATABASE()
           AND TABLE_NAME IN ('user_federated_identity','federated_auth_event')
           AND COLUMN_NAME IN ('nric','nama','name','access_token','refresh_token',
                               'id_token','authorization_code','client_secret')"
    )->fetchColumn();
    $userAfter = (int) $pdo->query('SELECT COUNT(*) FROM user_tbl')->fetchColumn();
    $userStructureAfter = (string) $pdo->query(
        "SELECT SHA2(GROUP_CONCAT(
            CONCAT_WS('|',COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,EXTRA)
            ORDER BY ORDINAL_POSITION SEPARATOR '\n'
         ),256)
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_tbl'"
    )->fetchColumn();

    if (
        $tables !== 2
        || $foreignKeys !== 3
        || $checks !== 3
        || $forbidden !== 0
        || $userBefore !== $userAfter
        || !hash_equals($userStructureBefore, $userStructureAfter)
    ) {
        throw new RuntimeException('MYDID_POST_MIGRATION_RECONCILIATION_FAILED');
    }
} catch (Throwable $exception) {
    try {
        $pdo->exec($down);
    } catch (Throwable) {
        // The operator must reconcile manually if compensating rollback fails.
    }
    $fail('MYDID_MIGRATION_FAILED');
}

printf(
    "APPLIED database=%s tables=2 foreign_keys=3 checks=3 forbidden_columns=0 "
    . "user_rows=%d user_structure_unchanged=yes migration_sha256=%s "
    . "change_reference=%s backup_reference=%s retention_reference=%s\n",
    $database,
    $userAfter,
    hash_file('sha256', $upPath),
    $changeReference,
    $backupReference,
    $retentionReference
);
