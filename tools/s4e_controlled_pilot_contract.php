<?php

if (PHP_SAPI !== 'cli') { exit(2); }
$root = dirname(__DIR__);
$checks = 0; $failed = 0;
$report = static function (bool $ok, string $label) use (&$checks, &$failed): void {
    $checks++; if (!$ok) $failed++; printf("%s %s\n", $ok ? 'PASS' : 'FAIL', $label);
};
$files = [
    'app/Sync/SyncPilotConfig.php', 'app/Sync/SyncPlanSubsetSelector.php',
    'app/Sync/SafeSyncOrchestrator.php', 'app/Sync/SyncPreviewService.php',
    'app/Sync/SyncEngineFactory.php', 'lib/q_func.php', 'admin/dashboard.php',
];
$source = [];
foreach ($files as $file) {
    $source[$file] = (string) file_get_contents($root . '/' . $file);
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($root . '/' . $file), $out, $code);
    $report($code === 0, 'PHP lint ' . $file);
    $out = []; $code = 1;
}
$config = $source['app/Sync/SyncPilotConfig.php'];
$selector = $source['app/Sync/SyncPlanSubsetSelector.php'];
$factory = $source['app/Sync/SyncEngineFactory.php'];
$qFunc = $source['lib/q_func.php'];
$dashboard = $source['admin/dashboard.php'];
$report(str_contains($config, "\$new !== 2 || \$update !== 1") && str_contains($config, 'SYNC_PILOT_DESTRUCTIVE_ACTION_FORBIDDEN'), 'pilot scope fixed at 2/1/0/0');
$report(str_contains($selector, "['NEW' => [], 'UPDATE' => []]") && !str_contains($selector, "'DEACTIVATE' =>"), 'selector cannot include destructive actions');
$report(str_contains($source['app/Sync/SafeSyncOrchestrator.php'], '$this->subsetSelector->select($plan)') , 'writer reselects subset from fresh snapshot before approval validation');
$report(str_contains($source['app/Sync/SyncPreviewService.php'], '$approvedPlan = $subsetSelector') && str_contains($source['app/Sync/SyncPreviewService.php'], "['pilot_counts']"), 'preview approval binds selected subset');
$report(str_contains($factory, 'createPilotCoordinator') && str_contains($factory, 'SYNC_PILOT_DISABLED'), 'endpoint factory has a dedicated fail-closed pilot path');
$report(str_contains($qFunc, 'createPilotCoordinator($approvalStore, $pilotConfig)') && !str_contains($qFunc, 'run_admin_sync_user($operation'), 'admin endpoint cannot call legacy or full writer');
$report(str_contains($qFunc, "\$coordinator->run(\n                    \$approvalId,\n                    \$triggeredBy,\n                    \$triggeredBy"), 'pilot persists canonical admin ID as triggered-by');
$report(str_contains($dashboard, 'pilot_apply_available === true') && str_contains($dashboard, '(pilotCounts.Deactivate || 0) === 0'), 'UI requires server pilot availability and zero destructive counts');
$report(str_contains($dashboard, "data: {admin_add_sync_user:'', sync_approval_id:pilotApprovalId}"), 'UI sends one-time approval only to guarded endpoint');

exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/tests/characterization/s4e_controlled_pilot_subset.php'), $output, $code);
$report($code === 0 && in_array('RESULT checks=8 failed=0', $output, true), 'controlled subset characterization passes');
printf("RESULT checks=%d failed=%d\n", $checks, $failed);
exit($failed === 0 ? 0 : 1);
