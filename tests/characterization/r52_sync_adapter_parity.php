<?php

/**
 * R5.2D2 test-only adapter parity fixture.
 *
 * No database, network, session or production sync execution is performed.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(2);
}

$projectRoot = dirname(__DIR__, 2);

foreach ([
    'app/Sync/Contracts/ExternalUserSourceInterface.php',
    'app/Sync/Contracts/InitialPasswordFactoryInterface.php',
    'app/Sync/Contracts/SyncPolicyInterface.php',
    'app/Sync/Contracts/SyncPersistenceInterface.php',
    'tests/Support/Sync/CallableExternalUserSourceAdapter.php',
    'tests/Support/Sync/CallableInitialPasswordFactoryAdapter.php',
    'tests/Support/Sync/LegacySyncPolicyAdapter.php',
    'tests/Support/Sync/LegacyOperationPersistenceAdapter.php',
] as $file) {
    require_once $projectRoot . '/' . $file;
}

use OneId\Tests\Support\Sync\CallableExternalUserSourceAdapter;
use OneId\Tests\Support\Sync\CallableInitialPasswordFactoryAdapter;
use OneId\Tests\Support\Sync\LegacyOperationPersistenceAdapter;
use OneId\Tests\Support\Sync\LegacySyncPolicyAdapter;

final class R52D2LegacyOperationSpy
{
    /** @var list<array{0:string,1:array<int,mixed>}> */
    public array $calls = [];

    public function __call(string $name, array $arguments): mixed
    {
        $this->calls[] = [$name, $arguments];

        return match ($name) {
            'action_add_new_ext_header' => 77,
            'sync_get_all_sso_user' => [['u_id' => 'A01']],
            'sync_get_inactive_user_ids' => ['I01'],
            'action_add_external_temp_body' => 101,
            'action_get_ext_header' => ['ext_head_id' => 77, 'ext_head_status' => 2],
            default => null,
        };
    }
}

$checks = 0;
$failed = 0;
$report = static function (bool $ok, string $item, string $detail = '') use (&$checks, &$failed): void {
    $checks++;
    if (!$ok) {
        $failed++;
    }
    printf("%s %-58s%s\n", $ok ? 'PASS' : 'FAIL', $item, $detail === '' ? '' : ' ' . $detail);
};

$sourceCalls = 0;
$sourceRows = [['data4' => 'U01']];
$source = new CallableExternalUserSourceAdapter(static function () use (&$sourceCalls, $sourceRows): array {
    $sourceCalls++;
    return $sourceRows;
});
$report($source->fetchAll() === $sourceRows && $sourceCalls === 1, 'external source callable parity');

$passwordCalls = 0;
$password = new CallableInitialPasswordFactoryAdapter(static function () use (&$passwordCalls): string {
    $passwordCalls++;
    return 'deterministic-test-hash';
});
$report(
    $password->createHash() === 'deterministic-test-hash' && $passwordCalls === 1,
    'password factory callable parity'
);

$policy = new LegacySyncPolicyAdapter();
$report($policy->excludedUserIds() === ['10'], 'legacy exclusion parity');
foreach ([
    'Akademik' => 2,
    'Pentadbiran' => 3,
    'Pelajar' => 10,
    'PelajarPelajar' => 10,
    'PentadbiranPelajar' => 11,
    'AkademikPelajar' => 12,
    'TidakDikenali' => 0,
] as $category => $expected) {
    $report($policy->categoryIdFor($category) === $expected, 'category parity: ' . $category);
}

$row = [];
for ($i = 1; $i <= 12; $i++) {
    $row['data' . $i] = 'D' . $i;
}
$dataValues = array_values($row);
$changes = [['u_id' => 'U01', 'action' => 'UPDATE']];
$spy = new R52D2LegacyOperationSpy();
$adapter = new LegacyOperationPersistenceAdapter($spy);

