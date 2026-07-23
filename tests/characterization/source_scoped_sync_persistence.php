<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(2);

require_once dirname(__DIR__, 2) . '/bootstrap/sync_runtime.php';

use OneId\App\Sync\Adapters\SourceScopedSyncPersistenceAdapter;
use OneId\App\Sync\Contracts\SyncPersistenceInterface;
use OneId\App\Sync\DTO\SyncPlan;
use OneId\App\Sync\SyncSafetyPolicy;

final class SourceScopePersistenceFixture implements SyncPersistenceInterface
{
    public function begin(): void {}
    public function commit(): void {}
    public function rollback(): void {}
    public function createHeader(int $type): int { return 1; }
    public function activeUsers(): array {
        return [
            ['u_id' => 'staff-a', 'u_category' => 2],
            ['u_id' => 'staff-b', 'u_category' => 3],
            ['u_id' => 'ug-a', 'u_category' => 10],
            ['u_id' => 'ug-b', 'u_category' => 11],
            ['u_id' => 'ug-c', 'u_category' => 12],
            ['u_id' => 'manual', 'u_category' => 6],
        ];
    }
    public function inactiveUserIds(): array { return ['inactive']; }
    public function deactivateUser(string $userId): void {}
    public function updateUser(string $userId, array $row, string $changeHash): void {}
    public function updateHeaderStatus(int $headerId, int $status, string $field, int $count): void {}
    public function stageExternalUser(int $headerId, array $row): int { return 1; }
    public function insertExternalUser(array $row, int $categoryId, string $passwordHash, string $changeHash): void {}
    public function markStagedUser(int $headerId, int $bodyId, int $status): void {}
    public function appendChanges(array $changes): void {}
    public function updateSummary(int $headerId, int $new, int $updated, int $deactivated, int $reactivated, string $triggeredBy): void {}
    public function header(int $headerId): array { return []; }
}

$fixture = new SourceScopePersistenceFixture();
$staff = new SourceScopedSyncPersistenceAdapter($fixture, [2, 3]);
$ug = new SourceScopedSyncPersistenceAdapter($fixture, [10, 11, 12]);
$staffIds = array_column($staff->activeUsers(), 'u_id');
$ugIds = array_column($ug->activeUsers(), 'u_id');

$checks = [
    'Staff scope contains staff categories only' =>
        $staffIds === ['staff-a', 'staff-b'],
    'UG scope contains student categories only' =>
        $ugIds === ['ug-a', 'ug-b', 'ug-c'],
    'inactive identity read remains available for reactivation matching' =>
        $ug->inactiveUserIds() === ['inactive'],
    'Staff-only safety does not require a UG snapshot' =>
        (new SyncSafetyPolicy(requiredSourceCode: 'STAFF_HR'))->assess(
            [[
                'ext_data_source_category' => 'Pentadbiran',
                'data4' => 'staff-a',
            ]],
            [],
            new SyncPlan([], 1, 0, 0),
            1
        )->allowed,
    'UG-only safety does not require a Staff snapshot' =>
        (new SyncSafetyPolicy(requiredSourceCode: 'STUDENT_UG'))->assess(
            [[
                'ext_data_source_category' => 'Pelajar',
                'data4' => 'ug-a',
            ]],
            [],
            new SyncPlan([], 1, 0, 0),
            1
        )->allowed,
];
$failed = 0;
foreach ($checks as $label => $passed) {
    $failed += $passed ? 0 : 1;
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $label);
}
printf("RESULT checks=%d failed=%d\n", count($checks), $failed);
exit($failed === 0 ? 0 : 1);
