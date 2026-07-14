<?php

/**
 * S3 transaction, lock, source-completeness and reconciliation fixture.
 *
 * In-memory only: no database, network, session or filesystem write.
 */

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
    'app/Sync/DTO/SyncPlan.php',
    'app/Sync/DTO/SyncRunSummary.php',
    'app/Sync/DTO/SyncSafetyDecision.php',
    'app/Sync/SyncDataTransformer.php',
    'app/Sync/SyncPlanner.php',
    'app/Sync/SyncReconciler.php',
    'app/Sync/SyncSafetyPolicy.php',
    'app/Sync/SyncSafetyViolation.php',
    'app/Sync/SafeSyncOrchestrator.php',
    'app/Sync/Adapters/DatabaseSyncPersistenceAdapter.php',
    'app/Sync/Adapters/LegacySyncPolicy.php',
] as $file) {
    require_once $root . '/' . $file;
}

use OneId\App\Sync\Adapters\DatabaseSyncPersistenceAdapter;
use OneId\App\Sync\Adapters\LegacySyncPolicy;
use OneId\App\Sync\Contracts\ExternalUserSourceInterface;
use OneId\App\Sync\Contracts\InitialPasswordFactoryInterface;
use OneId\App\Sync\Contracts\SyncPersistenceInterface;
use OneId\App\Sync\Contracts\SyncReconciliationReaderInterface;
use OneId\App\Sync\Contracts\SyncRunLockInterface;
use OneId\App\Sync\DTO\SyncPlan;
use OneId\App\Sync\SafeSyncOrchestrator;
use OneId\App\Sync\SyncPlanner;
use OneId\App\Sync\SyncReconciler;
use OneId\App\Sync\SyncSafetyPolicy;
function s3_row(string $id, string $name, string $category, string $secondary = ''): array
{
    $row = ['ext_data_source_category' => $category];
    for ($index = 1; $index <= 12; $index++) {
        $row['data' . $index] = '';
    }
    $row['data1'] = $name;
    $row['data2'] = $secondary;
    $row['data4'] = $id;
    return $row;
}

function s3_source_rows(): array
{
    return [
        s3_row('STAFF-S3', 'Staff S3', 'Pentadbiran'),
        s3_row('STUDENT-S3', 'Student S3', 'Pelajar', 'IC-STUDENT-S3'),
    ];
}

final class S3Source implements ExternalUserSourceInterface
{
    private array $trace;

    public function __construct(array &$trace, private array $rows, private bool $shouldThrow = false)
    {
        $this->trace =& $trace;
    }

    public function fetchAll(): array
    {
        $this->trace[] = 'source.fetch';
        if ($this->shouldThrow) {
            throw new RuntimeException('fixture upstream failure');
        }
        return $this->rows;
    }
}

final class S3Lock implements SyncRunLockInterface
{
    private array $trace;

    public function __construct(array &$trace, private bool $available = true)
    {
        $this->trace =& $trace;
    }

    public function acquire(int $waitSeconds = 0): bool
    {
        $this->trace[] = 'lock.acquire';
        return $this->available;
    }

    public function release(): void
    {
        $this->trace[] = 'lock.release';
    }
}

final class S3Password implements InitialPasswordFactoryInterface
{
    public function createHash(): string
    {
        return 'fixture-password-hash';
    }
}

final class S3Reconciliation implements SyncReconciliationReaderInterface
{
    private array $trace;

    public function __construct(array &$trace, private array $counts)
    {
        $this->trace =& $trace;
    }

    public function changeCounts(int $headerId): array
    {
        $this->trace[] = 'reconcile.read';
        return $this->counts;
    }
}

final class S3Persistence implements SyncPersistenceInterface
{
    private array $trace;
    private int $bodyId = 100;

    public function __construct(
        array &$trace,
        private array $active = [],
        private array $inactive = [],
        private ?string $throwAt = null
    ) {
        $this->trace =& $trace;
    }

    private function record(string $event): void
    {
        $this->trace[] = $event;
        if ($this->throwAt === $event) {
            throw new RuntimeException('fixture mutation failure');
        }
    }

