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
    'app/Sync/Contracts/SyncApprovalStoreInterface.php',
    'app/Sync/DTO/SyncApproval.php',
    'app/Sync/DTO/SyncApprovalReceipt.php',
    'app/Sync/SyncPlanFingerprinter.php',
    'app/Sync/SyncApprovalService.php',
    'app/Sync/Adapters/SessionSyncApprovalStore.php',
    'tests/characterization/s4b_sync_approval_rejections.php',
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

$service = $source['app/Sync/SyncApprovalService.php'];
$fingerprinter = $source['app/Sync/SyncPlanFingerprinter.php'];
$sessionStore = $source['app/Sync/Adapters/SessionSyncApprovalStore.php'];
$qFunc = (string) file_get_contents($root . '/lib/q_func.php');
$dashboard = (string) file_get_contents($root . '/admin/dashboard.php');
$factory = (string) file_get_contents($root . '/app/Sync/SyncEngineFactory.php');

$report(str_contains($fingerprinter, 'safeProjection()') && str_contains($fingerprinter, 'usort('), 'fingerprint is redacted and canonicalized');
$report(str_contains($fingerprinter, 'discardedProtectedCollisions') && str_contains($fingerprinter, 'protectedManualUsers'), 'fingerprint binds safety/provenance metrics');
$report(str_contains($service, 'random_bytes(32)') && str_contains($service, 'random_bytes(8)') && str_contains($service, "'/^[a-f0-9]{64}$/'"), 'approval and correlation identifiers are independently random');
$report(str_contains($service, '$this->ttlSeconds > 300') && str_contains($service, 'SYNC_APPROVAL_EXPIRED'), 'approval TTL is bounded and enforced');
$consumePosition = strpos($service, '$this->store->consume($approvalId)');
$adminPosition = strpos($service, 'SYNC_APPROVAL_ADMIN_MISMATCH');
$planPosition = strpos($service, 'SYNC_APPROVAL_PLAN_MISMATCH');
$report($consumePosition !== false && $consumePosition < $adminPosition && $consumePosition < $planPosition, 'approval is atomically burned before validation result');
$report(str_contains($service, 'hash_equals($approval->adminId') && str_contains($service, 'hash_equals($approval->planFingerprint'), 'admin and plan bindings use timing-safe comparison');
$report(!str_contains($service, 'SyncPersistenceInterface') && !str_contains($service, 'begin(') && !str_contains($service, 'commit('), 'approval service cannot mutate application database');
$report(str_contains($sessionStore, 'PHP_SESSION_ACTIVE') && str_contains($sessionStore, 'unset($records[$approvalId])'), 'server-side session store requires active session and one-time consume');
$report(str_contains($qFunc, 'SyncApprovalService') && str_contains($qFunc, 'SessionSyncApprovalStore'), 'S4D q_func wires server-side one-time approval');
$report(str_contains($dashboard, 'pilot_apply_available === true') && str_contains($dashboard, 'pilotApprovalId'), 'dashboard approval bearer is restricted to controlled pilot UI');
$report(str_contains($factory, 'SyncApprovalService') && !str_contains($factory, 'SessionSyncApprovalStore'), 'S4C factory uses injected approval store without session coupling');

$output = [];
$code = 1;
exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/tests/characterization/s4b_sync_approval_rejections.php') . ' 2>&1', $output, $code);
$detail = implode(' | ', array_slice($output, -2));
$report($code === 0 && in_array('RESULT checks=26 failed=0', $output, true), 'S4B in-memory rejection fixture passes', $detail);

printf("RESULT checks=%d failed=%d\n", $checks, $failed);
exit($failed === 0 ? 0 : 1);
