<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

define('ONEID_MAINTENANCE_BYPASS', true);
require_once dirname(__DIR__) . '/lib/config.php';

$mode = $argv[1] ?? '--check';
$backupArgument = '';
$changeReference = '';
foreach (array_slice($argv, 2) as $argument) {
    if (str_starts_with($argument, '--backup-evidence=')) {
        $backupArgument = substr($argument, strlen('--backup-evidence='));
    } elseif (str_starts_with($argument, '--change-reference=')) {
        $changeReference = substr($argument, strlen('--change-reference='));
    }
}
if (!in_array($mode, ['--check', '--apply'], true)) {
    fwrite(STDERR, "Usage: php tools/user_mfa_resilience_mr2_schema_migrate.php --check|--apply [--backup-evidence=FILE --change-reference=ID]\n");
    exit(2);
}

$pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
$environment = (string) oneid_config('ONEID_ENVIRONMENT', '');
$tables = [
    'maintenance_mfa_policy',
    'maintenance_mfa_policy_history',
    'user_mfa_policy_change_requests',
    'user_mfa_policy_change_approvals',
    'user_mfa_policy_transition_runs',
];
$placeholders = implode(',', array_fill(0, count($tables), '?'));
$tableStatement = $pdo->prepare(
    "SELECT table_name FROM information_schema.tables
      WHERE table_schema=DATABASE() AND table_name IN ({$placeholders}) ORDER BY table_name"
);
$tableStatement->execute($tables);
$present = $tableStatement->fetchAll(PDO::FETCH_COLUMN);
$checkClause = (string) $pdo->query(
    "SELECT CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS
      WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='chk_user_mfa_policy_mode'"
)->fetchColumn();
$installed = count($present) === count($tables)
    && str_contains($checkClause, 'EMERGENCY_BYPASS');
printf(
    "MR2_SCHEMA environment=%s database=%s tables=%d/5 emergency_mode=%s mode=%s\n",
    $environment,
    $database,
    count($present),
    str_contains($checkClause, 'EMERGENCY_BYPASS') ? 'yes' : 'no',
    $mode
);
if ($mode === '--check') {
    exit($installed ? 0 : 1);
}
if ($environment !== 'staging' || $database !== 'oneiddb') {
    fwrite(STDERR, "BLOCKED exact staging target required\n");
    exit(1);
}
if ($installed) {
    echo "PASS MR2 schema already installed\n";
    exit(0);
}
if ($present !== [] || str_contains($checkClause, 'EMERGENCY_BYPASS')) {
    fwrite(STDERR, "BLOCKED partial MR2 schema requires reconciliation\n");
    exit(1);
}
if (preg_match('/\AONEID-MR2-STAGING-[0-9]{8}-[0-9]{2}\z/', $changeReference) !== 1) {
    fwrite(STDERR, "BLOCKED valid MR2 staging change reference required\n");
    exit(1);
}
$evidence = is_file($backupArgument) ? parse_ini_file($backupArgument, false, INI_SCANNER_RAW) : false;
$backupFile = is_array($evidence) ? (string) ($evidence['backup_file'] ?? '') : '';
$backupHash = is_array($evidence) ? (string) ($evidence['backup_sha256'] ?? '') : '';
if (!is_array($evidence)
    || ($evidence['source_database'] ?? '') !== 'oneiddb'
    || ($evidence['restore_completed'] ?? '') !== 'yes'
    || ($evidence['exact_row_count_reconciliation'] ?? '') !== 'pass'
    || ($evidence['restore_target_dropped'] ?? '') !== 'yes'
    || !is_file($backupFile)
    || !hash_equals($backupHash, (string) hash_file('sha256', $backupFile))
) {
    fwrite(STDERR, "BLOCKED verified staging backup evidence required\n");
    exit(1);
}

$policyBefore = $pdo->query(
    'SELECT policy_mode,email_enabled,totp_enabled,configuration_version
       FROM user_login_mfa_policy WHERE singleton_key=1'
)->fetch();
$split = static fn(string $sql): array => array_values(array_filter(
    array_map('trim', preg_split('/;\s*(?:\R|$)/', $sql) ?: []),
    static fn(string $statement): bool => $statement !== ''
));
$up = (string) file_get_contents(dirname(__DIR__) . '/docs/migrations/20260907_user_mfa_resilience_mr2_up.sql');
$down = (string) file_get_contents(dirname(__DIR__) . '/docs/migrations/20260907_user_mfa_resilience_mr2_down.sql');
try {
    foreach ($split($up) as $statement) {
        $pdo->exec($statement);
    }
} catch (Throwable $exception) {
    foreach ($split($down) as $statement) {
        try {
            $pdo->exec($statement);
        } catch (Throwable) {
        }
    }
    fwrite(STDERR, 'FAIL MR2 apply compensated correlation=' . bin2hex(random_bytes(8)) . "\n");
    exit(1);
}

$policyAfter = $pdo->query(
    'SELECT policy_mode,email_enabled,totp_enabled,configuration_version
       FROM user_login_mfa_policy WHERE singleton_key=1'
)->fetch();
$maintenance = $pdo->query(
    'SELECT policy_mode,email_enabled,totp_enabled,configuration_version
       FROM maintenance_mfa_policy WHERE singleton_key=1'
)->fetch();
$tableStatement->execute($tables);
$present = $tableStatement->fetchAll(PDO::FETCH_COLUMN);
$verified = $policyBefore === $policyAfter
    && count($present) === count($tables)
    && is_array($maintenance)
    && $maintenance['policy_mode'] === 'ENFORCED'
    && (int) $maintenance['email_enabled'] === 1
    && (int) $maintenance['configuration_version'] === 1;
if (!$verified) {
    fwrite(STDERR, "FAIL MR2 post-migration reconciliation required\n");
    exit(1);
}
printf(
    "PASS MR2 schema installed dormant=yes user_policy_unchanged=yes maintenance_mfa=ENFORCED change_reference=%s backup=%s\n",
    $changeReference,
    (string) ($evidence['backup_reference'] ?? '')
);
