<?php

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(2);
}

$root = dirname(__DIR__, 2);
require_once $root . '/app/Sync/SyncDataTransformer.php';
require_once $root . '/app/User/Contracts/UserResyncApprovalStoreInterface.php';
require_once $root . '/app/User/UserResyncException.php';
require_once $root . '/app/User/UserResyncService.php';

use OneId\App\User\Contracts\UserResyncApprovalStoreInterface;
use OneId\App\User\UserResyncException;
use OneId\App\User\UserResyncService;

final class M1MemoryApprovalStore implements UserResyncApprovalStoreInterface
{
    public array $records = [];

    public function save(array $approval): void
    {
        $this->records[$approval['approval_id']] = $approval;
    }

    public function consume(string $approvalId): ?array
    {
        $record = $this->records[$approvalId] ?? null;
        unset($this->records[$approvalId]);
        return $record;
    }
}

final class M1FakeOperation
{
    public array $current;
    public array $calls = [];
    public bool $transaction = false;
    public int $updateResult = 1;
    public int $auditResult = 1;

    public function __construct(array $current)
    {
        $this->current = $current;
    }

    public function admin_get_user_for_resync(string $userId, bool $forUpdate = false): array
    {
        $this->calls[] = ['read', $userId, $forUpdate];
        return $this->current;
    }

    public function beginTransaction(): void
    {
        $this->transaction = true;
        $this->calls[] = ['begin'];
    }

    public function commit(): void
    {
        $this->transaction = false;
        $this->calls[] = ['commit'];
    }

    public function rollback(): void
    {
        $this->transaction = false;
        $this->calls[] = ['rollback'];
    }

    public function admin_update_specific_user_info_all_data(...$arguments): int
    {
        $this->calls[] = ['update', $arguments];
        return $this->updateResult;
    }

    public function syslog_record($type, $detail, $ip): int
    {
        $this->calls[] = ['audit', $type, $detail, $ip];
        return $this->auditResult;
    }
}

function m1_current(array $overrides = []): array
{
    $base = [
        'u_id' => '900101011234',
        'u_category' => '3',
        'u_type' => '0',
        'avail_status' => '1',
        'u_changes_hash' => 'old-hash',
        'account_source' => 'external',
        'sync_protected' => '0',
        'data1' => 'Old Name',
        'data2' => 'EMP1',
        'data3' => '0530-09',
        'data4' => '900101011234',
        'data5' => 'old@example.test',
        'data6' => 'Old Department',
        'data7' => 'Old Position',
        'data8' => '', 'data9' => '', 'data10' => '', 'data11' => '', 'data12' => '',
    ];
    return array_replace($base, $overrides);
}

function m1_external(array $overrides = []): array
{
    $base = [
        'data1' => 'New Name',
        'data2' => 'EMP1',
        'data3' => '0530-09',
        'data4' => '900101011234',
        'data5' => 'new@example.test',
        'data6' => 'New Department',
        'data7' => 'New Position',
        'data8' => '', 'data9' => '', 'data10' => '', 'data11' => '', 'data12' => '',
        'ext_data_source_category' => 'Staf Pentadbiran',
    ];
    return array_replace($base, $overrides);
}

$checks = 0;
$failed = 0;
$report = static function (bool $ok, string $label) use (&$checks, &$failed): void {
    $checks++;
    if (!$ok) $failed++;
    printf("%s %s\n", $ok ? 'PASS' : 'FAIL', $label);
};
$reason = static function (callable $callback): string {
    try {
        $callback();
    } catch (UserResyncException $exception) {
        return $exception->reason;
    }
    return '';
};

$operation = new M1FakeOperation(m1_current());
$store = new M1MemoryApprovalStore();
$sourceRows = [m1_external()];
$sourceCalls = 0;
$sourceFamilies = [];
$service = new UserResyncService(
    $operation,
    static function (string $userId, string $sourceFamily) use (&$sourceRows, &$sourceCalls, &$sourceFamilies): array {
        $sourceCalls++;
        $sourceFamilies[] = $sourceFamily;
        return $sourceRows;
    },
    $store
);

$preview = $service->preview('900101011234', 'ADMIN1');
$report($preview['status'] === 1 && $preview['code'] === 'PREVIEW_READY', 'changed external profile produces ready preview');
$report($preview['can_apply'] === true && $preview['change_count'] === 4, 'preview reports exact changed-field count');
$report(count($store->records) === 1 && count($operation->calls) === 1, 'preview stores approval with zero mutation');
$report($sourceFamilies === ['staff'], 'staff profile reads only the staff external source family');
$identityChange = array_values(array_filter($preview['changes'], static fn($item) => $item['field'] === 'data4'));
$report($identityChange === [], 'unchanged primary identity is omitted from diff');
$report(!str_contains(json_encode($preview), '900101011234'), 'preview response does not expose full primary identity');

