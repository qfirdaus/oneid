<?php

/** S4C in-memory coordinator fixture. No database, network, session or HTTP I/O. */

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
    'app/Sync/SyncPlanFingerprinter.php',
    'app/Sync/SyncApprovalService.php',
    'app/Sync/SafeSyncOrchestrator.php',
    'app/Sync/ApprovedSyncCoordinator.php',
    'app/Sync/Adapters/LegacySyncPolicy.php',
] as $file) {
    require_once $root . '/' . $file;
}

use OneId\App\Sync\Adapters\LegacySyncPolicy;
use OneId\App\Sync\ApprovedSyncCoordinator;
use OneId\App\Sync\Contracts\ExternalUserSourceInterface;
use OneId\App\Sync\Contracts\InitialPasswordFactoryInterface;
use OneId\App\Sync\Contracts\SyncApprovalStoreInterface;
use OneId\App\Sync\Contracts\SyncPersistenceInterface;
use OneId\App\Sync\Contracts\SyncReconciliationReaderInterface;
use OneId\App\Sync\Contracts\SyncRunLockInterface;
use OneId\App\Sync\DTO\SyncApproval;
use OneId\App\Sync\SafeSyncOrchestrator;
use OneId\App\Sync\SyncApprovalService;
use OneId\App\Sync\SyncPlanFingerprinter;
use OneId\App\Sync\SyncPlanner;
use OneId\App\Sync\SyncReconciler;
use OneId\App\Sync\SyncSafetyPolicy;

function s4c_row(string $id, string $name, string $category, string $secondary = ''): array
{
    $row = ['ext_data_source_category' => $category];
    for ($index = 1; $index <= 12; $index++) $row['data' . $index] = '';
    $row['data1'] = $name;
    $row['data2'] = $secondary;
    $row['data4'] = $id;
    return $row;
}

function s4c_rows(string $staffName = 'Staff S4C'): array
{
    return [
        s4c_row('STAFF-S4C', $staffName, 'Pentadbiran'),
        s4c_row('STUDENT-S4C', 'Student S4C', 'Pelajar', 'IC-STUDENT-S4C'),
    ];
}

final class S4CSource implements ExternalUserSourceInterface
{
    private array $trace;
    public int $calls = 0;
    public function __construct(array &$trace, private array $rows) { $this->trace =& $trace; }
    public function fetchAll(): array { $this->calls++; $this->trace[] = 'source.fetch'; return $this->rows; }
}

final class S4CLock implements SyncRunLockInterface
{
    private array $trace;
    public function __construct(array &$trace, private bool $available = true) { $this->trace =& $trace; }
    public function acquire(int $waitSeconds = 0): bool { $this->trace[] = 'lock.acquire'; return $this->available; }
    public function release(): void { $this->trace[] = 'lock.release'; }
}

final class S4CPassword implements InitialPasswordFactoryInterface
{
    public function createHash(): string { return 'fixture-hash'; }
}

final class S4CReconciliation implements SyncReconciliationReaderInterface
{
    private array $trace;
    public function __construct(array &$trace) { $this->trace =& $trace; }
    public function changeCounts(int $headerId): array
    {
        $this->trace[] = 'reconcile.read';
        return ['New' => 2, 'Update' => 0, 'Deactivate' => 0, 'Reactivate' => 0];
    }
}

