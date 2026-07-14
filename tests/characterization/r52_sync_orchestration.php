<?php

/**
 * In-memory characterization of run_admin_sync_user.
 * No database, network, session or filesystem write is performed.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(2);
}

$fixtureExternalRows = [];
$fixtureExternalError = false;

function EXTERNAL_DATA_SOURCE_GET_ALL_USER(): array
{
    global $fixtureExternalRows, $fixtureExternalError;
    if ($fixtureExternalError) {
        throw new RuntimeException('fixture upstream failure');
    }
    return $fixtureExternalRows;
}

require_once dirname(__DIR__, 2) . '/lib/auth_security.php';
require_once dirname(__DIR__, 2) . '/lib/sync_user_runner.php';

function r52_sync_row(string $uid, string $name, string $category, string $secondaryId = ''): array
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

function r52_sync_sso_row(string $uid, array $row): array
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

final class R52SyncFakeOperation
{
    public array $ssoRows = [];
    public array $inactiveUids = [];
    public array $deactivated = [];
    public array $updated = [];
    public array $staged = [];
    public array $inserted = [];
    public array $bodyStatuses = [];
    public array $logs = [];
    public array $summary = [];
    public array $header = [
        'ext_head_id' => 77,
        'ext_head_status' => 0,
        'ext_head_initial_sourcedata' => null,
        'ext_head_uploaded_data' => null,
    ];
    public bool $began = false;
    public bool $committed = false;
    public bool $rolledBack = false;
    public bool $throwOnDeactivate = false;

    public function beginTransaction(): void
    {
        $this->began = true;
    }

    public function action_add_new_ext_header($type): int
    {
        return 77;
    }

    public function sync_get_all_sso_user(): array
    {
        return $this->ssoRows;
    }

    public function sync_get_inactive_user_ids(): array
    {
        return $this->inactiveUids;
    }

    public function admin_update_user_status($uid, $status): void
    {
        if ($this->throwOnDeactivate) {
            throw new RuntimeException('fixture rollback trigger');
        }
        $this->deactivated[] = [$uid, $status];
    }

    public function admin_update_specific_user_info_all_data(...$arguments): void
    {
        $this->updated[] = $arguments;
    }

    public function admin_update_ext_header_status($headerId, $status, $field, $count): void
    {
        $this->header['ext_head_status'] = $status;
        $this->header[$field] = $count;
    }

    public function action_add_external_temp_body(...$arguments): int
    {
        $id = 100 + count($this->staged);
        $this->staged[] = $arguments;
        return $id;
    }

    public function action_add_new_user_from_external_source(...$arguments): void
    {
        $this->inserted[] = $arguments;
    }

    public function admin_update_ext_body_status($headerId, $bodyId, $status): void
    {
        $this->bodyStatuses[] = [$headerId, $bodyId, $status];
    }

    public function sync_log_change_batch(array $rows): void
    {
        $this->logs = $rows;
    }

    public function sync_update_header_summary(
        $headerId,
        $new,
        $updated,
        $deactivated,
        $reactivated,
        $triggeredBy
    ): void {
        $this->summary = compact('headerId', 'new', 'updated', 'deactivated', 'reactivated', 'triggeredBy');
    }

    public function commit(): void
    {
        $this->committed = true;
    }

    public function rollback(): void
    {
        $this->rolledBack = true;
    }

    public function action_get_ext_header($headerId): array
    {
        return $this->header;
    }
}

$checks = 0;
$failed = 0;
$report = static function (bool $ok, string $item) use (&$checks, &$failed): void {
    $checks++;
    if (!$ok) {
        $failed++;
    }
    printf("%s %s\n", $ok ? 'PASS' : 'FAIL', $item);
};

$existingOld = r52_sync_row('IC-UPD', 'Old Name', 'Pentadbiran');
$existingNew = r52_sync_row('IC-UPD', 'New Name', 'Pentadbiran');
$student = r52_sync_row('STUDENT-1', 'Student', 'Pelajar', 'IC-STUDENT');
$missing = r52_sync_row('IC-MISSING', 'Missing', 'Akademik');
$excluded = r52_sync_row('10', 'Excluded', 'Pentadbiran');
$new = r52_sync_row('NEW-1', 'New User', 'Pentadbiran');
$reactivate = r52_sync_row('RETURN-1', 'Return User', 'Akademik');
$invalid = r52_sync_row('', 'Invalid', 'Pentadbiran');

$fixtureExternalRows = [$existingNew, $student, $new, $reactivate, $invalid, $excluded];
$operation = new R52SyncFakeOperation();
$operation->ssoRows = [
    r52_sync_sso_row('STAFF-1', $existingOld),
    r52_sync_sso_row('STUDENT-1', $student),
    r52_sync_sso_row('MISSING-1', $missing),
    r52_sync_sso_row('10', $excluded),
];
$operation->inactiveUids = ['RETURN-1'];

$result = run_admin_sync_user($operation, 'R5.2D0 fixture');
$report($operation->began && $operation->committed && !$operation->rolledBack, 'success transaction commits');
$report($operation->deactivated === [['MISSING-1', 0]], 'missing SSO user deactivated');
$report(count($operation->updated) === 1 && $operation->updated[0][0] === 'STAFF-1', 'changed matched user updated');
$report(count($operation->staged) === 2, 'new and reactivated rows staged');
$report(
    array_map(static fn(array $row): string => (string) $row[0], $operation->inserted) === ['NEW-1', 'RETURN-1'],
    'new and reactivated users inserted in order'
);
$report(
    array_map(static fn(array $row): int => (int) $row[1], $operation->inserted) === [3, 2],
    'external categories mapped to legacy category IDs'
);
$report(
    array_column($operation->logs, 'action') === ['DEACTIVATE', 'UPDATE', 'NEW', 'REACTIVATE'],
    'audit action ordering contract'
);
$report(
    $operation->summary === [
        'headerId' => 77,
        'new' => 1,
        'updated' => 1,
        'deactivated' => 1,
        'reactivated' => 1,
        'triggeredBy' => 'R5.2D0 fixture',
    ],
    'summary count contract'
);
$report(
    $result['New'] === 1
        && $result['Update'] === 1
        && $result['Deactivate'] === 1
        && $result['Reactivate'] === 1,
    'returned count contract'
);
$report(
    !in_array('10', array_column($operation->inserted, 0), true)
        && !in_array('10', array_column($operation->deactivated, 0), true),
    'hardcoded excluded UID omitted'
);
$report(count($operation->bodyStatuses) === 2, 'staged rows marked processed');

$fixtureExternalRows = [];
$emptyOperation = new R52SyncFakeOperation();
$emptyResult = run_admin_sync_user($emptyOperation, 'R5.2D0 empty fixture');
$report($emptyOperation->committed && !$emptyOperation->rolledBack, 'empty-source transaction commits');
$report($emptyOperation->header['ext_head_status'] === 3, 'empty-source header status contract');
$report(
    $emptyResult['New'] === 0
        && $emptyResult['Update'] === 0
        && $emptyResult['Deactivate'] === 0
        && $emptyResult['Reactivate'] === 0,
    'empty-source count contract'
);

$fixtureExternalRows = [$new];
$rollbackOperation = new R52SyncFakeOperation();
$rollbackOperation->ssoRows = [r52_sync_sso_row('MISSING-1', $missing)];
$rollbackOperation->throwOnDeactivate = true;
$exceptionPropagated = false;
try {
    run_admin_sync_user($rollbackOperation, 'R5.2D0 rollback fixture');
} catch (RuntimeException $exception) {
    $exceptionPropagated = $exception->getMessage() === 'fixture rollback trigger';
}
$report($exceptionPropagated, 'orchestration exception propagates');
$report($rollbackOperation->rolledBack && !$rollbackOperation->committed, 'failure transaction rolls back');

$fixtureExternalError = true;
$upstreamOperation = new R52SyncFakeOperation();
$upstreamException = false;
try {
    run_admin_sync_user($upstreamOperation, 'R5.2D0 upstream fixture');
} catch (RuntimeException $exception) {
    $upstreamException = $exception->getMessage() === 'fixture upstream failure';
}
$report($upstreamException && $upstreamOperation->began, 'upstream exception occurs after transaction begins');
$report(
    !$upstreamOperation->rolledBack && !$upstreamOperation->committed,
    'legacy upstream exception bypasses rollback boundary'
);

printf("RESULT checks=%d failed=%d\n", $checks, $failed);
exit($failed === 0 ? 0 : 1);
