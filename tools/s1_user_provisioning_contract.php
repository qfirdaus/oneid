<?php

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(2);
}

$projectRoot = dirname(__DIR__);
$checks = 0;
$failed = 0;
$report = static function (bool $ok, string $item, string $detail = '') use (&$checks, &$failed): void {
    $checks++;
    if (!$ok) $failed++;
    printf("%s %-62s%s\n", $ok ? 'PASS' : 'FAIL', $item, $detail === '' ? '' : ' ' . $detail);
};

$files = [
    'app/User/ManualUserInput.php',
    'app/User/ManualUserCreator.php',
    'lib/Database.php',
    'lib/q_func.php',
    'lib/sync_user_runner.php',
    'index.php',
    'admin/dashboard.php',
    'tests/characterization/s1_manual_user_hardening.php',
    'tests/characterization/s1_sync_provenance_protection.php',
    'tools/s1_provenance_migrate.php',
    'tools/s1_user_provisioning_contract.php',
];
$sources = [];
foreach ($files as $relative) {
    $path = $projectRoot . '/' . $relative;
    $sources[$relative] = is_file($path) ? (string) file_get_contents($path) : '';
    $output = [];
    $exitCode = 1;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1', $output, $exitCode);
    $report($sources[$relative] !== '' && $exitCode === 0, 'source and PHP lint: ' . $relative);
}

$input = $sources['app/User/ManualUserInput.php'];
$creator = $sources['app/User/ManualUserCreator.php'];
$database = $sources['lib/Database.php'];
$qFunc = $sources['lib/q_func.php'];
$sync = $sources['lib/sync_user_runner.php'];
$login = $sources['index.php'];
$dashboard = $sources['admin/dashboard.php'];

$report(str_contains($input, 'FILTER_VALIDATE_EMAIL') && str_contains($input, "self::length(\$userId) > 20"), 'server validates email and DB-sized user ID');
$report(str_contains($input, "'manual:' . \$this->categoryId"), 'manual hash uses canonical transformer namespace');
$report(str_contains($creator, 'PROVENANCE_MIGRATION_REQUIRED'), 'manual create fails closed before provenance migration');
$report(str_contains($creator, 'beginTransaction()') && str_contains($creator, 'commit()') && str_contains($creator, 'safeRollback()'), 'manual create has transaction and rollback');
$report(str_contains($creator, "'manual',") && str_contains($creator, 'syslog_record(23,'), 'manual provenance and audit are persisted together');
$report(str_contains($creator, 'isDuplicateKey(') && str_contains($creator, 'USER_ID_EXISTS'), 'duplicate-key race receives safe response');
$report(!str_contains($creator, "\$exception->getMessage()"), 'manual failure response does not expose exception message');

$report(str_contains($database, 'supportsUserProvenance()'), 'database detects expanding migration safely');
$report(str_contains($database, 'account_source,sync_protected') && str_contains($database, ':account_source,:sync_protected'), 'manual insert persists provenance columns');
$report(str_contains($database, "account_source='external',sync_protected=0"), 'external upsert establishes external provenance');
$report(str_contains($database, "account_source='manual' AND sync_protected=1") && str_contains($database, 'LIMIT 1 FOR UPDATE'), 'external upsert refuses protected manual collision');
$report(str_contains($database, 'isActiveUserCategory('), 'category is validated against active database category');

$report(str_contains($qFunc, 'ManualUserInput::fromPost($_POST)') && str_contains($qFunc, 'ManualUserCreator($operation)'), 'q_func delegates manual creation to hardened service');
$report(!str_contains($qFunc, 'hash("sha256",$_POST[\'add_new_manual_user_name\'])'), 'legacy name-only manual hash removed');
$report(str_contains($dashboard, 'type="email"') && str_contains($dashboard, 'required for OTP'), 'manual email is required in browser UI');
$report(str_contains($login, 'const MAXLEN = 20;') && str_contains($login, 'maxlength="20"'), 'login accepts full DB-sized user ID');
$report(str_contains($login, '/^[A-Za-z0-9._@-]*$/'), 'login allowlist matches manual user ID characters');
$manualUiStart = strpos($dashboard, 'var form_add_new_user_manual');
$manualUiEnd = strpos($dashboard, 'function nav_back_to_category_listing', $manualUiStart ?: 0);
$manualUi = $manualUiStart !== false && $manualUiEnd !== false
    ? substr($dashboard, $manualUiStart, $manualUiEnd - $manualUiStart)
    : '';
$report($manualUi !== '' && preg_match('/error:\s*function\s*\([^)]*\)\s*\{\s*\}/s', $manualUi) !== 1, 'manual AJAX error callback is no longer empty');

$report(str_contains($sync, '$protected_identity_map = [];'), 'legacy sync builds protected manual identity map');
$report(str_contains($sync, "(\$sso_row['account_source'] ?? '') !== 'manual'"), 'legacy sync recognizes manual provenance');
$report(str_contains($sync, "(int) (\$sso_row['sync_protected'] ?? 0) !== 1"), 'legacy sync recognizes protection flag');

$upMigration = $projectRoot . '/docs/migrations/S1_USER_PROVENANCE_UP.sql';
$downMigration = $projectRoot . '/docs/migrations/S1_USER_PROVENANCE_DOWN.sql';
$upSql = is_file($upMigration) ? (string) file_get_contents($upMigration) : '';
$downSql = is_file($downMigration) ? (string) file_get_contents($downMigration) : '';
$report(str_contains($upSql, 'ADD COLUMN account_source') && str_contains($upSql, 'ADD COLUMN sync_protected'), 'expanding provenance migration exists');
$report(str_contains($upSql, 'idx_user_sync_scope'), 'sync-scope index migration exists');
$report(str_contains($downSql, 'DROP COLUMN sync_protected') && str_contains($downSql, 'DROP COLUMN account_source'), 'rollback migration exists');

$runFixture = static function (string $relative, string $expected) use ($projectRoot): array {
    $output = [];
    $exitCode = 1;
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($projectRoot . '/' . $relative) . ' 2>&1', $output, $exitCode);
    return [$exitCode === 0 && in_array($expected, $output, true), implode(' | ', array_slice($output, -2))];
};

foreach ([
    ['tests/characterization/s1_manual_user_hardening.php', 'RESULT checks=29 failed=0', 'manual hardening fixture'],
    ['tests/characterization/s1_sync_provenance_protection.php', 'RESULT checks=7 failed=0', 'sync provenance fixture'],
    ['tests/characterization/r52_sync_orchestration.php', 'RESULT checks=18 failed=0', 'legacy sync regression'],
    ['tests/characterization/r52_sync_dry_run_zero_mutation.php', 'RESULT checks=25 failed=0', 'dormant dry-run regression'],
] as [$relative, $expected, $label]) {
    [$ok, $detail] = $runFixture($relative, $expected);
    $report($ok, $label, $detail);
}

printf("RESULT checks=%d failed=%d\n", $checks, $failed);
exit($failed === 0 ? 0 : 1);