$apply = $service->apply($preview['approval_id'], 'ADMIN1', '127.0.0.1');
$report($apply['status'] === 1 && $apply['code'] === 'RESYNC_APPLIED', 'approved matching preview applies successfully');
$callNames = array_column($operation->calls, 0);
$report(in_array('begin', $callNames, true) && in_array('update', $callNames, true) && in_array('audit', $callNames, true) && in_array('commit', $callNames, true), 'apply performs transaction update audit and commit');
$report(!in_array('rollback', $callNames, true) && $operation->transaction === false, 'successful apply does not rollback or leak transaction');
$auditCalls = array_values(array_filter($operation->calls, static fn($call) => $call[0] === 'audit'));
$report(($auditCalls[0][1] ?? null) === 24 && str_contains($auditCalls[0][2] ?? '', 'correlation='), 'apply writes correlated audit event 24');
$report($reason(static fn() => $service->apply($preview['approval_id'], 'ADMIN1', '127.0.0.1')) === 'RESYNC_APPROVAL_NOT_AVAILABLE', 'approval is one-time and cannot be replayed');

$studentCurrent = m1_current(['u_id' => '12345', 'data2' => '991122334455', 'data3' => '', 'data4' => '12345']);
$studentExternal = m1_external(['data2' => '991122334455', 'data3' => '', 'data4' => '12345', 'ext_data_source_category' => 'Pelajar']);
$studentFamily = '';
$studentService = new UserResyncService(
    new M1FakeOperation($studentCurrent),
    static function (string $userId, string $sourceFamily) use ($studentExternal, &$studentFamily): array {
        $studentFamily = $sourceFamily;
        return [$studentExternal];
    },
    new M1MemoryApprovalStore()
);
$studentService->preview('12345', 'ADMIN1');
$report($studentFamily === 'student', 'student profile reads only the student external source family');

$same = m1_external([
    'data1' => 'Old Name',
    'data5' => 'old@example.test',
    'data6' => 'Old Department',
    'data7' => 'Old Position',
]);
$sameOperation = new M1FakeOperation(m1_current());
$sameStore = new M1MemoryApprovalStore();
$sameService = new UserResyncService($sameOperation, static fn() => [$same], $sameStore);
$samePreview = $sameService->preview('900101011234', 'ADMIN1');
$report($samePreview['code'] === 'NO_CHANGES' && $samePreview['can_apply'] === false, 'identical profile produces no-change preview');
$report($sameStore->records === [], 'no-change preview issues no approval');

$zeroService = new UserResyncService(new M1FakeOperation(m1_current()), static fn() => [], new M1MemoryApprovalStore());
$report($reason(static fn() => $zeroService->preview('900101011234', 'ADMIN1')) === 'RESYNC_EXTERNAL_USER_NOT_FOUND', 'zero external rows fail closed');
$manyService = new UserResyncService(new M1FakeOperation(m1_current()), static fn() => [m1_external(), m1_external()], new M1MemoryApprovalStore());
$report($reason(static fn() => $manyService->preview('900101011234', 'ADMIN1')) === 'RESYNC_EXTERNAL_USER_AMBIGUOUS', 'multiple external rows fail closed');
$mismatchService = new UserResyncService(new M1FakeOperation(m1_current()), static fn() => [m1_external(['data4' => 'OTHER'])], new M1MemoryApprovalStore());
$report($reason(static fn() => $mismatchService->preview('900101011234', 'ADMIN1')) === 'RESYNC_EXTERNAL_IDENTITY_MISMATCH', 'external identity mismatch fails closed');

$manualOperation = new M1FakeOperation(m1_current(['account_source' => 'manual', 'sync_protected' => 1]));
$manualSourceCalls = 0;
$manualService = new UserResyncService($manualOperation, static function () use (&$manualSourceCalls): array {
    $manualSourceCalls++;
    return [m1_external()];
}, new M1MemoryApprovalStore());
$report($reason(static fn() => $manualService->preview('900101011234', 'ADMIN1')) === 'RESYNC_MANUAL_PROTECTED', 'protected manual account cannot be resynced');
$report($manualSourceCalls === 0, 'protected manual account is rejected before external lookup');
$inactiveService = new UserResyncService(new M1FakeOperation(m1_current(['avail_status' => 0])), static fn() => [m1_external()], new M1MemoryApprovalStore());
$report($reason(static fn() => $inactiveService->preview('900101011234', 'ADMIN1')) === 'RESYNC_USER_INACTIVE', 'inactive account cannot be resynced');
$sourceFailureService = new UserResyncService(new M1FakeOperation(m1_current()), static function (): array { throw new RuntimeException('fixture source failure'); }, new M1MemoryApprovalStore());
$report($reason(static fn() => $sourceFailureService->preview('900101011234', 'ADMIN1')) === 'RESYNC_SOURCE_UNAVAILABLE', 'source exception is converted to safe failure code');

