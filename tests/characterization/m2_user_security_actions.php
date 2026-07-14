<?php

if (PHP_SAPI !== 'cli') {
    exit(2);
}

$root = dirname(__DIR__, 2);
require_once $root . '/app/User/UserSecurityActionException.php';
require_once $root . '/app/User/UserSecurityActionService.php';

use OneId\App\User\UserSecurityActionException;
use OneId\App\User\UserSecurityActionService;

final class M2FakeOperation
{
    public array|false $user = ['u_id' => 'USER1', 'avail_status' => 1, 'password_change_required' => 0];
    public array $calls = [];
    public int $passwordResult = 1;
    public int $statusResult = 1;
    public int $auditResult = 1;
    public bool $throwOnTokens = false;

    public function beginTransaction(): void { $this->calls[] = ['begin']; }
    public function commit(): void { $this->calls[] = ['commit']; }
    public function rollback(): void { $this->calls[] = ['rollback']; }
    public function admin_get_user_for_security_action(string $id, bool $lock): array|false
    { $this->calls[] = ['read', $id, $lock]; return $this->user; }
    public function set_user_password(string $id, string $password, int $required): int
    { $this->calls[] = ['password', $id, $password, $required]; return $this->passwordResult; }
    public function admin_update_user_status(string $id, int $status): int
    { $this->calls[] = ['status', $id, $status]; return $this->statusResult; }
    public function update_whole_token_status(string $id, int $status): int
    { if ($this->throwOnTokens) throw new RuntimeException('fixture'); $this->calls[] = ['tokens', $id, $status]; return 0; }
    public function otp_invalidate_active(string $id): int
    { $this->calls[] = ['otp', $id]; return 0; }
    public function syslog_record(int $event, string $detail, string $ip): int
    { $this->calls[] = ['audit', $event, $detail, $ip]; return $this->auditResult; }
}

$checks = 0;
$failed = 0;
$report = static function (bool $ok, string $label) use (&$checks, &$failed): void {
    $checks++;
    if (!$ok) $failed++;
    printf("%s %s\n", $ok ? 'PASS' : 'FAIL', $label);
};
$reason = static function (callable $callback): string {
    try { $callback(); } catch (UserSecurityActionException $e) { return $e->reason; }
    return '';
};

$resetOp = new M2FakeOperation();
$reset = (new UserSecurityActionService($resetOp))->resetPassword('USER1', 'ADMIN1', '127.0.0.1');
$names = array_column($resetOp->calls, 0);
$report($reset['code'] === 'M2_PASSWORD_RESET', 'password reset returns explicit success code');
$report($names === ['begin','read','password','tokens','otp','audit','commit'], 'password reset is one ordered atomic workflow');
$passwordCall = $resetOp->calls[2];
$report(strlen($passwordCall[2]) === 64 && $passwordCall[3] === 1, 'reset uses unknown random secret and requires password change');
$auditCall = $resetOp->calls[5];
$report($auditCall[1] === 10 && str_contains($auditCall[2], 'correlation='), 'password reset writes correlated audit event');
$report(!str_contains($auditCall[2], $passwordCall[2]), 'temporary reset secret is absent from audit detail');

$deactivateOp = new M2FakeOperation();
$deactivate = (new UserSecurityActionService($deactivateOp))->deactivate('USER1', 'ADMIN1', '127.0.0.1');
$report($deactivate['code'] === 'M2_USER_DEACTIVATED', 'deactivate returns explicit success code');
$report(in_array(['status','USER1',0], $deactivateOp->calls, true), 'deactivate verifies status mutation to inactive');
$deactivateAudit = array_values(array_filter($deactivateOp->calls, static fn($c) => $c[0] === 'audit'));
$report(($deactivateAudit[0][1] ?? null) === 25, 'deactivate writes event 25');

