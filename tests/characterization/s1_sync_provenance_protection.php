<?php

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(2);
}

$fixtureExternalRows = [];
function EXTERNAL_DATA_SOURCE_GET_ALL_USER(): array
{
    global $fixtureExternalRows;
    return $fixtureExternalRows;
}

require_once dirname(__DIR__, 2) . '/lib/auth_security.php';
require_once dirname(__DIR__, 2) . '/lib/sync_user_runner.php';

function s1_sync_row(string $uid, string $name, string $category = 'Pentadbiran'): array
{
    $row = ['ext_data_source_category' => $category];
    for ($index = 1; $index <= 12; $index++) {
        $row['data' . $index] = '';
    }
    $row['data1'] = $name;
    $row['data4'] = $uid;
    return $row;
}

function s1_sso_row(string $uid, string $name, string $source, int $protected): array
{
    $row = s1_sync_row($uid, $name);
    $row['u_id'] = $uid;
    $row['source'] = '1';
    $row['account_source'] = $source;
    $row['sync_protected'] = $protected;
    $row['u_changes_hash'] = sync_compute_hash(
        $row['data1'], $row['data2'], $row['data3'], $row['data4'],
        $row['data5'], $row['data6'], $row['data7'], $row['data8'],
        $row['data9'], $row['data10'], $row['data11'], $row['data12'],
        $row['ext_data_source_category']
    );
    return $row;
}

final class S1SyncProtectionOperation
{
    public array $ssoRows = [];
    public array $deactivated = [];
    public array $inserted = [];
    public array $logs = [];
    public bool $committed = false;

    public function beginTransaction(): void {}
    public function action_add_new_ext_header($type): int { return 91; }
    public function sync_get_all_sso_user(): array { return $this->ssoRows; }
    public function sync_get_inactive_user_ids(): array { return []; }
    public function admin_update_user_status($uid, $status): void { $this->deactivated[] = [$uid, $status]; }
    public function admin_update_specific_user_info_all_data(...$arguments): void {}
    public function admin_update_ext_header_status($headerId, $status, $field, $count): void {}
    public function action_add_external_temp_body(...$arguments): int { return 200 + count($this->inserted); }
    public function action_add_new_user_from_external_source(...$arguments): void { $this->inserted[] = $arguments; }
    public function admin_update_ext_body_status($headerId, $bodyId, $status): void {}
    public function sync_log_change_batch(array $rows): void { $this->logs = $rows; }
    public function sync_update_header_summary(...$arguments): void {}
    public function commit(): void { $this->committed = true; }
    public function rollback(): void {}
    public function action_get_ext_header($headerId): array
    {
        return ['ext_head_id' => $headerId, 'ext_head_status' => 2, 'ext_head_uploaded_data' => count($this->inserted)];
    }
}

$fixtureExternalRows = [
    s1_sync_row('MANUAL-1', 'External Collision'),
    s1_sync_row('NEW-1', 'New External User'),
];
$operation = new S1SyncProtectionOperation();
$operation->ssoRows = [
    s1_sso_row('MANUAL-1', 'Protected Manual User', 'manual', 1),
    s1_sso_row('MISSING-1', 'Missing External User', 'external', 0),
];

$result = run_admin_sync_user($operation, 'S1 fixture');
$checks = 0;
$failed = 0;
$report = static function (bool $ok, string $item) use (&$checks, &$failed): void {
    $checks++;
    if (!$ok) $failed++;
    printf("%s %s\n", $ok ? 'PASS' : 'FAIL', $item);
};

$deactivatedIds = array_map(static fn(array $row): string => (string) $row[0], $operation->deactivated);
$insertedIds = array_map(static fn(array $row): string => (string) $row[0], $operation->inserted);
$logIds = array_column($operation->logs, 'u_id');

$report($operation->committed, 'protected fixture commits');
$report(!in_array('MANUAL-1', $deactivatedIds, true), 'protected manual account is not deactivated');
$report(!in_array('MANUAL-1', $insertedIds, true), 'colliding external row does not overwrite manual account');
$report(!in_array('MANUAL-1', $logIds, true), 'protected manual account produces no external mutation audit');
$report($deactivatedIds === ['MISSING-1'], 'unprotected missing external account still deactivates');
$report($insertedIds === ['NEW-1'], 'non-colliding external account still inserts');
$report($result['Deactivate'] === 1 && $result['New'] === 1, 'summary excludes protected manual collision');

printf("RESULT checks=%d failed=%d\n", $checks, $failed);
exit($failed === 0 ? 0 : 1);

