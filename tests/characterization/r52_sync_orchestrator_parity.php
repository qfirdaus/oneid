<?php

/**
 * R5.2D3 legacy versus interface-based test orchestrator parity fixture.
 *
 * No database, network, session or filesystem write is performed.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(2);
}

$projectRoot = dirname(__DIR__, 2);
$r52d3ExternalRows = [];
$r52d3ExternalError = false;

function EXTERNAL_DATA_SOURCE_GET_ALL_USER(): array
{
    global $r52d3ExternalRows, $r52d3ExternalError;
    if ($r52d3ExternalError) {
        throw new RuntimeException('fixture upstream failure');
    }
    return $r52d3ExternalRows;
}

require_once $projectRoot . '/lib/auth_security.php';
require_once $projectRoot . '/lib/sync_user_runner.php';
foreach ([
    'app/Sync/Contracts/ExternalUserSourceInterface.php',
    'app/Sync/Contracts/InitialPasswordFactoryInterface.php',
    'app/Sync/Contracts/SyncPolicyInterface.php',
    'app/Sync/Contracts/SyncPersistenceInterface.php',
    'app/Sync/DTO/SyncRunSummary.php',
    'tests/Support/Sync/CallableExternalUserSourceAdapter.php',
    'tests/Support/Sync/CallableInitialPasswordFactoryAdapter.php',
    'tests/Support/Sync/LegacySyncPolicyAdapter.php',
    'tests/Support/Sync/LegacyOperationPersistenceAdapter.php',
    'tests/Support/Sync/TestSyncOrchestrator.php',
] as $file) {
    require_once $projectRoot . '/' . $file;
}

use OneId\Tests\Support\Sync\CallableExternalUserSourceAdapter;
use OneId\Tests\Support\Sync\CallableInitialPasswordFactoryAdapter;
use OneId\Tests\Support\Sync\LegacyOperationPersistenceAdapter;
use OneId\Tests\Support\Sync\LegacySyncPolicyAdapter;
use OneId\Tests\Support\Sync\TestSyncOrchestrator;

function r52d3_row(string $uid, string $name, string $category, string $secondaryId = ''): array
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

function r52d3_sso_row(string $uid, array $row): array
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

final class R52D3OperationSpy
{
    public array $ssoRows = [];
    public array $inactiveUids = [];
    public array $header = [
        'ext_head_id' => 77,
        'ext_head_status' => 0,
        'ext_head_initial_sourcedata' => null,
        'ext_head_uploaded_data' => null,
    ];
    /** @var list<array{0:string,1:array<int,mixed>}> */
    public array $calls = [];
    public bool $throwOnDeactivate = false;

    private function record(string $method, array $arguments = []): void
    {
        $this->calls[] = [$method, $arguments];
    }

    public function beginTransaction(): void
    {
        $this->record(__FUNCTION__);
    }

    public function action_add_new_ext_header($type): int
    {
        $this->record(__FUNCTION__, [$type]);
        return 77;
    }

    public function sync_get_all_sso_user(): array
    {
        $this->record(__FUNCTION__);
        return $this->ssoRows;
    }

    public function sync_get_inactive_user_ids(): array
    {
        $this->record(__FUNCTION__);
        return $this->inactiveUids;
    }

    public function admin_update_user_status($uid, $status): void
    {
        $this->record(__FUNCTION__, [$uid, $status]);
        if ($this->throwOnDeactivate) {
            throw new RuntimeException('fixture rollback trigger');
        }
    }

    public function admin_update_specific_user_info_all_data(...$arguments): void
    {
        $this->record(__FUNCTION__, $arguments);
    }

    public function admin_update_ext_header_status($headerId, $status, $field, $count): void
    {
        $this->record(__FUNCTION__, [$headerId, $status, $field, $count]);
        $this->header['ext_head_status'] = $status;
        $this->header[$field] = $count;
    }

    public function action_add_external_temp_body(...$arguments): int
    {
        $this->record(__FUNCTION__, $arguments);
        return 100 + count(array_filter(
            $this->calls,
            static fn(array $call): bool => $call[0] === 'action_add_external_temp_body'
        )) - 1;
    }

    public function action_add_new_user_from_external_source(...$arguments): void
    {
        $this->record(__FUNCTION__, $arguments);
    }

    public function admin_update_ext_body_status($headerId, $bodyId, $status): void
    {
        $this->record(__FUNCTION__, [$headerId, $bodyId, $status]);
    }

    public function sync_log_change_batch(array $rows): void
    {
        $this->record(__FUNCTION__, [$rows]);
    }

    public function sync_update_header_summary(
        $headerId,
        $new,
        $updated,
        $deactivated,
        $reactivated,
        $triggeredBy
    ): void {
        $this->record(__FUNCTION__, [$headerId, $new, $updated, $deactivated, $reactivated, $triggeredBy]);
    }

    public function commit(): void
    {
        $this->record(__FUNCTION__);
    }

    public function rollback(): void
    {
        $this->record(__FUNCTION__);
    }

    public function action_get_ext_header($headerId): array
    {
        $this->record(__FUNCTION__, [$headerId]);
        return $this->header;
    }
}

