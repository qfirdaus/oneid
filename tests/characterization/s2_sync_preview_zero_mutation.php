<?php

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(2);
}

$root = dirname(__DIR__, 2);
foreach ([
    'app/Sync/Contracts/ExternalUserSourceInterface.php',
    'app/Sync/Contracts/SyncPersistenceInterface.php',
    'app/Sync/Contracts/SyncPolicyInterface.php',
    'app/Sync/DTO/SyncPlan.php',
    'app/Sync/ExternalRowNormalizer.php',
    'app/Sync/SyncDataTransformer.php',
    'app/Sync/SyncPlanner.php',
    'app/Sync/SyncPreviewService.php',
    'app/Sync/Adapters/LegacySyncPolicy.php',
] as $file) {
    require_once $root . '/' . $file;
}

use OneId\App\Sync\Adapters\LegacySyncPolicy;
use OneId\App\Sync\Contracts\ExternalUserSourceInterface;
use OneId\App\Sync\Contracts\SyncPersistenceInterface;
use OneId\App\Sync\SyncPlanner;
use OneId\App\Sync\SyncPreviewService;
use OneId\App\Sync\ExternalRowNormalizer;

function s2_row(string $id, string $name, string $category = 'Pentadbiran', string $secondary = ''): array
{
    $row = ['ext_data_source_category' => $category];
    for ($i = 1; $i <= 12; $i++) {
        $row['data' . $i] = '';
    }
    $row['data1'] = $name;
    $row['data2'] = $secondary;
    $row['data4'] = $id;
    return $row;
}

final class S2Source implements ExternalUserSourceInterface
{
    public int $calls = 0;

    public function __construct(private array $rows)
    {
    }

    public function fetchAll(): array
    {
        $this->calls++;
        return $this->rows;
    }
}

final class S2Persistence implements SyncPersistenceInterface
{
    public array $calls = [];

    public function __construct(private array $active, private array $inactive = [])
    {
    }

    private function mutation(string $method): never
    {
        throw new LogicException('S2 preview attempted mutation: ' . $method);
    }

    public function activeUsers(): array { $this->calls[] = __FUNCTION__; return $this->active; }
    public function inactiveUserIds(): array { $this->calls[] = __FUNCTION__; return $this->inactive; }
    public function begin(): void { $this->mutation(__FUNCTION__); }
    public function commit(): void { $this->mutation(__FUNCTION__); }
    public function rollback(): void { $this->mutation(__FUNCTION__); }
    public function createHeader(int $type): int { $this->mutation(__FUNCTION__); }
    public function deactivateUser(string $userId): void { $this->mutation(__FUNCTION__); }
    public function updateUser(string $userId, array $row, string $changeHash): void { $this->mutation(__FUNCTION__); }
    public function updateHeaderStatus(int $headerId, int $status, string $field, int $count): void { $this->mutation(__FUNCTION__); }
    public function stageExternalUser(int $headerId, array $row): int { $this->mutation(__FUNCTION__); }
    public function insertExternalUser(array $row, int $categoryId, string $passwordHash, string $changeHash): void { $this->mutation(__FUNCTION__); }
    public function markStagedUser(int $headerId, int $bodyId, int $status): void { $this->mutation(__FUNCTION__); }
    public function appendChanges(array $changes): void { $this->mutation(__FUNCTION__); }
    public function updateSummary(int $headerId, int $new, int $updated, int $deactivated, int $reactivated, string $triggeredBy): void { $this->mutation(__FUNCTION__); }
    public function header(int $headerId): array { $this->mutation(__FUNCTION__); }
}

$checks = 0;
$failed = 0;
$report = static function (bool $ok, string $label) use (&$checks, &$failed): void {
    $checks++;
    if (!$ok) $failed++;
    printf("%s %s\n", $ok ? 'PASS' : 'FAIL', $label);
};