final class S4CPersistence implements SyncPersistenceInterface
{
    private array $trace;
    private int $body = 10;
    public function __construct(array &$trace) { $this->trace =& $trace; }
    private function event(string $name): void { $this->trace[] = $name; }
    public function begin(): void { $this->event('persistence.begin'); }
    public function commit(): void { $this->event('persistence.commit'); }
    public function rollback(): void { $this->event('persistence.rollback'); }
    public function createHeader(int $type): int { $this->event('header.create'); return 91; }
    public function activeUsers(): array { $this->event('users.active'); return []; }
    public function inactiveUserIds(): array { $this->event('users.inactive'); return []; }
    public function deactivateUser(string $userId): void { $this->event('user.deactivate'); }
    public function updateUser(string $userId, array $row, string $changeHash): void { $this->event('user.update'); }
    public function updateHeaderStatus(int $headerId, int $status, string $field, int $count): void { $this->event('header.status'); }
    public function stageExternalUser(int $headerId, array $row): int { $this->event('user.stage'); return $this->body++; }
    public function insertExternalUser(array $row, int $categoryId, string $passwordHash, string $changeHash): void { $this->event('user.insert'); }
    public function markStagedUser(int $headerId, int $bodyId, int $status): void { $this->event('user.stage-status'); }
    public function appendChanges(array $changes): void { $this->event('audit.append'); }
    public function updateSummary(int $headerId, int $new, int $updated, int $deactivated, int $reactivated, string $triggeredBy): void { $this->event('summary.update'); }
    public function header(int $headerId): array { $this->event('header.read'); return ['ext_head_id' => $headerId]; }
}

final class S4CApprovalStore implements SyncApprovalStoreInterface
{
    private array $trace;
    /** @var array<string, SyncApproval> */
    public array $records = [];
    public function __construct(array &$trace) { $this->trace =& $trace; }
    public function save(SyncApproval $approval): void { $this->records[$approval->approvalId] = $approval; }
    public function consume(string $approvalId): ?SyncApproval
    {
        $this->trace[] = 'approval.consume';
        $record = $this->records[$approvalId] ?? null;
        unset($this->records[$approvalId]);
        return $record;
    }
}

/** @return array{ApprovedSyncCoordinator, SyncApprovalService, S4CSource, S4CApprovalStore, SyncPlanner} */
function s4c_fixture(array &$trace, array $rows, bool $lockAvailable = true): array
{
    $source = new S4CSource($trace, $rows);
    $store = new S4CApprovalStore($trace);
    $planner = new SyncPlanner(new LegacySyncPolicy());
    $approval = new SyncApprovalService($store, new SyncPlanFingerprinter(), 300);
    $orchestrator = new SafeSyncOrchestrator(
        $source,
        new S4CPersistence($trace),
        new S4CReconciliation($trace),
        new S4CLock($trace, $lockAvailable),
        $planner,
        new SyncSafetyPolicy(),
        new SyncReconciler(),
        new S4CPassword()
    );
    return [new ApprovedSyncCoordinator($orchestrator, $approval), $approval, $source, $store, $planner];
}

$checks = 0;
$failed = 0;
$report = static function (bool $ok, string $label) use (&$checks, &$failed): void {
    $checks++;
    if (!$ok) $failed++;
    printf("%s %s\n", $ok ? 'PASS' : 'FAIL', $label);
};

$trace = [];
[$coordinator, $approval, $source, $store, $planner] = s4c_fixture($trace, s4c_rows());
$previewPlan = $planner->plan(s4c_rows(), [], []);
$receipt = $approval->issue('0530-09', $previewPlan, 2, 1_000_000);
$summary = $coordinator->run($receipt->approvalId, '0530-09', 'S4C fixture', 0, 1_000_001);
$report($summary->new === 2, 'approved coordinator executes expected plan');
$report($source->calls === 1, 'writer obtains exactly one external snapshot');
$report(count(array_filter($trace, fn ($event) => $event === 'users.active')) === 1, 'active users are read exactly once');
$report(count(array_filter($trace, fn ($event) => $event === 'users.inactive')) === 1, 'inactive users are read exactly once');
$report(array_search('approval.consume', $trace, true) < array_search('persistence.begin', $trace, true), 'approval is consumed before transaction');
$report(array_search('source.fetch', $trace, true) < array_search('approval.consume', $trace, true), 'approval validates the freshly fetched snapshot');
$report(array_search('reconcile.read', $trace, true) < array_search('persistence.commit', $trace, true), 'reconciliation still precedes commit');
$report(end($trace) === 'lock.release', 'successful approved run releases lock');
$report($store->records === [], 'successful approved run burns token');

