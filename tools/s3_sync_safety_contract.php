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
    'app/Sync/Contracts/SyncReconciliationReaderInterface.php',
    'app/Sync/Contracts/SyncRunLockInterface.php',
    'app/Sync/DTO/SyncSafetyDecision.php',
    'app/Sync/SyncSafetyPolicy.php',
    'app/Sync/SyncSafetyViolation.php',
    'app/Sync/SyncReconciler.php',
    'app/Sync/SafeSyncOrchestrator.php',
    'app/Sync/Adapters/DatabaseSyncPersistenceAdapter.php',
    'app/Sync/Adapters/DatabaseSyncRunLock.php',
    'app/Sync/Adapters/DatabaseSyncReconciliationReader.php',
    'lib/Database.php',
    'tests/characterization/s3_sync_operational_safety.php',
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
$policy = $source['app/Sync/SyncSafetyPolicy.php'];
$database = $source['lib/Database.php'];
$persistenceAdapter = $source['app/Sync/Adapters/DatabaseSyncPersistenceAdapter.php'];
$qFunc = (string) file_get_contents($root . '/lib/q_func.php');
$legacyRunner = (string) file_get_contents($root . '/lib/sync_user_runner.php');

$sourcePosition = strpos($orchestrator, '$this->source->fetchAll()');
$beginPosition = strpos($orchestrator, '$this->persistence->begin()');
$executePosition = strpos($orchestrator, '$result = $this->executePlan');
$commitPosition = strpos($orchestrator, '$this->persistence->commit()');
$report($sourcePosition !== false && $beginPosition !== false && $sourcePosition < $beginPosition, 'external fetch and planning precede transaction');
$report($executePosition !== false && $commitPosition !== false && $executePosition < $commitPosition && str_contains($orchestrator, '$this->reconciler->assertMatched'), 'reconciled execution completes before commit');
$report(str_contains($orchestrator, 'catch (Throwable $exception)') && str_contains($orchestrator, '$this->persistence->rollback()'), 'all writer exceptions enter rollback guard');
$report(str_contains($orchestrator, 'finally') && str_contains($orchestrator, '$this->lock->release()'), 'run lock is released in finally');
$report(str_contains($orchestrator, 'SYNC_ALREADY_RUNNING'), 'concurrent run fails with stable diagnostic code');
$report(str_contains($policy, 'STAFF_SOURCE_MISSING') && str_contains($policy, 'STUDENT_SOURCE_MISSING'), 'both external source families are mandatory');
$report(str_contains($policy, 'DEACTIVATION_THRESHOLD_EXCEEDED') && str_contains($policy, 'SOURCE_SHRINK_THRESHOLD_EXCEEDED'), 'blast-radius thresholds fail closed');
$report(str_contains($policy, 'PROTECTED_IDENTITY_COLLISION') && str_contains($policy, 'UNKNOWN_SOURCE_CATEGORY'), 'identity and category anomalies fail closed');
$report(str_contains($database, 'GET_LOCK(:lock_name, :wait_seconds)') && str_contains($database, 'RELEASE_LOCK(:lock_name)'), 'database adapter uses connection-scoped advisory lock');
$report(str_contains($database, 'FROM sync_change_log') && str_contains($database, 'GROUP BY action'), 'reconciliation reads durable audit totals');
$report(str_contains($persistenceAdapter, 'SYNC_DEACTIVATE_NOT_APPLIED') && str_contains($persistenceAdapter, 'SYNC_INSERT_NOT_APPLIED') && str_contains($persistenceAdapter, 'SYNC_AUDIT_WRITE_MISMATCH'), 'writer rejects zero-row and audit-count anomalies');
$report(!str_contains($qFunc, 'SafeSyncOrchestrator') && !str_contains($legacyRunner, 'SafeSyncOrchestrator'), 'S3 orchestrator has no production caller wiring');
$report(str_contains($qFunc, 'SyncRuntimeConfig::fromEnvironment()') && !str_contains($qFunc, 'run_admin_sync_user($operation'), 'Apply is strict safe-only and defaults disabled');

$output = [];
$code = 1;
exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/tests/characterization/s3_sync_operational_safety.php') . ' 2>&1', $output, $code);
$detail = implode(' | ', array_slice($output, -2));
$report($code === 0 && in_array('RESULT checks=24 failed=0', $output, true), 'in-memory operational safety fixture passes', $detail);

printf("RESULT checks=%d failed=%d\n", $checks, $failed);
exit($failed === 0 ? 0 : 1);
