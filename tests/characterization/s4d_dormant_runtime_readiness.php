<?php

/** S4D in-memory preview approval fixture. No DB, ODBC, HTTP or live sync. */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(2);
}

$root = dirname(__DIR__, 2);
foreach ([
    'app/Sync/Contracts/ExternalUserSourceInterface.php',
    'app/Sync/Contracts/SyncPersistenceInterface.php',
    'app/Sync/Contracts/SyncPolicyInterface.php',
    'app/Sync/Contracts/SyncApprovalStoreInterface.php',
    'app/Sync/Contracts/SyncPlanApprovalGateInterface.php',
    'app/Sync/DTO/SyncPlan.php',
    'app/Sync/DTO/SyncSafetyDecision.php',
    'app/Sync/DTO/SyncApproval.php',
    'app/Sync/DTO/SyncApprovalReceipt.php',
    'app/Sync/SyncDataTransformer.php',
    'app/Sync/SyncPlanner.php',
    'app/Sync/SyncSafetyPolicy.php',
    'app/Sync/SyncPlanFingerprinter.php',
    'app/Sync/SyncApprovalService.php',
    'app/Sync/SyncPreviewService.php',
    'app/Sync/Adapters/LegacySyncPolicy.php',
] as $file) {
    require_once $root . '/' . $file;
}

use OneId\App\Sync\Adapters\LegacySyncPolicy;
use OneId\App\Sync\Contracts\ExternalUserSourceInterface;
use OneId\App\Sync\Contracts\SyncApprovalStoreInterface;
use OneId\App\Sync\Contracts\SyncPersistenceInterface;
use OneId\App\Sync\DTO\SyncApproval;
use OneId\App\Sync\SyncApprovalService;
use OneId\App\Sync\SyncPlanFingerprinter;
use OneId\App\Sync\SyncPlanner;
use OneId\App\Sync\SyncPreviewService;
use OneId\App\Sync\SyncSafetyPolicy;

function s4d_row(string $id, string $name, string $category, string $secondary = ''): array
{
    $row = ['ext_data_source_category' => $category];
    for ($index = 1; $index <= 12; $index++) $row['data' . $index] = '';
    $row['data1'] = $name;
    $row['data2'] = $secondary;
    $row['data4'] = $id;
    return $row;
}

function s4d_rows(): array
{
    return [
        s4d_row('STAFF-S4D', 'Staff S4D', 'Pentadbiran'),
        s4d_row('STUDENT-S4D', 'Student S4D', 'Pelajar', 'IC-S4D'),
    ];
}

final class S4DSource implements ExternalUserSourceInterface
{
    public int $calls = 0;
    public function __construct(private array $rows) {}
    public function fetchAll(): array { $this->calls++; return $this->rows; }
}

final class S4DPersistence implements SyncPersistenceInterface
{
    public array $reads = [];
    public function activeUsers(): array { $this->reads[] = __FUNCTION__; return []; }
    public function inactiveUserIds(): array { $this->reads[] = __FUNCTION__; return []; }
    private function mutation(): never { throw new LogicException('S4D preview attempted mutation'); }
    public function begin(): void { $this->mutation(); }
    public function commit(): void { $this->mutation(); }
    public function rollback(): void { $this->mutation(); }
    public function createHeader(int $type): int { $this->mutation(); }
    public function deactivateUser(string $userId): void { $this->mutation(); }
    public function updateUser(string $userId, array $row, string $changeHash): void { $this->mutation(); }
    public function updateHeaderStatus(int $headerId, int $status, string $field, int $count): void { $this->mutation(); }
    public function stageExternalUser(int $headerId, array $row): int { $this->mutation(); }
    public function insertExternalUser(array $row, int $categoryId, string $passwordHash, string $changeHash): void { $this->mutation(); }
    public function markStagedUser(int $headerId, int $bodyId, int $status): void { $this->mutation(); }
    public function appendChanges(array $changes): void { $this->mutation(); }
    public function updateSummary(int $headerId, int $new, int $updated, int $deactivated, int $reactivated, string $triggeredBy): void { $this->mutation(); }
    public function header(int $headerId): array { $this->mutation(); }
}