$trace = [];
[$coordinator, $approval, , $store, $planner] = s4c_fixture($trace, s4c_rows('Changed Staff'));
$receipt = $approval->issue('0530-09', $planner->plan(s4c_rows(), [], []), 2, 2_000_000);
try {
    $coordinator->run($receipt->approvalId, '0530-09', 'S4C mismatch', 0, 2_000_001);
    $mismatch = false;
} catch (RuntimeException $exception) {
    $mismatch = $exception->getMessage() === 'SYNC_APPROVAL_PLAN_MISMATCH';
}
$report($mismatch, 'changed snapshot is rejected as plan mismatch');
$report(!in_array('persistence.begin', $trace, true) && !in_array('header.create', $trace, true), 'plan mismatch has zero mutation');
$report($store->records === [] && end($trace) === 'lock.release', 'mismatch burns token and releases lock');

$trace = [];
[$coordinator, $approval, , , $planner] = s4c_fixture($trace, s4c_rows());
$receipt = $approval->issue('0530-09', $planner->plan(s4c_rows(), [], []), 2, 3_000_000);
try {
    $coordinator->run($receipt->approvalId, 'OTHER', 'S4C wrong admin', 0, 3_000_001);
    $wrongAdmin = false;
} catch (RuntimeException $exception) {
    $wrongAdmin = $exception->getMessage() === 'SYNC_APPROVAL_ADMIN_MISMATCH';
}
$report($wrongAdmin && !in_array('persistence.begin', $trace, true), 'wrong admin is rejected before mutation');

$trace = [];
[$coordinator, $approval, , , $planner] = s4c_fixture($trace, s4c_rows());
$receipt = $approval->issue('0530-09', $planner->plan(s4c_rows(), [], []), 2, 4_000_000);
try {
    $coordinator->run($receipt->approvalId, '0530-09', 'S4C expired', 0, 4_000_300);
    $expired = false;
} catch (RuntimeException $exception) {
    $expired = $exception->getMessage() === 'SYNC_APPROVAL_EXPIRED';
}
$report($expired && !in_array('persistence.begin', $trace, true), 'expired approval is rejected before mutation');

$trace = [];
[$coordinator, $approval, , , $planner] = s4c_fixture($trace, s4c_rows(), false);
$receipt = $approval->issue('0530-09', $planner->plan(s4c_rows(), [], []), 2, 5_000_000);
try {
    $coordinator->run($receipt->approvalId, '0530-09', 'S4C locked', 0, 5_000_001);
    $locked = false;
} catch (RuntimeException $exception) {
    $locked = $exception->getMessage() === 'SYNC_ALREADY_RUNNING';
}
$report($locked, 'lock contention fails closed');
$report($trace === ['lock.acquire'], 'lock contention performs no source, approval or persistence access');

$trace = [];
[$coordinator, $approval, , , $planner] = s4c_fixture($trace, s4c_rows());
$receipt = $approval->issue('0530-09', $planner->plan(s4c_rows(), [], []), 2, 6_000_000);
$coordinator->run($receipt->approvalId, '0530-09', 'S4C first', 0, 6_000_001);
$beginBeforeReplay = count(array_filter($trace, fn ($event) => $event === 'persistence.begin'));
try {
    $coordinator->run($receipt->approvalId, '0530-09', 'S4C replay', 0, 6_000_002);
    $replay = false;
} catch (RuntimeException $exception) {
    $replay = $exception->getMessage() === 'SYNC_APPROVAL_NOT_AVAILABLE';
}
$beginAfterReplay = count(array_filter($trace, fn ($event) => $event === 'persistence.begin'));
$report($replay, 'approval replay is rejected');
$report($beginBeforeReplay === $beginAfterReplay, 'approval replay cannot start a second transaction');
$report(end($trace) === 'lock.release', 'replay rejection releases lock');

printf("RESULT checks=%d failed=%d\n", $checks, $failed);
exit($failed === 0 ? 0 : 1);
