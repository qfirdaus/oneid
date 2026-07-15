<?php

/** Internal child: run exact pilot against a generated rehearsal database only. */
if (PHP_SAPI !== 'cli') { exit(2); }
$root = dirname(__DIR__);
require_once $root . '/lib/config.php';
require_once $root . '/lib/external_data_source_API.php';
require_once $root . '/bootstrap/sync_runtime.php';

$expected = trim((string) getenv('ONEID_REHEARSAL_TARGET_DATABASE'));
$reflection = new ReflectionProperty(Database::class, 'pdo');
$reflection->setAccessible(true);
/** @var PDO $pdo */
$pdo = $reflection->getValue($operation);
$actual = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
if ($expected === ''
    || !hash_equals($expected, $actual)
    || $actual === 'oneiddb'
    || preg_match('/\Aoneiddb_s4e_[0-9]{8}_[0-9]{6}_[a-f0-9]{4}\z/', $actual) !== 1
) {
    fwrite(STDERR, "FAIL child target guard rejected database\n");
    exit(2);
}

final class S4EIsolatedApprovalStore implements \OneId\App\Sync\Contracts\SyncApprovalStoreInterface
{
    /** @var array<string, \OneId\App\Sync\DTO\SyncApproval> */
    private array $records = [];
    public function save(\OneId\App\Sync\DTO\SyncApproval $approval): void { $this->records[$approval->approvalId] = $approval; }
    public function consume(string $approvalId): ?\OneId\App\Sync\DTO\SyncApproval
    {
        $approval = $this->records[$approvalId] ?? null;
        unset($this->records[$approvalId]);
        return $approval;
    }
}

try {
    $pilot = \OneId\App\Sync\SyncPilotConfig::fromValues('true', '2', '1', '0', '0');
    $selector = new \OneId\App\Sync\SyncPlanSubsetSelector($pilot);
    $store = new S4EIsolatedApprovalStore();
    $approval = new \OneId\App\Sync\SyncApprovalService($store, new \OneId\App\Sync\SyncPlanFingerprinter());
    $preview = new \OneId\App\Sync\SyncPreviewService(
        new \OneId\App\Sync\Adapters\ExternalApiUserSource(),
        new \OneId\App\Sync\Adapters\DatabaseSyncPersistenceAdapter($operation),
        new \OneId\App\Sync\SyncPlanner(new \OneId\App\Sync\Adapters\LegacySyncPolicy()),
        300,
        5.0,
        new \OneId\App\Sync\SyncSafetyPolicy()
    );
    $baseline = $operation->sync_latest_completed_source_rows();
    $receipt = $preview->previewForApproval('S4E isolated rehearsal', $baseline, $approval, $selector);
    if (($receipt['approval_ready'] ?? false) !== true || ($receipt['pilot_counts'] ?? []) !== [
        'New' => 2, 'Update' => 1, 'Deactivate' => 0, 'Reactivate' => 0,
    ]) {
        throw new RuntimeException('SYNC_REHEARSAL_APPROVAL_NOT_READY');
    }
    $coordinator = (new \OneId\App\Sync\SyncEngineFactory(
        $operation,
        \OneId\App\Sync\SyncRuntimeConfig::fromValues('true', 'safe')
    ))->createPilotCoordinator($store, $pilot);
    $summary = $coordinator->run(
        (string) $receipt['approval_id'],
        'S4E isolated rehearsal',
        'S4E isolated rehearsal'
    );
    printf(
        "PASS isolated_apply header=%d new=%d update=%d deactivate=%d reactivate=%d reconciliation=pass\n",
        $summary->headerId, $summary->new, $summary->updated, $summary->deactivated, $summary->reactivated
    );
    exit(0);
} catch (\OneId\App\Sync\SyncDatabaseStageException $exception) {
    printf(
        "FAIL code=SYNC_DATABASE_WRITE_FAILED stage=%s sqlstate=%s driver=%d\n",
        $exception->stage, $exception->sqlState, $exception->driverCode
    );
    exit(1);
} catch (Throwable $exception) {
    $allowed = [
        'SYNC_REHEARSAL_APPROVAL_NOT_READY','SYNC_APPROVAL_PLAN_MISMATCH',
        'SYNC_SAFETY_BLOCKED','SYNC_RECONCILIATION_MISMATCH','SYNC_UPDATE_NOT_APPLIED',
        'SYNC_INSERT_NOT_APPLIED','SYNC_AUDIT_WRITE_MISMATCH',
    ];
    $code = in_array($exception->getMessage(), $allowed, true)
        ? $exception->getMessage() : 'UNEXPECTED_ISOLATED_REHEARSAL_ERROR';
    printf("FAIL code=%s exception=%s\n", $code, get_class($exception));
    exit(1);
}
