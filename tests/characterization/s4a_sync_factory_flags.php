<?php

/** S4A pure flag/factory fixture. No database, source or session access. */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(2);
}

$root = dirname(__DIR__, 2);
foreach ([
    'app/Sync/Contracts/ExternalUserSourceInterface.php',
    'app/Sync/Contracts/InitialPasswordFactoryInterface.php',
    'app/Sync/Contracts/SyncPersistenceInterface.php',
    'app/Sync/Contracts/SyncPolicyInterface.php',
    'app/Sync/Contracts/SyncReconciliationReaderInterface.php',
    'app/Sync/Contracts/SyncRunLockInterface.php',
    'app/Sync/Contracts/SyncApprovalStoreInterface.php',
    'app/Sync/Contracts/SyncPlanApprovalGateInterface.php',
    'app/Sync/DTO/SyncPlan.php',
    'app/Sync/DTO/SyncRunSummary.php',
    'app/Sync/DTO/SyncSafetyDecision.php',
    'app/Sync/DTO/SyncApproval.php',
    'app/Sync/DTO/SyncApprovalReceipt.php',
    'app/Sync/SyncDataTransformer.php',
    'app/Sync/SyncPlanner.php',
    'app/Sync/SyncReconciler.php',
    'app/Sync/SyncSafetyPolicy.php',
    'app/Sync/SyncSafetyViolation.php',
    'app/Sync/SafeSyncOrchestrator.php',
    'app/Sync/ApprovedSyncCoordinator.php',
    'app/Sync/SyncPlanFingerprinter.php',
    'app/Sync/SyncApprovalService.php',
    'app/Sync/SyncRuntimeConfig.php',
    'app/Sync/Adapters/DatabaseSyncPersistenceAdapter.php',
    'app/Sync/Adapters/DatabaseSyncReconciliationReader.php',
    'app/Sync/Adapters/DatabaseSyncRunLock.php',
    'app/Sync/Adapters/ExternalApiUserSource.php',
    'app/Sync/Adapters/LegacySyncPolicy.php',
    'app/Sync/Adapters/SecureInitialPasswordFactory.php',
    'app/Sync/SyncEngineFactory.php',
] as $file) {
    require_once $root . '/' . $file;
}

use OneId\App\Sync\ApprovedSyncCoordinator;
use OneId\App\Sync\Contracts\SyncApprovalStoreInterface;
use OneId\App\Sync\DTO\SyncApproval;
use OneId\App\Sync\SyncEngineFactory;
use OneId\App\Sync\SyncRuntimeConfig;

final class S4AOperationTrap
{
    public int $calls = 0;

    public function __call(string $name, array $arguments): never
    {
        $this->calls++;
        throw new LogicException('S4A factory performed operation I/O: ' . $name);
    }
}

final class S4AApprovalStore implements SyncApprovalStoreInterface
{
    public function save(SyncApproval $approval): void {}
    public function consume(string $approvalId): ?SyncApproval { return null; }
}

$checks = 0;
$failed = 0;
$report = static function (bool $ok, string $label) use (&$checks, &$failed): void {
    $checks++;
    if (!$ok) $failed++;
    printf("%s %s\n", $ok ? 'PASS' : 'FAIL', $label);
};

$default = SyncRuntimeConfig::fromValues(null, null);
$report(!$default->applyEnabled && $default->engine === 'disabled', 'missing flags default to false/disabled');
$report(!$default->canApply(), 'default configuration cannot Apply');

$explicitDisabled = SyncRuntimeConfig::fromValues('false', 'disabled');
$report(!$explicitDisabled->canApply(), 'explicit false/disabled cannot Apply');
$safe = SyncRuntimeConfig::fromValues('true', 'safe');
$report($safe->applyEnabled && $safe->engine === 'safe' && $safe->canApply(), 'only exact true/safe can Apply');

$invalidCases = [
    ['TRUE', 'safe', 'SYNC_APPLY_FLAG_INVALID'],
    ['yes', 'safe', 'SYNC_APPLY_FLAG_INVALID'],
    ['true', 'legacy', 'SYNC_ENGINE_INVALID'],
    ['true', 'unknown', 'SYNC_ENGINE_INVALID'],
    ['false', 'safe', 'SYNC_FLAG_COMBINATION_INVALID'],
    ['true', 'disabled', 'SYNC_FLAG_COMBINATION_INVALID'],
];
foreach ($invalidCases as [$apply, $engine, $expected]) {
    try {
        SyncRuntimeConfig::fromValues($apply, $engine);
        $blocked = false;
    } catch (RuntimeException $exception) {
        $blocked = $exception->getMessage() === $expected;
    }
    $report($blocked, sprintf('invalid combination %s/%s fails closed', $apply, $engine));
}

$disabledOperation = new S4AOperationTrap();
try {
    (new SyncEngineFactory($disabledOperation, $default))->createApprovedCoordinator(new S4AApprovalStore());
    $factoryBlocked = false;
} catch (RuntimeException $exception) {
    $factoryBlocked = $exception->getMessage() === 'SYNC_APPLY_DISABLED';
}
$report($factoryBlocked, 'disabled factory refuses to create writer');
$report($disabledOperation->calls === 0, 'disabled factory performs zero operation I/O');

$safeOperation = new S4AOperationTrap();
$coordinator = (new SyncEngineFactory($safeOperation, $safe))->createApprovedCoordinator(new S4AApprovalStore());
$report($coordinator instanceof ApprovedSyncCoordinator, 'true/safe factory creates approval-aware coordinator only');
$report($safeOperation->calls === 0, 'safe factory construction performs zero operation I/O');

printf("RESULT checks=%d failed=%d\n", $checks, $failed);
exit($failed === 0 ? 0 : 1);