/** Normalize only the intentionally non-deterministic initial password hash. */
function r52d3_normalized_calls(array $calls): array
{
    foreach ($calls as &$call) {
        if ($call[0] === 'action_add_new_user_from_external_source' && isset($call[1][2])) {
            $call[1][2] = '<INITIAL_PASSWORD_HASH>';
        }
    }
    unset($call);
    return $calls;
}

function r52d3_new_orchestrator(array $externalRows, R52D3OperationSpy $operation): TestSyncOrchestrator
{
    return new TestSyncOrchestrator(
        new CallableExternalUserSourceAdapter(static fn(): array => $externalRows),
        new LegacyOperationPersistenceAdapter($operation),
        new LegacySyncPolicyAdapter(),
        new CallableInitialPasswordFactoryAdapter(static fn(): string => 'fixture-password-hash')
    );
}

$checks = 0;
$failed = 0;
$report = static function (bool $ok, string $item, string $detail = '') use (&$checks, &$failed): void {
    $checks++;
    if (!$ok) {
        $failed++;
    }
    printf("%s %-60s%s\n", $ok ? 'PASS' : 'FAIL', $item, $detail === '' ? '' : ' ' . $detail);
};

$existingOld = r52d3_row('IC-UPD', 'Old Name', 'Pentadbiran');
$existingNew = r52d3_row('IC-UPD', 'New Name', 'Pentadbiran');
$student = r52d3_row('STUDENT-1', 'Student', 'Pelajar', 'IC-STUDENT');
$missing = r52d3_row('IC-MISSING', 'Missing', 'Akademik');
$excluded = r52d3_row('10', 'Excluded', 'Pentadbiran');
$new = r52d3_row('NEW-1', 'New User', 'Pentadbiran');
$reactivate = r52d3_row('RETURN-1', 'Return User', 'Akademik');
$invalid = r52d3_row('', 'Invalid', 'Pentadbiran');
$externalRows = [$existingNew, $student, $new, $reactivate, $invalid, $excluded];
$ssoRows = [
    r52d3_sso_row('STAFF-1', $existingOld),
    r52d3_sso_row('STUDENT-1', $student),
    r52d3_sso_row('MISSING-1', $missing),
    r52d3_sso_row('10', $excluded),
];

$r52d3ExternalRows = $externalRows;
$legacy = new R52D3OperationSpy();
$legacy->ssoRows = $ssoRows;
$legacy->inactiveUids = ['RETURN-1'];
$legacyResult = run_admin_sync_user($legacy, 'R5.2D3 parity');

$projected = new R52D3OperationSpy();
$projected->ssoRows = $ssoRows;
$projected->inactiveUids = ['RETURN-1'];
$projectedResult = r52d3_new_orchestrator($externalRows, $projected)
    ->run('R5.2D3 parity')
    ->toLegacyArray();

$legacyRawInserts = array_values(array_filter(
    $legacy->calls,
    static fn(array $call): bool => $call[0] === 'action_add_new_user_from_external_source'
));
$projectedRawInserts = array_values(array_filter(
    $projected->calls,
    static fn(array $call): bool => $call[0] === 'action_add_new_user_from_external_source'
));
$report(
    count($legacyRawInserts) === 2
        && count($projectedRawInserts) === 2
        && !in_array('', array_column(array_column($legacyRawInserts, 1), 2), true)
        && !in_array('', array_column(array_column($projectedRawInserts, 1), 2), true),
    'initial password hash supplied by both paths'
);

$legacyCalls = r52d3_normalized_calls($legacy->calls);
$projectedCalls = r52d3_normalized_calls($projected->calls);
$report($projectedCalls === $legacyCalls, 'success exact legacy call-trace parity');
$report($projectedResult === $legacyResult, 'success legacy result projection parity');
$report(
    array_column($projectedCalls, 0) === array_column($legacyCalls, 0),
    'success operation ordering parity'
);
$insertCalls = array_values(array_filter(
    $projectedCalls,
    static fn(array $call): bool => $call[0] === 'action_add_new_user_from_external_source'
));
$report(array_column(array_column($insertCalls, 1), 0) === ['NEW-1', 'RETURN-1'], 'new/reactivate UID parity');
$report(array_column(array_column($insertCalls, 1), 1) === [3, 2], 'category mapping parity');