$adapter->begin();
$headerId = $adapter->createHeader(0);
$active = $adapter->activeUsers();
$inactive = $adapter->inactiveUserIds();
$adapter->deactivateUser('U02');
$adapter->updateUser('U01', $row, 'change-hash');
$adapter->updateHeaderStatus(77, 1, 'ext_head_initial_sourcedata', 2);
$bodyId = $adapter->stageExternalUser(77, $row);
$adapter->insertExternalUser($row, 10, 'password-hash', 'insert-hash');
$adapter->markStagedUser(77, 101, 2);
$adapter->appendChanges($changes);
$adapter->updateSummary(77, 1, 2, 3, 4, 'admin-user');
$header = $adapter->header(77);
$adapter->commit();
$adapter->rollback();

$expectedCalls = [
    ['beginTransaction', []],
    ['action_add_new_ext_header', [0]],
    ['sync_get_all_sso_user', []],
    ['sync_get_inactive_user_ids', []],
    ['admin_update_user_status', ['U02', 0]],
    ['admin_update_specific_user_info_all_data', array_merge(['U01'], $dataValues, ['change-hash'])],
    ['admin_update_ext_header_status', [77, 1, 'ext_head_initial_sourcedata', 2]],
    ['action_add_external_temp_body', array_merge([77], $dataValues)],
    ['action_add_new_user_from_external_source', array_merge(['D4', 10, 'password-hash'], $dataValues, ['insert-hash'])],
    ['admin_update_ext_body_status', [77, 101, 2]],
    ['sync_log_change_batch', [$changes]],
    ['sync_update_header_summary', [77, 1, 2, 3, 4, 'admin-user']],
    ['action_get_ext_header', [77]],
    ['commit', []],
    ['rollback', []],
];

$report($spy->calls === $expectedCalls, 'persistence exact method/argument parity');
$report($headerId === 77, 'createHeader return parity');
$report($active === [['u_id' => 'A01']], 'activeUsers return parity');
$report($inactive === ['I01'], 'inactiveUserIds return parity');
$report($bodyId === 101, 'stageExternalUser return parity');
$report($header === ['ext_head_id' => 77, 'ext_head_status' => 2], 'header return parity');

$adapterSymbols = [
    'CallableExternalUserSourceAdapter',
    'CallableInitialPasswordFactoryAdapter',
    'LegacySyncPolicyAdapter',
    'LegacyOperationPersistenceAdapter',
];
$productionFiles = [
    'lib/sync_user_runner.php',
    'lib/Database.php',
    'lib/q_func.php',
    'page/dashboard.php',
    'admin/dashboard.php',
];
$productionReferences = [];
foreach ($productionFiles as $file) {
    $contents = (string) file_get_contents($projectRoot . '/' . $file);
    foreach ($adapterSymbols as $symbol) {
        if (str_contains($contents, $symbol)) {
            $productionReferences[] = $file . ':' . $symbol;
        }
    }
}
$report(
    $productionReferences === [],
    'test-only adapters remain outside production',
    $productionReferences === [] ? '' : implode(',', $productionReferences)
);

$legacyRunner = $projectRoot . '/lib/sync_user_runner.php';
$legacyRunnerHash = hash_file('sha256', $legacyRunner);
$report(
    $legacyRunnerHash === '965fd187492e1f120b074601746b031474405480f234412e458f64189108c8bb',
    'legacy runner remains unchanged and unselected',
    'sha256=' . $legacyRunnerHash
);
$databaseSource = (string) file_get_contents($projectRoot . '/lib/Database.php');
$report(
    str_contains($databaseSource, 'sync_latest_completed_source_rows')
        && str_contains($databaseSource, 'ext_head_status IN (2, 4)'),
    'S4D database change is read-only baseline lookup'
);
$qFuncSource = (string) file_get_contents($projectRoot . '/lib/q_func.php');
$report(
    str_contains($qFuncSource, 'createApprovedCoordinator($approvalStore)')
        && !str_contains($qFuncSource, 'run_admin_sync_user($operation'),
    'S4D q_func selects approved coordinator, not legacy writer'
);
$report(!is_file($projectRoot . '/cron/run_sync.php'), 'retired cron absent from runtime');

printf("RESULT checks=%d failed=%d\n", $checks, $failed);
exit($failed === 0 ? 0 : 1);
