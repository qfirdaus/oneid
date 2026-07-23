<?php

/** R5.2D8 dormant production orchestrator versus legacy full parity. */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(2);
}

$projectRoot = dirname(__DIR__, 2);
$r52d8ExternalRows = [];
$r52d8ExternalFailure = false;

function EXTERNAL_DATA_SOURCE_GET_ALL_USER(): array
{
    global $r52d8ExternalRows, $r52d8ExternalFailure;
    if ($r52d8ExternalFailure) {
        throw new RuntimeException('D8 upstream failure');
    }
    return $r52d8ExternalRows;
}

require_once $projectRoot . '/lib/auth_security.php';
require_once $projectRoot . '/lib/sync_user_runner.php';
foreach ([
    'app/Sync/Contracts/ExternalUserSourceInterface.php',
    'app/Sync/Contracts/InitialPasswordFactoryInterface.php',
    'app/Sync/Contracts/SyncPolicyInterface.php',
    'app/Sync/Contracts/SyncPersistenceInterface.php',
    'app/Sync/DTO/SyncPlan.php',
    'app/Sync/DTO/SyncRunSummary.php',
    'app/Sync/SyncPlanner.php',
    'app/Sync/SyncOrchestrator.php',
    'app/Sync/Adapters/ExternalApiUserSource.php',
    'app/Sync/Adapters/SecureInitialPasswordFactory.php',
    'app/Sync/Adapters/LegacySyncPolicy.php',
    'app/Sync/Adapters/DatabaseSyncPersistenceAdapter.php',
] as $file) {
    require_once $projectRoot . '/' . $file;
}

use OneId\App\Sync\Adapters\DatabaseSyncPersistenceAdapter;
use OneId\App\Sync\Adapters\ExternalApiUserSource;
use OneId\App\Sync\Adapters\LegacySyncPolicy;
use OneId\App\Sync\Adapters\SecureInitialPasswordFactory;
use OneId\App\Sync\SyncOrchestrator;
use OneId\App\Sync\SyncPlanner;

function r52d8_row(string $uid, string $name, string $category, string $secondaryId = ''): array
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

function r52d8_sso_row(string $uid, array $row): array
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

final class R52D8OperationSpy
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
    private int $staged = 0;

    public function __call(string $name, array $arguments): mixed
    {
        $this->calls[] = [$name, $arguments];

        if ($name === 'admin_update_user_status' && $this->throwOnDeactivate) {
            throw new RuntimeException('D8 mutation failure');
        }
        if ($name === 'admin_update_ext_header_status') {
            $this->header['ext_head_status'] = $arguments[1];
            $this->header[$arguments[2]] = $arguments[3];
        }

        return match ($name) {
            'action_add_new_ext_header' => 77,
            'sync_get_all_sso_user' => $this->ssoRows,
            'sync_get_inactive_user_ids' => $this->inactiveUids,
            'action_add_external_temp_body' => 100 + $this->staged++,
            'action_get_ext_header' => $this->header,
            default => null,
        };
    }
}

function r52d8_orchestrator(R52D8OperationSpy $operation): SyncOrchestrator
{
    return new SyncOrchestrator(
        new ExternalApiUserSource(),
        new DatabaseSyncPersistenceAdapter($operation),
        new SyncPlanner(new LegacySyncPolicy()),
        new SecureInitialPasswordFactory()
    );
}

/** Normalize only random initial-password hash arguments. */
function r52d8_normalize_calls(array $calls): array
{
    foreach ($calls as &$call) {
        if ($call[0] === 'action_add_new_user_from_external_source' && isset($call[1][2])) {
            $call[1][2] = '<INITIAL_PASSWORD_HASH>';
        }
    }
    unset($call);
    return $calls;
}

