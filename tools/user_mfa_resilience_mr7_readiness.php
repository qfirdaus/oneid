<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

require_once dirname(__DIR__) . '/lib/config.php';

$mode = $argv[1] ?? '--release';
if (!in_array($mode, ['--release', '--activation'], true)) {
    fwrite(STDERR, "Usage: php tools/user_mfa_resilience_mr7_readiness.php --release|--activation\n");
    exit(2);
}

$environment = strtolower((string) oneid_config('ONEID_ENVIRONMENT', ''));
$pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
$requiredTables = [
    'maintenance_mfa_policy',
    'maintenance_mfa_policy_history',
    'user_mfa_policy_change_requests',
    'user_mfa_policy_change_approvals',
    'user_mfa_policy_transition_runs',
];
$placeholders = implode(',', array_fill(0, count($requiredTables), '?'));
$tables = $pdo->prepare("SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ({$placeholders})");
$tables->execute($requiredTables);
$schemaReady = count($tables->fetchAll(PDO::FETCH_COLUMN)) === count($requiredTables);
$policy = $pdo->query('SELECT policy_mode,email_enabled,totp_enabled,configuration_version FROM user_login_mfa_policy WHERE singleton_key=1')->fetch();
$maintenance = $schemaReady ? $pdo->query('SELECT policy_mode,email_enabled,configuration_version FROM maintenance_mfa_policy WHERE singleton_key=1')->fetch() : false;
$openRequests = $schemaReady ? (int) $pdo->query("SELECT COUNT(*) FROM user_mfa_policy_change_requests WHERE environment=" . $pdo->quote($environment) . " AND request_status IN ('PENDING_APPROVAL','APPROVED','ACTIVE')")->fetchColumn() : -1;
$runtimeUser = strtoupper((string) oneid_config('ONEID_USER_MFA_MODE', 'OFF'));
$runtimeAuthorized = filter_var(oneid_config('ONEID_USER_MFA_ACTIVATION_AUTHORIZED', false), FILTER_VALIDATE_BOOLEAN);
$maintenanceEnabled = filter_var(oneid_config('ONEID_MAINTENANCE_MFA_ENABLED', false), FILTER_VALIDATE_BOOLEAN);
$maintenanceAuthorized = filter_var(oneid_config('ONEID_MAINTENANCE_MFA_ACTIVATION_AUTHORIZED', false), FILTER_VALIDATE_BOOLEAN);
$workerEnabled = filter_var(oneid_config('ONEID_USER_MFA_LIFECYCLE_WORKER_ENABLED', false), FILTER_VALIDATE_BOOLEAN);

$checks = [
    'known_environment' => in_array($environment, ['local','staging','production'], true),
    'schema_complete' => $schemaReady,
    'user_policy_safe_baseline' => is_array($policy) && $policy['policy_mode'] === 'ENFORCED' && (int) $policy['email_enabled'] === 1,
    'maintenance_policy_enforced' => is_array($maintenance) && $maintenance['policy_mode'] === 'ENFORCED' && (int) $maintenance['email_enabled'] === 1,
    'no_open_transition' => $openRequests === 0,
    'runtime_user_ceiling_enforced' => $runtimeUser === 'ENFORCED' && $runtimeAuthorized,
    'runtime_maintenance_independent' => $maintenanceEnabled && $maintenanceAuthorized,
];
if ($mode === '--activation') {
    $checks['lifecycle_worker_enabled'] = $workerEnabled;
}

$failed = 0;
foreach ($checks as $name => $passed) {
    echo ($passed ? 'PASS ' : 'FAIL ') . $name . PHP_EOL;
    if (!$passed) {
        $failed++;
    }
}
printf(
    "RESULT mode=%s environment=%s database=%s policy=%s version=%d open_requests=%d worker=%s failed=%d mutation=0\n",
    ltrim($mode, '-'),
    $environment,
    $database,
    is_array($policy) ? (string) $policy['policy_mode'] : 'UNAVAILABLE',
    is_array($policy) ? (int) $policy['configuration_version'] : 0,
    $openRequests,
    $workerEnabled ? 'enabled' : 'disabled',
    $failed
);
exit($failed === 0 ? 0 : 1);