$r52d3ExternalRows = [];
$legacyEmpty = new R52D3OperationSpy();
$legacyEmptyResult = run_admin_sync_user($legacyEmpty, 'R5.2D3 empty');
$projectedEmpty = new R52D3OperationSpy();
$projectedEmptyResult = r52d3_new_orchestrator([], $projectedEmpty)
    ->run('R5.2D3 empty')
    ->toLegacyArray();
$report($projectedEmpty->calls === $legacyEmpty->calls, 'empty-source call-trace parity');
$report($projectedEmptyResult === $legacyEmptyResult, 'empty-source result parity');

$r52d3ExternalRows = [$new];
$legacyFailure = new R52D3OperationSpy();
$legacyFailure->ssoRows = [r52d3_sso_row('MISSING-1', $missing)];
$legacyFailure->throwOnDeactivate = true;
$legacyException = null;
try {
    run_admin_sync_user($legacyFailure, 'R5.2D3 rollback');
} catch (RuntimeException $exception) {
    $legacyException = $exception->getMessage();
}
$projectedFailure = new R52D3OperationSpy();
$projectedFailure->ssoRows = [r52d3_sso_row('MISSING-1', $missing)];
$projectedFailure->throwOnDeactivate = true;
$projectedException = null;
try {
    r52d3_new_orchestrator([$new], $projectedFailure)->run('R5.2D3 rollback');
} catch (RuntimeException $exception) {
    $projectedException = $exception->getMessage();
}
$report($projectedException === $legacyException, 'mutation exception propagation parity');
$report($projectedFailure->calls === $legacyFailure->calls, 'mutation rollback call-trace parity');

$r52d3ExternalError = true;
$legacyUpstream = new R52D3OperationSpy();
$legacyUpstreamException = null;
try {
    run_admin_sync_user($legacyUpstream, 'R5.2D3 upstream');
} catch (RuntimeException $exception) {
    $legacyUpstreamException = $exception->getMessage();
}
$projectedUpstream = new R52D3OperationSpy();
$projectedUpstreamException = null;
$failingSource = new CallableExternalUserSourceAdapter(static function (): array {
    throw new RuntimeException('fixture upstream failure');
});
$upstreamOrchestrator = new TestSyncOrchestrator(
    $failingSource,
    new LegacyOperationPersistenceAdapter($projectedUpstream),
    new LegacySyncPolicyAdapter(),
    new CallableInitialPasswordFactoryAdapter(static fn(): string => 'unused')
);
try {
    $upstreamOrchestrator->run('R5.2D3 upstream');
} catch (RuntimeException $exception) {
    $projectedUpstreamException = $exception->getMessage();
}
$report($projectedUpstreamException === $legacyUpstreamException, 'upstream exception propagation parity');
$report($projectedUpstream->calls === $legacyUpstream->calls, 'upstream transaction weakness parity');

$productionFiles = [
    'lib/sync_user_runner.php',
    'lib/Database.php',
    'lib/q_func.php',
    'page/dashboard.php',
    'admin/dashboard.php',
];
$references = [];
foreach ($productionFiles as $file) {
    if (str_contains((string) file_get_contents($projectRoot . '/' . $file), 'TestSyncOrchestrator')) {
        $references[] = $file;
    }
}
$report($references === [], 'no production orchestrator wiring', implode(',', $references));

$runtimeHashes = [
    'lib/sync_user_runner.php' => '965fd187492e1f120b074601746b031474405480f234412e458f64189108c8bb',
    'lib/Database.php' => '4657eb89a7d90514cb13b842dba6a4453d1ac320b3fceb5e0d06f2c91426a41a',
    'lib/q_func.php' => '213dadbc6ad77ded818aa92d2e979c0b2b13afc1022b8289c5938b6883905f41',
];
foreach ($runtimeHashes as $file => $expectedHash) {
    $actualHash = hash_file('sha256', $projectRoot . '/' . $file);
    $report($actualHash === $expectedHash, 'S4D runtime checkpoint: ' . $file, 'sha256=' . $actualHash);
}
$report(!is_file($projectRoot . '/cron/run_sync.php'), 'retired cron absent from runtime');

printf("RESULT checks=%d failed=%d\n", $checks, $failed);
exit($failed === 0 ? 0 : 1);