$protected = s2_row('MANUAL-SECRET-ID', 'Protected Person', 'Pentadbiran', 'SECRET-IC');
$protected['u_id'] = 'MANUAL-SECRET-ID';
$protected['account_source'] = 'manual';
$protected['sync_protected'] = 1;
$existing = s2_row('EXISTING-ID', 'Existing Person');
$existing['u_id'] = 'EXISTING-ID';
$existing['account_source'] = 'external';
$existing['sync_protected'] = 0;

$source = new S2Source([
    s2_row('SECRET-IC', 'Collision Person'),
    s2_row('NEW-SECRET-ID', 'New Secret Person'),
]);
$persistence = new S2Persistence([$protected, $existing]);
$service = new SyncPreviewService(
    $source,
    $persistence,
    new SyncPlanner(new LegacySyncPolicy()),
    300,
    5.0
);
$preview = $service->preview();
$encoded = json_encode($preview);

$report($source->calls === 1, 'external snapshot fetched exactly once');
$report($persistence->calls === ['activeUsers', 'inactiveUserIds'], 'only two persistence reads occur');
$report($preview['mode'] === 'preview' && $preview['can_apply'] === false, 'response is explicitly preview-only');
$report($preview['protected_manual_users'] === 1, 'protected manual account counted');
$report($preview['discarded_protected_collisions'] === 1, 'protected identity collision excluded');
$report($preview['counts']['New'] === 1, 'non-colliding external identity remains NEW');
$report($preview['counts']['Deactivate'] === 1, 'only unprotected missing account is proposed for deactivation');
$report(!str_contains($encoded, 'MANUAL-SECRET-ID'), 'raw protected user ID is absent');
$report(!str_contains($encoded, 'SECRET-IC'), 'raw identity number is absent');
$report(!str_contains($encoded, 'Protected Person'), 'raw name is absent');
$report(preg_match('/^[a-f0-9]{64}$/', $preview['plan_hash']) === 1, 'deterministic plan hash format');
$report(strtotime($preview['expires_at']) > strtotime($preview['generated_at']), 'preview has future expiry');
$report($preview['risk_level'] === 'blocked', 'deactivation anomaly crosses hard-stop threshold');
$report(in_array('Deactivation threshold exceeded; apply must remain blocked.', $preview['warnings'], true), 'threshold warning returned');
$staffNormalized = ExternalRowNormalizer::normalize([
    'DATA1' => 'Staff Name', 'IDPEKERJA' => 'EMP', 'NOPEKERJA' => 'NO',
    'DATA4' => 'IC', 'JENIS' => 'Pentadbiran',
]);
$studentNormalized = ExternalRowNormalizer::normalize([
    'NAMA' => 'Student Name', 'NO_MATRIK' => 'MAT', 'DATA2' => 'IC',
    'NAMA_PTJ' => 'PTJ', 'PROGRAM' => 'PROGRAM',
    'EXT_DATA_SOURCE_CATEGORY' => 'Pelajar',
]);
$report($staffNormalized['data2'] === 'EMP' && $staffNormalized['data3'] === 'NO' && $staffNormalized['ext_data_source_category'] === 'Pentadbiran', 'FreeTDS staff row normalized to canonical schema');
$report($studentNormalized['data1'] === 'Student Name' && $studentNormalized['data4'] === 'MAT' && $studentNormalized['data6'] === 'PTJ', 'FreeTDS student row normalized to canonical schema');

$emptySource = new S2Source([]);
$emptyPersistence = new S2Persistence([]);
try {
    (new SyncPreviewService(
        $emptySource,
        $emptyPersistence,
        new SyncPlanner(new LegacySyncPolicy())
    ))->preview();
    $emptyBlocked = false;
} catch (RuntimeException $exception) {
    $emptyBlocked = $exception->getMessage() === 'EMPTY_EXTERNAL_SNAPSHOT';
}
$report($emptyBlocked, 'empty external snapshot fails closed');
$report($emptyPersistence->calls === [], 'empty snapshot causes zero persistence reads');

printf("RESULT checks=%d failed=%d\n", $checks, $failed);
exit($failed === 0 ? 0 : 1);