    public function begin(): void { $this->record('persistence.begin'); }
    public function commit(): void { $this->record('persistence.commit'); }
    public function rollback(): void { $this->record('persistence.rollback'); }
    public function createHeader(int $type): int { $this->record('header.create'); return 77; }
    public function activeUsers(): array { $this->record('users.active'); return $this->active; }
    public function inactiveUserIds(): array { $this->record('users.inactive'); return $this->inactive; }
    public function deactivateUser(string $userId): void { $this->record('user.deactivate'); }
    public function updateUser(string $userId, array $row, string $changeHash): void { $this->record('user.update'); }
    public function updateHeaderStatus(int $headerId, int $status, string $field, int $count): void { $this->record('header.status'); }
    public function stageExternalUser(int $headerId, array $row): int { $this->record('user.stage'); return $this->bodyId++; }
    public function insertExternalUser(array $row, int $categoryId, string $passwordHash, string $changeHash): void { $this->record('user.insert'); }
    public function markStagedUser(int $headerId, int $bodyId, int $status): void { $this->record('user.stage-status'); }
    public function appendChanges(array $changes): void { $this->record('audit.append'); }
    public function updateSummary(int $headerId, int $new, int $updated, int $deactivated, int $reactivated, string $triggeredBy): void { $this->record('summary.update'); }
    public function header(int $headerId): array { $this->record('header.read'); return ['ext_head_id' => $headerId]; }
}

final class S3ZeroAffectedOperation
{
    public function admin_update_user_status(string $userId, int $status): int
    {
        return 0;
    }

    public function action_add_new_user_from_external_source(...$arguments): int
    {
        return 0;
    }
}

function s3_orchestrator(
    array &$trace,
    array $rows,
    array $auditCounts,
    bool $lockAvailable = true,
    bool $sourceThrows = false,
    ?string $throwAt = null
): SafeSyncOrchestrator {
    return new SafeSyncOrchestrator(
        new S3Source($trace, $rows, $sourceThrows),
        new S3Persistence($trace, [], [], $throwAt),
        new S3Reconciliation($trace, $auditCounts),
        new S3Lock($trace, $lockAvailable),
        new SyncPlanner(new LegacySyncPolicy()),
        new SyncSafetyPolicy(),
        new SyncReconciler(),
        new S3Password()
    );
}

$checks = 0;
$failed = 0;
$report = static function (bool $ok, string $label) use (&$checks, &$failed): void {
    $checks++;
    if (!$ok) $failed++;
    printf("%s %s\n", $ok ? 'PASS' : 'FAIL', $label);
};

$healthyCounts = ['New' => 2, 'Update' => 0, 'Deactivate' => 0, 'Reactivate' => 0];

$trace = [];
try {
    s3_orchestrator($trace, s3_source_rows(), $healthyCounts, false)->run('S3 fixture');
    $blocked = false;
} catch (RuntimeException $exception) {
    $blocked = $exception->getMessage() === 'SYNC_ALREADY_RUNNING';
}
$report($blocked, 'concurrent run fails closed when advisory lock is unavailable');
$report($trace === ['lock.acquire'], 'lock contention causes zero source and persistence access');

$trace = [];
try {
    s3_orchestrator($trace, s3_source_rows(), $healthyCounts, true, true)->run('S3 fixture');
    $upstreamFailed = false;
} catch (RuntimeException $exception) {
    $upstreamFailed = $exception->getMessage() === 'fixture upstream failure';
}
$report($upstreamFailed, 'upstream failure is propagated');
$report($trace === ['lock.acquire', 'source.fetch', 'lock.release'], 'upstream failure starts no transaction and releases lock');

$trace = [];
try {
    s3_orchestrator($trace, [s3_row('STAFF-ONLY', 'Staff', 'Pentadbiran')], $healthyCounts)
        ->run('S3 fixture');
    $sourceBlocked = false;
} catch (RuntimeException $exception) {
    $sourceBlocked = $exception->getMessage() === 'SYNC_SAFETY_BLOCKED';
}
$report($sourceBlocked, 'missing source family blocks the run');
$report(!in_array('persistence.begin', $trace, true) && !in_array('header.create', $trace, true), 'source safety block occurs before transaction/header');
$report(end($trace) === 'lock.release', 'source safety block releases lock');

