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
    printf("%s %-70s%s\n", $ok ? 'PASS' : 'FAIL', $label, $detail === '' ? '' : ' ' . $detail);
};

$files = [
    'bootstrap/sync_runtime.php',
    'app/Sync/SyncPreviewService.php',
    'app/Sync/SyncRuntimeConfig.php',
    'app/Sync/SyncEngineFactory.php',
    'lib/Database.php',
    'lib/request_security.php',
    'lib/q_func.php',
    'admin/dashboard.php',
    'tests/characterization/s4d_dormant_runtime_readiness.php',
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

$bootstrap = $source['bootstrap/sync_runtime.php'];
$preview = $source['app/Sync/SyncPreviewService.php'];
$database = $source['lib/Database.php'];
$guard = $source['lib/request_security.php'];
$qFunc = $source['lib/q_func.php'];
$dashboard = $source['admin/dashboard.php'];

$report(str_contains($bootstrap, 'SyncEngineFactory.php') && !str_contains($bootstrap, 'fetchAll(') && !str_contains($bootstrap, 'begin('), 'runtime class map loads definitions without I/O');
$report(str_contains($preview, 'previewForApproval(') && str_contains($preview, 'SyncSafetyPolicy'), 'preview approval uses full safety policy');
$report(str_contains($preview, 'SOURCE_BASELINE_UNAVAILABLE') && str_contains($preview, 'approval_ready'), 'baseline is mandatory before approval issuance');
$report(!str_contains($preview, 'begin()') && !str_contains($preview, 'createHeader(') && !str_contains($preview, 'commit()'), 'preview approval has no application mutation capability');
$report(str_contains($database, 'sync_latest_completed_source_rows') && str_contains($database, 'ext_head_status IN (2, 4)'), 'baseline comes from latest completed OneID sync header');
$report(str_contains($guard, "'admin_add_sync_user'") && str_contains($guard, 'Exactly one recognized action is required') && str_contains($guard, 'oneid_require_csrf()'), 'Apply endpoint inherits admin exactly-one-action and CSRF guards');
$report(str_contains($qFunc, 'SyncRuntimeConfig::fromEnvironment()') && str_contains($qFunc, 'createApprovedCoordinator($approvalStore)'), 'Apply endpoint uses strict approval-aware safe coordinator');
$report(str_contains($qFunc, "\$_POST['sync_approval_id']") && !str_contains($qFunc, 'run_admin_sync_user($operation'), 'Apply requires approval ID and cannot invoke legacy writer');
$report(!str_contains($qFunc, 'FILTER_VALIDATE_BOOLEAN') && str_contains($qFunc, "'SYNC_APPLY_DISABLED'"), 'loose flag parsing is absent and disabled code is allowlisted');
$report(str_contains($qFunc, "'msg' => 'External sync was not applied.'") && !str_contains($qFunc, "['error' => \$exception->getMessage()]"), 'Apply failure response is generic');
$report(!str_contains($dashboard, "data: {admin_add_sync_user:''}") && !str_contains($dashboard, 'sync_approval_id'), 'dashboard still exposes no Apply control or approval bearer');

$output = [];
$code = 1;
exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/tests/characterization/s4d_dormant_runtime_readiness.php') . ' 2>&1', $output, $code);
$detail = implode(' | ', array_slice($output, -2));
$report($code === 0 && in_array('RESULT checks=15 failed=0', $output, true), 'S4D in-memory dormant readiness fixture passes', $detail);

printf("RESULT checks=%d failed=%d\n", $checks, $failed);
exit($failed === 0 ? 0 : 1);
