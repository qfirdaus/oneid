<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { exit(2); }
define('ONEID_MAINTENANCE_BYPASS', true);
require_once dirname(__DIR__) . '/lib/config.php';

$pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$value = static fn(string $key): string => trim((string) oneid_config($key, ''));
$database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
$mdTables = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME IN ('maintenance_developer_access_grants','maintenance_developer_access_history')")->fetchColumn();
$mfaTables = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME IN ('user_login_mfa_policy','user_login_mfa_policy_history','user_mfa_factors',
    'user_mfa_preferences','user_login_mfa_transactions','user_login_mfa_challenges')")->fetchColumn();
$leftovers = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.SCHEMATA
    WHERE SCHEMA_NAME LIKE 'oneid_md2_%' OR SCHEMA_NAME LIKE 'oneid_md3_%' OR SCHEMA_NAME LIKE 'oneid_md8_%'")->fetchColumn();
$checks = [
    'environment_is_staging' => $value('ONEID_ENVIRONMENT') === 'staging',
    'exact_database_is_oneiddb' => $database === 'oneiddb',
    'migration_files_present' => is_file(dirname(__DIR__) . '/docs/migrations/20260904_maintenance_developer_access_up.sql')
        && is_file(dirname(__DIR__) . '/docs/migrations/20260904_maintenance_developer_access_down.sql'),
    'user_mfa_schema_ready' => $mfaTables === 6,
    'user_mfa_enforced' => $value('ONEID_USER_MFA_MODE') === 'ENFORCED',
    'user_mfa_activation_authorized' => filter_var($value('ONEID_USER_MFA_ACTIVATION_AUTHORIZED'), FILTER_VALIDATE_BOOLEAN),
    'user_mfa_email_enabled' => filter_var($value('ONEID_USER_MFA_EMAIL_ENABLED'), FILTER_VALIDATE_BOOLEAN),
    'no_rehearsal_database_leftovers' => $leftovers === 0,
];
foreach ($checks as $name => $passed) { printf("%s %s\n", $passed ? 'PASS' : 'BLOCKED', $name); }
$featureEnabled = filter_var($value('ONEID_MAINTENANCE_DEVELOPER_ACCESS_ENABLED'), FILTER_VALIDATE_BOOLEAN);
$schemaReady = $mdTables === 2;
$activationReady = !in_array(false, $checks, true) && $schemaReady && !$featureEnabled;
printf("INFO maintenance_schema_tables=%d/2 feature_enabled=%s migration_sha256=%s\n", $mdTables,
    $featureEnabled ? 'true' : 'false', hash_file('sha256', dirname(__DIR__) . '/docs/migrations/20260904_maintenance_developer_access_up.sql'));
printf("RESULT snapshot_complete=yes code_prerequisites=%s activation_ready=%s live_mutations=0\n",
    in_array(false, $checks, true) ? 'blocked' : 'ready', $activationReady ? 'yes' : 'no');
// Snapshot completion succeeds. activation_ready is deliberately reported separately.
exit(0);