function r52d8_insert_passwords(array $calls): array
{
    $hashes = [];
    foreach ($calls as $call) {
        if ($call[0] === 'action_add_new_user_from_external_source') {
            $hashes[] = $call[1][2] ?? '';
        }
    }
    return $hashes;
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

$existingOld = r52d8_row('IC-UPD', 'Old Name', 'Pentadbiran');
$existingNew = r52d8_row('IC-UPD', 'New Name', 'Pentadbiran');
$student = r52d8_row('STUDENT-1', 'Student', 'Pelajar', 'IC-STUDENT');
$missing = r52d8_row('IC-MISSING', 'Missing', 'Akademik');
$excluded = r52d8_row('10', 'Excluded', 'Pentadbiran');
$new = r52d8_row('NEW-1', 'New User', 'Pentadbiran');
$reactivate = r52d8_row('RETURN-1', 'Return User', 'Akademik');
$invalid = r52d8_row('', 'Invalid', 'Pentadbiran');
$r52d8ExternalRows = [$existingNew, $student, $new, $reactivate, $invalid, $excluded];
$ssoRows = [
    r52d8_sso_row('STAFF-1', $existingOld),
    r52d8_sso_row('STUDENT-1', $student),
    r52d8_sso_row('MISSING-1', $missing),
    r52d8_sso_row('10', $excluded),
];

$legacy = new R52D8OperationSpy();
$legacy->ssoRows = $ssoRows;
$legacy->inactiveUids = ['RETURN-1'];
$legacyResult = run_admin_sync_user($legacy, 'R5.2D8 parity');
$modern = new R52D8OperationSpy();
$modern->ssoRows = $ssoRows;
$modern->inactiveUids = ['RETURN-1'];
$modernResult = r52d8_orchestrator($modern)->run('R5.2D8 parity')->toLegacyArray();

$legacyPasswords = r52d8_insert_passwords($legacy->calls);
$modernPasswords = r52d8_insert_passwords($modern->calls);
$report(
    count($legacyPasswords) === 2
        && count($modernPasswords) === 2
        && !in_array('', $legacyPasswords, true)
        && !in_array('', $modernPasswords, true),
    'both success paths supply initial password hashes'
);
$report(
    r52d8_normalize_calls($modern->calls) === r52d8_normalize_calls($legacy->calls),
    'success exact persistence call-trace parity'
);
$report($modernResult === $legacyResult, 'success legacy result parity');
$report(
    array_keys($modernResult) === array_keys($legacyResult)
        && count(array_intersect(
            ['ext_head_id', 'New', 'Update', 'Deactivate', 'Reactivate'],
            array_keys($modernResult)
        )) === 5,
    'result shape remains associative legacy contract'
);

$r52d8ExternalRows = [];
$legacyEmpty = new R52D8OperationSpy();
$legacyEmptyResult = run_admin_sync_user($legacyEmpty, 'R5.2D8 empty');
$modernEmpty = new R52D8OperationSpy();
$modernEmptyResult = r52d8_orchestrator($modernEmpty)->run('R5.2D8 empty')->toLegacyArray();
$report($modernEmpty->calls === $legacyEmpty->calls, 'empty-source exact call-trace parity');
$report($modernEmptyResult === $legacyEmptyResult, 'empty-source result parity');

$unchanged = r52d8_row('SAME-1', 'Same User', 'Pentadbiran');
$r52d8ExternalRows = [$unchanged];
$unchangedSso = [r52d8_sso_row('SAME-1', $unchanged)];
$legacyNoPending = new R52D8OperationSpy();
$legacyNoPending->ssoRows = $unchangedSso;
$legacyNoPendingResult = run_admin_sync_user($legacyNoPending, 'R5.2D8 no-pending');
$modernNoPending = new R52D8OperationSpy();
$modernNoPending->ssoRows = $unchangedSso;
$modernNoPendingResult = r52d8_orchestrator($modernNoPending)
    ->run('R5.2D8 no-pending')
    ->toLegacyArray();
$report($modernNoPending->calls === $legacyNoPending->calls, 'no-pending exact call-trace parity');
$report($modernNoPendingResult === $legacyNoPendingResult, 'no-pending result parity');
$report($modernNoPendingResult['ext_head_status'] === 4, 'no-pending header status parity');

$r52d8ExternalRows = [$new];
$failureSso = [r52d8_sso_row('MISSING-1', $missing)];
$legacyFailure = new R52D8OperationSpy();
$legacyFailure->ssoRows = $failureSso;
$legacyFailure->throwOnDeactivate = true;
$legacyFailureMessage = null;
try {
    run_admin_sync_user($legacyFailure, 'R5.2D8 mutation');
} catch (RuntimeException $exception) {
    $legacyFailureMessage = $exception->getMessage();
}
$modernFailure = new R52D8OperationSpy();
$modernFailure->ssoRows = $failureSso;
$modernFailure->throwOnDeactivate = true;
$modernFailureMessage = null;
try {
    r52d8_orchestrator($modernFailure)->run('R5.2D8 mutation');
} catch (RuntimeException $exception) {
    $modernFailureMessage = $exception->getMessage();
}
$report($modernFailureMessage === $legacyFailureMessage, 'mutation exception parity');
$report($modernFailure->calls === $legacyFailure->calls, 'mutation rollback call-trace parity');

$r52d8ExternalFailure = true;
$legacyUpstream = new R52D8OperationSpy();
$legacyUpstreamMessage = null;
try {
    run_admin_sync_user($legacyUpstream, 'R5.2D8 upstream');
} catch (RuntimeException $exception) {
    $legacyUpstreamMessage = $exception->getMessage();
}
$modernUpstream = new R52D8OperationSpy();
$modernUpstreamMessage = null;
try {
    r52d8_orchestrator($modernUpstream)->run('R5.2D8 upstream');
} catch (RuntimeException $exception) {
    $modernUpstreamMessage = $exception->getMessage();
}
$report($modernUpstreamMessage === $legacyUpstreamMessage, 'upstream exception parity');
$report($modernUpstream->calls === $legacyUpstream->calls, 'upstream transaction weakness parity');
$r52d8ExternalFailure = false;

$productionFiles = [
    'lib/sync_user_runner.php',
    'lib/Database.php',
    'lib/q_func.php',
    'page/dashboard.php',
    'admin/dashboard.php',
];
$references = [];
foreach ($productionFiles as $file) {
    if (str_contains((string) file_get_contents($projectRoot . '/' . $file), 'SyncOrchestrator')) {
        $references[] = $file;
    }
}
$report($references === [], 'no production orchestrator wiring', implode(',', $references));

$runtimeHashes = [
    'lib/sync_user_runner.php' => '965fd187492e1f120b074601746b031474405480f234412e458f64189108c8bb',
    'lib/Database.php' => '71b51b7a9443bc3b83361be8b80c2ea464694af5454bbb38bfb80ad6ab3a1cce',
    'lib/q_func.php' => '308b1e581eb9f876fc9cd5a2e2562dcf1b1faf521725843d88702c2bfbbe6257',
];
foreach ($runtimeHashes as $file => $expectedHash) {
    $actualHash = hash_file('sha256', $projectRoot . '/' . $file);
    $report($actualHash === $expectedHash, 'S4D runtime checkpoint: ' . $file, 'sha256=' . $actualHash);
}
$report(!is_file($projectRoot . '/cron/run_sync.php'), 'retired cron absent from runtime');

printf("RESULT checks=%d failed=%d\n", $checks, $failed);
exit($failed === 0 ? 0 : 1);