final class S4DStore implements SyncApprovalStoreInterface
{
    /** @var array<string, SyncApproval> */
    public array $records = [];
    public int $saveCalls = 0;
    public function save(SyncApproval $approval): void
    {
        $this->saveCalls++;
        $this->records[$approval->approvalId] = $approval;
    }
    public function consume(string $approvalId): ?SyncApproval { return null; }
}

function s4d_service(S4DSource $source, S4DPersistence $persistence): SyncPreviewService
{
    return new SyncPreviewService(
        $source,
        $persistence,
        new SyncPlanner(new LegacySyncPolicy()),
        300,
        5.0,
        new SyncSafetyPolicy()
    );
}

$checks = 0;
$failed = 0;
$report = static function (bool $ok, string $label) use (&$checks, &$failed): void {
    $checks++;
    if (!$ok) $failed++;
    printf("%s %s\n", $ok ? 'PASS' : 'FAIL', $label);
};

$source = new S4DSource(s4d_rows());
$persistence = new S4DPersistence();
$store = new S4DStore();
$approval = new SyncApprovalService($store, new SyncPlanFingerprinter());
$response = s4d_service($source, $persistence)->previewForApproval('0530-09', 2, $approval);
$encoded = json_encode($response);
$report($source->calls === 1, 'approval preview fetches one external snapshot');
$report($persistence->reads === ['activeUsers', 'inactiveUserIds'], 'approval preview performs only two internal reads');
$report($response['approval_ready'] === true && $response['can_apply'] === false, 'safe preview issues approval without authorizing UI Apply');
$report($response['blocking_codes'] === [] && $response['risk_level'] === 'normal', 'full S3 policy passes healthy fixture');
$report(preg_match('/^[a-f0-9]{64}$/', $response['approval_id']) === 1, 'opaque one-time approval ID is returned');
$report($store->saveCalls === 1 && count($store->records) === 1, 'approval is stored server-side exactly once');
$report($response['counts']['New'] === 2 && $response['source_rows'] === 2, 'approved counts and source baseline match snapshot');
$report(!str_contains($encoded, 'STAFF-S4D') && !str_contains($encoded, 'STUDENT-S4D'), 'response excludes raw user IDs');
$report(!str_contains($encoded, 'IC-S4D') && !str_contains($encoded, 'Staff S4D'), 'response excludes raw identity and name');

$sourceNoBaseline = new S4DSource(s4d_rows());
$persistenceNoBaseline = new S4DPersistence();
$storeNoBaseline = new S4DStore();
$blockedBaseline = s4d_service($sourceNoBaseline, $persistenceNoBaseline)->previewForApproval(
    '0530-09',
    null,
    new SyncApprovalService($storeNoBaseline, new SyncPlanFingerprinter())
);
$report($blockedBaseline['approval_ready'] === false, 'missing authoritative baseline blocks approval');
$report(in_array('SOURCE_BASELINE_UNAVAILABLE', $blockedBaseline['blocking_codes'], true), 'missing baseline has stable blocking code');
$report($storeNoBaseline->saveCalls === 0, 'missing baseline stores no approval');

$unsafeRows = s4d_rows();
$unsafeRows[] = s4d_row('UNKNOWN-S4D', 'Unknown', 'UnknownCategory');
$unsafeSource = new S4DSource($unsafeRows);
$unsafePersistence = new S4DPersistence();
$unsafeStore = new S4DStore();
$unsafe = s4d_service($unsafeSource, $unsafePersistence)->previewForApproval(
    '0530-09',
    3,
    new SyncApprovalService($unsafeStore, new SyncPlanFingerprinter())
);
$report($unsafe['approval_ready'] === false && $unsafe['risk_level'] === 'blocked', 'unsafe category blocks approval');
$report(in_array('UNKNOWN_SOURCE_CATEGORY', $unsafe['blocking_codes'], true), 'unsafe category exposes allowlisted blocking code');
$report($unsafeStore->saveCalls === 0, 'unsafe plan stores no approval');

printf("RESULT checks=%d failed=%d\n", $checks, $failed);
exit($failed === 0 ? 0 : 1);