$trace = [];
try {
    s3_orchestrator($trace, s3_source_rows(), $healthyCounts, true, false, 'user.insert')
        ->run('S3 fixture');
    $mutationFailed = false;
} catch (RuntimeException $exception) {
    $mutationFailed = $exception->getMessage() === 'fixture mutation failure';
}
$report($mutationFailed, 'writer exception is propagated');
$report(in_array('persistence.rollback', $trace, true), 'writer exception rolls back transaction');
$report(!in_array('persistence.commit', $trace, true), 'writer exception cannot commit');
$report(end($trace) === 'lock.release', 'writer exception releases lock');

$trace = [];
$mismatch = ['New' => 1, 'Update' => 0, 'Deactivate' => 0, 'Reactivate' => 0];
try {
    s3_orchestrator($trace, s3_source_rows(), $mismatch)->run('S3 fixture');
    $reconciliationBlocked = false;
} catch (RuntimeException $exception) {
    $reconciliationBlocked = $exception->getMessage() === 'SYNC_RECONCILIATION_MISMATCH';
}
$report($reconciliationBlocked, 'audit mismatch fails reconciliation');
$report(in_array('persistence.rollback', $trace, true) && !in_array('persistence.commit', $trace, true), 'reconciliation mismatch rolls back before commit');
$report(end($trace) === 'lock.release', 'reconciliation mismatch releases lock');

$trace = [];
$summary = s3_orchestrator($trace, s3_source_rows(), $healthyCounts)->run('S3 fixture');
$report($summary->new === 2 && $summary->updated === 0 && $summary->deactivated === 0, 'healthy run returns exact planned totals');
$report(array_search('source.fetch', $trace, true) < array_search('persistence.begin', $trace, true), 'external snapshot and planning precede transaction');
$report(array_search('reconcile.read', $trace, true) < array_search('persistence.commit', $trace, true), 'reconciliation completes before commit');
$report(end($trace) === 'lock.release', 'successful run releases lock');

$policy = new SyncSafetyPolicy();
$massPlan = new SyncPlan([
    ['action' => 'DEACTIVATE'],
], 2, 0, 0);
$decision = $policy->assess(s3_source_rows(), array_fill(0, 10, ['u_id' => 'x']), $massPlan, 2);
$report(!$decision->allowed && in_array('DEACTIVATION_THRESHOLD_EXCEEDED', $decision->blockingCodes, true), 'mass-deactivation threshold is fail-closed');
$shrink = $policy->assess(s3_source_rows(), [], new SyncPlan([], 2, 0, 0), 10);
$report(!$shrink->allowed && in_array('SOURCE_SHRINK_THRESHOLD_EXCEEDED', $shrink->blockingCodes, true), 'source shrink threshold is fail-closed');
$collision = $policy->assess(s3_source_rows(), [], new SyncPlan([], 2, 0, 0, [], 1, 1), 2);
$report(!$collision->allowed && in_array('PROTECTED_IDENTITY_COLLISION', $collision->blockingCodes, true), 'protected identity collision is fail-closed');
$unknownRows = s3_source_rows();
$unknownRows[] = s3_row('UNKNOWN-S3', 'Unknown S3', 'UnmappedCategory');
$unknown = $policy->assess($unknownRows, [], new SyncPlan([], 3, 0, 0), 3);
$report(!$unknown->allowed && in_array('UNKNOWN_SOURCE_CATEGORY', $unknown->blockingCodes, true), 'unknown source category is fail-closed even without a NEW action');

$strictAdapter = new DatabaseSyncPersistenceAdapter(new S3ZeroAffectedOperation());
try {
    $strictAdapter->deactivateUser('RACE-S3');
    $zeroDeactivateBlocked = false;
} catch (RuntimeException $exception) {
    $zeroDeactivateBlocked = $exception->getMessage() === 'SYNC_DEACTIVATE_NOT_APPLIED';
}
$report($zeroDeactivateBlocked, 'real zero-row deactivation cannot be counted as executed');
try {
    $strictAdapter->insertExternalUser(s3_row('RACE-S3', 'Race S3', 'Pentadbiran'), 3, 'hash', 'change-hash');
    $zeroInsertBlocked = false;
} catch (RuntimeException $exception) {
    $zeroInsertBlocked = $exception->getMessage() === 'SYNC_INSERT_NOT_APPLIED';
}
$report($zeroInsertBlocked, 'protected/racing zero-row insert cannot be counted as executed');

printf("RESULT checks=%d failed=%d\n", $checks, $failed);
exit($failed === 0 ? 0 : 1);