$reactivateOp = new M2FakeOperation();
$reactivateOp->user['avail_status'] = 0;
$reactivate = (new UserSecurityActionService($reactivateOp))->reactivate('USER1', 'ADMIN1', '127.0.0.1');
$report($reactivate['code'] === 'M2_USER_REACTIVATED', 'reactivate returns explicit success code');
$report(in_array(['status','USER1',1], $reactivateOp->calls, true), 'reactivate verifies status mutation to active');
$reactivateAudit = array_values(array_filter($reactivateOp->calls, static fn($c) => $c[0] === 'audit'));
$report(($reactivateAudit[0][1] ?? null) === 26, 'reactivate writes event 26');

$report($reason(static fn() => (new UserSecurityActionService(new M2FakeOperation()))->deactivate('ADMIN1','ADMIN1','127.0.0.1')) === 'M2_SELF_ACTION_FORBIDDEN', 'admin cannot deactivate own account');
$report($reason(static fn() => (new UserSecurityActionService(new M2FakeOperation()))->resetPassword('ADMIN1','ADMIN1','127.0.0.1')) === 'M2_SELF_ACTION_FORBIDDEN', 'admin cannot force-reset own password');

$inactiveOp = new M2FakeOperation();
$inactiveOp->user['avail_status'] = 0;
$report($reason(static fn() => (new UserSecurityActionService($inactiveOp))->deactivate('USER1','ADMIN1','127.0.0.1')) === 'M2_ALREADY_INACTIVE', 'repeat deactivate fails closed');
$report(in_array('rollback', array_column($inactiveOp->calls, 0), true), 'repeat deactivate rolls back');
$activeOp = new M2FakeOperation();
$report($reason(static fn() => (new UserSecurityActionService($activeOp))->reactivate('USER1','ADMIN1','127.0.0.1')) === 'M2_ALREADY_ACTIVE', 'repeat reactivate fails closed');
$inactiveReset = new M2FakeOperation();
$inactiveReset->user['avail_status'] = 0;
$report($reason(static fn() => (new UserSecurityActionService($inactiveReset))->resetPassword('USER1','ADMIN1','127.0.0.1')) === 'M2_USER_INACTIVE', 'inactive password reset fails closed');

$missing = new M2FakeOperation();
$missing->user = false;
$report($reason(static fn() => (new UserSecurityActionService($missing))->deactivate('USER1','ADMIN1','127.0.0.1')) === 'M2_USER_NOT_FOUND', 'missing user fails closed');
$statusFail = new M2FakeOperation();
$statusFail->statusResult = 0;
$report($reason(static fn() => (new UserSecurityActionService($statusFail))->deactivate('USER1','ADMIN1','127.0.0.1')) === 'M2_STATUS_NOT_CHANGED', 'zero-row status mutation fails closed');
$report(!in_array('audit', array_column($statusFail->calls, 0), true) && in_array('rollback', array_column($statusFail->calls, 0), true), 'zero-row status performs no audit and rolls back');
$auditFail = new M2FakeOperation();
$auditFail->auditResult = 0;
$report($reason(static fn() => (new UserSecurityActionService($auditFail))->resetPassword('USER1','ADMIN1','127.0.0.1')) === 'M2_AUDIT_NOT_WRITTEN', 'audit failure rejects password reset');
$report(in_array('rollback', array_column($auditFail->calls, 0), true) && !in_array('commit', array_column($auditFail->calls, 0), true), 'audit failure rolls back password reset');
$tokenFail = new M2FakeOperation();
$tokenFail->throwOnTokens = true;
$report($reason(static fn() => (new UserSecurityActionService($tokenFail))->deactivate('USER1','ADMIN1','127.0.0.1')) === 'M2_OPERATION_FAILED', 'unexpected revocation failure has safe code');
$report(in_array('rollback', array_column($tokenFail->calls, 0), true), 'revocation exception rolls back status mutation');

printf("RESULT checks=%d failed=%d\n", $checks, $failed);
exit($failed === 0 ? 0 : 1);