$report($reason(static fn() => $service->apply('invalid', 'ADMIN1', '127.0.0.1')) === 'RESYNC_APPROVAL_INVALID', 'malformed approval is rejected');
$expiredOperation = new M1FakeOperation(m1_current());
$expiredStore = new M1MemoryApprovalStore();
$expiredService = new UserResyncService($expiredOperation, static fn() => [m1_external()], $expiredStore);
$expiredPreview = $expiredService->preview('900101011234', 'ADMIN1');
$expiredStore->records[$expiredPreview['approval_id']]['expires_at'] = time() - 1;
$report($reason(static fn() => $expiredService->apply($expiredPreview['approval_id'], 'ADMIN1', '127.0.0.1')) === 'RESYNC_APPROVAL_EXPIRED', 'expired approval is rejected before transaction');
$adminMismatchOperation = new M1FakeOperation(m1_current());
$adminMismatchStore = new M1MemoryApprovalStore();
$adminMismatchService = new UserResyncService($adminMismatchOperation, static fn() => [m1_external()], $adminMismatchStore);
$adminMismatchPreview = $adminMismatchService->preview('900101011234', 'ADMIN1');
$report($reason(static fn() => $adminMismatchService->apply($adminMismatchPreview['approval_id'], 'ADMIN2', '127.0.0.1')) === 'RESYNC_APPROVAL_ADMIN_MISMATCH', 'approval is bound to issuing administrator');

$changedRows = [m1_external()];
$mismatchOperation = new M1FakeOperation(m1_current());
$mismatchStore = new M1MemoryApprovalStore();
$mismatchApplyService = new UserResyncService($mismatchOperation, static function () use (&$changedRows): array { return $changedRows; }, $mismatchStore);
$mismatchPreview = $mismatchApplyService->preview('900101011234', 'ADMIN1');
$changedRows = [m1_external(['data1' => 'Changed after preview'])];
$report($reason(static fn() => $mismatchApplyService->apply($mismatchPreview['approval_id'], 'ADMIN1', '127.0.0.1')) === 'RESYNC_PREVIEW_MISMATCH', 'changed source after preview is rejected');
$report(in_array('rollback', array_column($mismatchOperation->calls, 0), true), 'preview mismatch rolls back transaction');
$report(!in_array('update', array_column($mismatchOperation->calls, 0), true), 'preview mismatch performs zero profile update');

$auditFailOperation = new M1FakeOperation(m1_current());
$auditFailOperation->auditResult = 0;
$auditFailStore = new M1MemoryApprovalStore();
$auditFailService = new UserResyncService($auditFailOperation, static fn() => [m1_external()], $auditFailStore);
$auditFailPreview = $auditFailService->preview('900101011234', 'ADMIN1');
$report($reason(static fn() => $auditFailService->apply($auditFailPreview['approval_id'], 'ADMIN1', '127.0.0.1')) === 'RESYNC_AUDIT_NOT_WRITTEN', 'audit failure rejects apply');
$report(in_array('rollback', array_column($auditFailOperation->calls, 0), true), 'audit failure rolls back profile update transaction');

$updateFailOperation = new M1FakeOperation(m1_current());
$updateFailOperation->updateResult = 0;
$updateFailStore = new M1MemoryApprovalStore();
$updateFailService = new UserResyncService($updateFailOperation, static fn() => [m1_external()], $updateFailStore);
$updateFailPreview = $updateFailService->preview('900101011234', 'ADMIN1');
$report($reason(static fn() => $updateFailService->apply($updateFailPreview['approval_id'], 'ADMIN1', '127.0.0.1')) === 'RESYNC_UPDATE_NOT_APPLIED', 'zero-row profile update rejects apply');
$report(in_array('rollback', array_column($updateFailOperation->calls, 0), true), 'zero-row profile update rolls back transaction');

printf("RESULT checks=%d failed=%d\n", $checks, $failed);
exit($failed === 0 ? 0 : 1);
