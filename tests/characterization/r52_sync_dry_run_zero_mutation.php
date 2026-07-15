<?php

/**
 * R5.2D5 pure SyncPlan and test-only dry-run characterization.
 *
 * No database, network, session or filesystem write is performed.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(2);
}

$projectRoot = dirname(__DIR__, 2);
$r52d5LegacyExternalRows = [];

function EXTERNAL_DATA_SOURCE_GET_ALL_USER(): array
{
    global $r52d5LegacyExternalRows;
    return $r52d5LegacyExternalRows;
}

require_once $projectRoot . '/lib/auth_security.php';
require_once $projectRoot . '/lib/sync_user_runner.php';
foreach ([
    'app/Sync/Contracts/ExternalUserSourceInterface.php',
    'app/Sync/Contracts/SyncPolicyInterface.php',
    'app/Sync/Contracts/SyncPersistenceInterface.php',
    'app/Sync/DTO/SyncPlan.php',
    'app/Sync/SyncPlanner.php',
    'tests/Support/Sync/CallableExternalUserSourceAdapter.php',
    'tests/Support/Sync/LegacySyncPolicyAdapter.php',
    'tests/Support/Sync/LegacyOperationPersistenceAdapter.php',
    'tests/Support/Sync/TestSyncDryRun.php',
] as $file) {
    require_once $projectRoot . '/' . $file;
}

use OneId\App\Sync\DTO\SyncPlan;
use OneId\App\Sync\SyncPlanner;
use OneId\Tests\Support\Sync\CallableExternalUserSourceAdapter;
use OneId\Tests\Support\Sync\LegacyOperationPersistenceAdapter;
use OneId\Tests\Support\Sync\LegacySyncPolicyAdapter;
use OneId\Tests\Support\Sync\TestSyncDryRun;

function r52d5_row(string $uid, string $name, string $category, string $secondaryId = ''): array
{
    $row = ['ext_data_source_category' => $category];
    for ($index = 1; $index <= 12; $index++) {
        $row['data' . $index] = '';
    }
    $row['data1'] = $name;
    $row['data2'] = $secondaryId;
    $row['data4'] = $uid;
    return $row;
}

function r52d5_sso_row(string $uid, array $row): array
{
    $row['u_id'] = $uid;
    $row['source'] = '1';
    $row['u_changes_hash'] = sync_compute_hash(
        $row['data1'], $row['data2'], $row['data3'], $row['data4'],
        $row['data5'], $row['data6'], $row['data7'], $row['data8'],
        $row['data9'], $row['data10'], $row['data11'], $row['data12'],
        $row['ext_data_source_category']
    );
    return $row;
}

final class R52D5Operation
{
    public array $ssoRows = [];
    public array $inactiveUids = [];
    public array $logs = [];
    public array $inserted = [];
    public array $updated = [];
    public array $calls = [];
    public array $header = [
        'ext_head_id' => 77,
        'ext_head_status' => 0,
        'ext_head_initial_sourcedata' => null,
        'ext_head_uploaded_data' => null,
    ];

    public function __construct(private bool $allowMutations)
    {
    }

    private function read(string $method): void
    {
        $this->calls[] = $method;
    }

    private function mutate(string $method): void
    {
        $this->calls[] = $method;
        if (!$this->allowMutations) {
            throw new LogicException('dry-run mutation attempted: ' . $method);
        }
    }

    public function beginTransaction(): void
    {
        $this->mutate(__FUNCTION__);
    }

    public function action_add_new_ext_header($type): int
    {
        $this->mutate(__FUNCTION__);
        return 77;
    }

    public function sync_get_all_sso_user(): array
    {
        $this->read(__FUNCTION__);
        return $this->ssoRows;
    }

    public function sync_get_inactive_user_ids(): array
    {
        $this->read(__FUNCTION__);
        return $this->inactiveUids;
    }

    public function admin_update_user_status($uid, $status): void
    {
        $this->mutate(__FUNCTION__);
    }

    public function admin_update_specific_user_info_all_data(...$arguments): void
    {
        $this->mutate(__FUNCTION__);
        $this->updated[] = $arguments;
    }

    public function admin_update_ext_header_status($headerId, $status, $field, $count): void
    {
        $this->mutate(__FUNCTION__);
        $this->header['ext_head_status'] = $status;
        $this->header[$field] = $count;
    }

    public function action_add_external_temp_body(...$arguments): int
    {
        $this->mutate(__FUNCTION__);
        return 100 + count($this->inserted);
    }

    public function action_add_new_user_from_external_source(...$arguments): void
    {
        $this->mutate(__FUNCTION__);
        $this->inserted[] = $arguments;
    }

    public function admin_update_ext_body_status($headerId, $bodyId, $status): void
    {
        $this->mutate(__FUNCTION__);
    }

    public function sync_log_change_batch(array $rows): void
    {
        $this->mutate(__FUNCTION__);
        $this->logs = $rows;
    }

    public function sync_update_header_summary(...$arguments): void
    {
        $this->mutate(__FUNCTION__);
    }

    public function commit(): void
    {
        $this->mutate(__FUNCTION__);
    }

    public function rollback(): void
    {
        $this->mutate(__FUNCTION__);
    }

    public function action_get_ext_header($headerId): array
    {
        $this->read(__FUNCTION__);
        return $this->header;
    }
}

$checks = 0;
$failed = 0;
$report = static function (bool $ok, string $item, string $detail = '') use (&$checks, &$failed): void {
    $checks++;
    if (!$ok) {
        $failed++;
    }
    printf("%s %-62s%s\n", $ok ? 'PASS' : 'FAIL', $item, $detail === '' ? '' : ' ' . $detail);
};

$existingOld = r52d5_row('IC-UPD', 'Old Name', 'Pentadbiran');
$existingNew = r52d5_row('IC-UPD', 'New Name', 'Pentadbiran');
$student = r52d5_row('STUDENT-1', 'Student', 'Pelajar', 'IC-STUDENT');
$missing = r52d5_row('IC-MISSING', 'Missing', 'Akademik');
$excluded = r52d5_row('10', 'Excluded', 'Pentadbiran');
$new = r52d5_row('NEW-1', 'New User', 'Pentadbiran');
$reactivate = r52d5_row('RETURN-1', 'Return User', 'Akademik');
$invalid = r52d5_row('', 'Invalid', 'Pentadbiran');
$externalRows = [$existingNew, $student, $new, $reactivate, $invalid, $excluded];
$ssoRows = [
    r52d5_sso_row('STAFF-1', $existingOld),
    r52d5_sso_row('STUDENT-1', $student),
    r52d5_sso_row('MISSING-1', $missing),
    r52d5_sso_row('10', $excluded),
];

$r52d5LegacyExternalRows = $externalRows;
$legacyOperation = new R52D5Operation(true);
$legacyOperation->ssoRows = $ssoRows;
$legacyOperation->inactiveUids = ['RETURN-1'];
$legacyResult = run_admin_sync_user($legacyOperation, 'R5.2D5 fixture');

$sourceCalls = 0;
$source = new CallableExternalUserSourceAdapter(static function () use (&$sourceCalls, $externalRows): array {
    $sourceCalls++;
    return $externalRows;
});
$dryOperation = new R52D5Operation(false);
$dryOperation->ssoRows = $ssoRows;
$dryOperation->inactiveUids = ['RETURN-1'];
$planner = new SyncPlanner(new LegacySyncPolicyAdapter());
$dryRun = new TestSyncDryRun(
    $source,
    new LegacyOperationPersistenceAdapter($dryOperation),
    $planner
);
$plan = $dryRun->run();

$report($plan instanceof SyncPlan, 'immutable SyncPlan returned');
$reflection = new ReflectionClass(SyncPlan::class);
$readonly = true;
foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
    $readonly = $readonly && $property->isReadOnly();
}
$report($readonly, 'SyncPlan public properties readonly');
$report($sourceCalls === 1, 'external snapshot fetched exactly once');
$report(
    $dryOperation->calls === ['sync_get_all_sso_user', 'sync_get_inactive_user_ids'],
    'zero mutation: only two persistence reads performed'
);

$planLogProjection = array_map(static fn(array $action): array => [
    'u_id' => $action['u_id'],
    'action' => $action['action'],
    'old_data' => $action['old_data'],
    'new_data' => $action['new_data'],
    'changed_fields' => $action['changed_fields'],
], $plan->actions);
$legacyLogProjection = array_map(static fn(array $log): array => [
    'u_id' => $log['u_id'],
    'action' => $log['action'],
    'old_data' => $log['old_data'],
    'new_data' => $log['new_data'],
    'changed_fields' => $log['changed_fields'],
], $legacyOperation->logs);
$report($planLogProjection === $legacyLogProjection, 'planned action/audit parity with legacy');
$report(
    array_column($plan->actions, 'action') === ['DEACTIVATE', 'UPDATE', 'NEW', 'REACTIVATE'],
    'planned action ordering parity'
);
$report(
    $plan->legacyCounts() === [
        'New' => $legacyResult['New'],
        'Update' => $legacyResult['Update'],
        'Deactivate' => $legacyResult['Deactivate'],
        'Reactivate' => $legacyResult['Reactivate'],
    ],
    'planned counts parity with legacy result'
);
$insertActions = array_values(array_filter(
    $plan->actions,
    static fn(array $action): bool => in_array($action['action'], ['NEW', 'REACTIVATE'], true)
));
$report(array_column($insertActions, 'category_id') === [3, 2], 'planned category parity');
$updateActions = array_values(array_filter(
    $plan->actions,
    static fn(array $action): bool => $action['action'] === 'UPDATE'
));
$report(
    count($updateActions) === 1
        && count($legacyOperation->updated) === 1
        && $updateActions[0]['change_hash']
            === $legacyOperation->updated[0][array_key_last($legacyOperation->updated[0])],
    'planned UPDATE change-hash parity'
);
$report(
    array_column($insertActions, 'change_hash') === array_map(
        static fn(array $arguments): mixed => $arguments[array_key_last($arguments)],
        $legacyOperation->inserted
    ),
    'planned INSERT change-hash parity'
);
$report($plan->sourceRows === 6, 'raw external source count recorded');
$report($plan->discardedInvalid === 1, 'invalid external row count recorded');
$report($plan->discardedExcluded === 1, 'excluded external row count recorded');

$secondPlan = $planner->plan($externalRows, $ssoRows, ['RETURN-1']);
$report($secondPlan->planHash() === $plan->planHash(), 'deterministic plan hash');
$safeJson = (string) json_encode($plan->safeProjection());
$report(
    !str_contains($safeJson, 'New User')
        && !str_contains($safeJson, 'RETURN-1')
        && !str_contains($safeJson, 'MISSING-1'),
    'safe projection excludes name and raw UID'
);
$report(count($plan->safeProjection()) === 4, 'safe projection action count');

$emptyOperation = new R52D5Operation(false);
$emptySourceCalls = 0;
$emptyDryRun = new TestSyncDryRun(
    new CallableExternalUserSourceAdapter(static function () use (&$emptySourceCalls): array {
        $emptySourceCalls++;
        return [];
    }),
    new LegacyOperationPersistenceAdapter($emptyOperation),
    $planner
);
$emptyPlan = $emptyDryRun->run();
$report($emptySourceCalls === 1 && $emptyOperation->calls === [], 'empty source performs zero persistence calls');
$report($emptyPlan->actions === [] && array_sum($emptyPlan->legacyCounts()) === 0, 'empty source returns empty plan');

$failureOperation = new R52D5Operation(false);
$upstreamException = false;
$failureDryRun = new TestSyncDryRun(
    new CallableExternalUserSourceAdapter(static function (): array {
        throw new RuntimeException('fixture upstream failure');
    }),
    new LegacyOperationPersistenceAdapter($failureOperation),
    $planner
);
try {
    $failureDryRun->run();
} catch (RuntimeException $exception) {
    $upstreamException = $exception->getMessage() === 'fixture upstream failure';
}
$report($upstreamException, 'upstream exception propagates from dry-run');
$report($failureOperation->calls === [], 'upstream failure performs zero persistence calls');

$productionFiles = [
    'lib/sync_user_runner.php',
    'lib/Database.php',
    'lib/q_func.php',
    'page/dashboard.php',
    'admin/dashboard.php',
];
$testSymbols = ['TestSyncDryRun'];
$references = [];
foreach ($productionFiles as $file) {
    $contents = (string) file_get_contents($projectRoot . '/' . $file);
    foreach ($testSymbols as $symbol) {
        if (str_contains($contents, $symbol)) {
            $references[] = $file . ':' . $symbol;
        }
    }
}
$report($references === [], 'test-only dry-run remains outside production', implode(',', $references));

$runtimeHashes = [
    'lib/sync_user_runner.php' => '965fd187492e1f120b074601746b031474405480f234412e458f64189108c8bb',
    // Current post-M1/S4D runtime checkpoint. Full-sync production wiring
    // remains feature-flagged and approval-bound.
    'lib/Database.php' => '2a9e9c70e0379714658a7eaaa2b60f89aee5f9ccc7f2c62f2083b93250957379',
    'lib/q_func.php' => '71fd6fbb55c35a3c8e81417db919963d815f13146c5d242b4945aaa526c455d1',
];
foreach ($runtimeHashes as $file => $expectedHash) {
    $actualHash = hash_file('sha256', $projectRoot . '/' . $file);
    $report($actualHash === $expectedHash, 'S4D runtime checkpoint: ' . $file, 'sha256=' . $actualHash);
}
$report(!is_file($projectRoot . '/cron/run_sync.php'), 'retired cron absent from runtime');

printf("RESULT checks=%d failed=%d\n", $checks, $failed);
exit($failed === 0 ? 0 : 1);
