<?php

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(2);
}

$root = dirname(__DIR__);
$checks = 0;
$failed = 0;
$report = static function (bool $ok, string $label, string $detail = '') use (&$checks, &$failed): void {
    $checks++;
    if (!$ok) $failed++;
    printf("%s %-68s%s\n", $ok ? 'PASS' : 'FAIL', $label, $detail === '' ? '' : ' ' . $detail);
};

$files = [
    'app/Sync/SyncRuntimeConfig.php',
    'app/Sync/SyncEngineFactory.php',
    'tests/characterization/s4a_sync_factory_flags.php',
];
$source = [];
foreach ($files as $file) {
    $path = $root . '/' . $file;
    $source[$file] = is_file($path) ? (string) file_get_contents($path) : '';
    $output = [];
    $code = 1;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1', $output, $code);
    $report($source[$file] !== '' && $code === 0, 'source and PHP lint: ' . $file);
}

$config = $source['app/Sync/SyncRuntimeConfig.php'];
$factory = $source['app/Sync/SyncEngineFactory.php'];
$qFunc = (string) file_get_contents($root . '/lib/q_func.php');
$dashboard = (string) file_get_contents($root . '/admin/dashboard.php');
$legacyRunner = (string) file_get_contents($root . '/lib/sync_user_runner.php');

$report(str_contains($config, "? 'false' : \$apply") && str_contains($config, "? 'disabled' : \$engine"), 'missing flags resolve to false/disabled');
$report(str_contains($config, "['false', 'true']") && str_contains($config, "['disabled', 'safe']"), 'flags use exact strict allowlists');
$report(!str_contains($config, 'FILTER_VALIDATE_BOOLEAN') && !str_contains($config, 'strtolower('), 'loose boolean and case coercion are absent');
$report(str_contains($config, 'SYNC_APPLY_FLAG_INVALID') && str_contains($config, 'SYNC_ENGINE_INVALID') && str_contains($config, 'SYNC_FLAG_COMBINATION_INVALID'), 'invalid states have stable fail-closed codes');
$report(!str_contains($config, "'legacy'") && !str_contains($factory, 'run_admin_sync_user'), 'legacy writer is not selectable by S4A components');
$report(str_contains($factory, 'SYNC_APPLY_DISABLED') && str_contains($factory, 'createApprovedCoordinator'), 'factory blocks disabled configuration');
$report(str_contains($factory, 'new ApprovedSyncCoordinator(') && str_contains($factory, 'new SafeSyncOrchestrator('), 'factory creates approval-aware safe coordinator only');
$report(!str_contains($factory, 'fetchAll(') && !str_contains($factory, 'begin(') && !str_contains($factory, 'run('), 'factory construction contains no source/transaction/run call');
$report(str_contains($qFunc, 'SyncEngineFactory') && str_contains($qFunc, 'SyncRuntimeConfig::fromEnvironment()'), 'S4D q_func uses strict safe factory wiring');
$report(!str_contains($legacyRunner, 'SyncEngineFactory') && !str_contains($legacyRunner, 'SyncRuntimeConfig'), 'legacy runner has no S4A runtime wiring');
$report(!str_contains($dashboard, 'admin_apply_sync_user') && !str_contains($dashboard, "data: {admin_add_sync_user:''}"), 'dashboard remains preview-only');
$report(!str_contains($qFunc, 'FILTER_VALIDATE_BOOLEAN') && !str_contains($qFunc, 'run_admin_sync_user($operation'), 'S4D removes loose flag parsing and legacy writer selection');

$output = [];
$code = 1;
exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/tests/characterization/s4a_sync_factory_flags.php') . ' 2>&1', $output, $code);
$detail = implode(' | ', array_slice($output, -2));
$report($code === 0 && in_array('RESULT checks=14 failed=0', $output, true), 'pure S4A flag/factory fixture passes', $detail);

printf("RESULT checks=%d failed=%d\n", $checks, $failed);
exit($failed === 0 ? 0 : 1);
