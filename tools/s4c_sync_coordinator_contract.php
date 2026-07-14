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
    'app/Sync/Contracts/SyncPlanApprovalGateInterface.php',
    'app/Sync/SyncApprovalService.php',
    'app/Sync/SafeSyncOrchestrator.php',
    'app/Sync/ApprovedSyncCoordinator.php',
    'app/Sync/SyncEngineFactory.php',
    'tests/characterization/s4c_approved_single_snapshot.php',
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

$orchestrator = $source['app/Sync/SafeSyncOrchestrator.php'];
$coordinator = $source['app/Sync/ApprovedSyncCoordinator.php'];
$factory = $source['app/Sync/SyncEngineFactory.php'];
$qFunc = (string) file_get_contents($root . '/lib/q_func.php');
$dashboard = (string) file_get_contents($root . '/admin/dashboard.php');
$cronPath = $root . '/cron/run_sync.php';
$cron = is_file($cronPath) ? (string) file_get_contents($cronPath) : '';

$fetch = strpos($orchestrator, '$this->source->fetchAll()');
$plan = strpos($orchestrator, '$plan = $this->planner->plan(');
$approval = strpos($orchestrator, '$approvalGate->consumeAndValidate(');
$begin = strpos($orchestrator, '$this->persistence->begin()');
$execute = strpos($orchestrator, '$this->executePlan($headerId, $plan, $triggeredBy)');
$report($fetch !== false && $fetch < $plan && $plan < $approval && $approval < $begin && $begin < $execute, 'single plan is fetched, approved, then executed in order');
$report(substr_count($orchestrator, '$this->source->fetchAll()') === 1, 'orchestrator contains one external fetch path');
$report(str_contains($orchestrator, '$approval->acceptedBaseline') && str_contains($orchestrator, 'SyncPlanApprovalGateInterface'), 'accepted baseline and approval interface feed safety gate');
$report(str_contains($coordinator, 'runApproved(') && str_contains($coordinator, 'SyncPlanApprovalGateInterface'), 'coordinator makes approval mandatory');
$report(str_contains($factory, 'createApprovedCoordinator') && str_contains($factory, 'new SyncApprovalService('), 'factory exposes approval-aware coordinator');
$report(str_contains($factory, 'private function buildSafeOrchestrator') && !str_contains($factory, 'public function createSafeOrchestrator'), 'raw writer construction is private to factory');
$report(!str_contains($factory, 'SessionSyncApprovalStore'), 'factory requires injected server-side approval store');
$report(!str_contains($qFunc, 'ApprovedSyncCoordinator') && !str_contains($dashboard, 'admin_apply_sync_user') && !str_contains($cron, 'ApprovedSyncCoordinator'), 'runtime endpoint, Apply UI and cron remain unwired');

$output = [];
$code = 1;
exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/tests/characterization/s4c_approved_single_snapshot.php') . ' 2>&1', $output, $code);
$detail = implode(' | ', array_slice($output, -2));
$report($code === 0 && in_array('RESULT checks=19 failed=0', $output, true), 'S4C in-memory coordinator fixture passes', $detail);

printf("RESULT checks=%d failed=%d\n", $checks, $failed);
exit($failed === 0 ? 0 : 1);
