<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/lib/config.php';
require_once dirname(__DIR__, 2) . '/lib/session_security.php';
require_once dirname(__DIR__, 2) . '/app/User/UserPasswordChangeException.php';
require_once dirname(__DIR__, 2) . '/app/User/InitialPasswordSetupService.php';

use OneId\App\User\InitialPasswordSetupService;
use OneId\App\User\UserPasswordChangeException;

oneid_start_secure_session();

final class InitialPasswordSetupFake
{
    public array $user;
    public int $passwordWrites = 0;
    public int $tokenRevocations = 0;
    public int $otpInvalidations = 0;
    public int $audits = 0;
    public bool $committed = false;

    public function __construct(int $required = 1)
    {
        $this->user = [
            'u_id' => 'STUDENT1',
            'u_password' => oneid_password_hash('Unknown-Random-Password1!'),
            'password_change_required' => $required,
            'avail_status' => 1,
        ];
    }
    public function beginTransaction(): void {}
    public function commit(): void { $this->committed = true; }
    public function rollback(): void {}
    public function get_user_password_change_for_update(string $id): array { return $this->user; }
    public function get_password_history_hashes(string $id, int $limit): array { return []; }
    public function record_password_history(string $id, string $hash): int { return 1; }
    public function set_user_password(string $id, string $password, int $required): int
    {
        $this->passwordWrites++;
        $this->user['password_change_required'] = $required;
        return 1;
    }
    public function prune_password_history(string $id, int $keep): int { return 0; }
    public function update_whole_token_status(string $id, int $status): int
    {
        $this->tokenRevocations++;
        return 1;
    }
    public function otp_invalidate_active(string $id): int
    {
        $this->otpInvalidations++;
        return 1;
    }
    public function syslog_record(int $event, string $detail, string $ip): int
    {
        $this->audits++;
        return str_contains($detail, 'mydigitalid_initial_password_setup') ? 1 : 0;
    }
}

$checks = [];
$_SESSION = [
    'login_status' => 'true',
    'login_user' => 'STUDENT1',
    'auth_method' => 'mydigitalid',
];
oneid_issue_mydigitalid_initial_password_grant('STUDENT1');
$checks['fresh purpose-bound grant is valid'] = oneid_has_valid_mydigitalid_initial_password_grant('STUDENT1');
$checks['grant rejects another user'] = !oneid_has_valid_mydigitalid_initial_password_grant('STUDENT2');
$_SESSION['mydigitalid_initial_password_grant']['issued_at'] = time() - 301;
$checks['grant expires after five minutes'] = !oneid_has_valid_mydigitalid_initial_password_grant('STUDENT1');
oneid_issue_mydigitalid_initial_password_grant('STUDENT1');

$fake = new InitialPasswordSetupFake();
$result = (new InitialPasswordSetupService($fake))->setup(
    'STUDENT1',
    'Violet-River-Cloud9!',
    'Violet-River-Cloud9!',
    '127.0.0.1'
);
$checks['setup succeeds without current password only for required account'] =
    $result['code'] === 'UC6_INITIAL_PASSWORD_SET_REAUTH_REQUIRED'
    && $fake->committed
    && $fake->passwordWrites === 1
    && $fake->tokenRevocations === 1
    && $fake->otpInvalidations === 1
    && $fake->audits === 1;

$notRequired = new InitialPasswordSetupFake(0);
try {
    (new InitialPasswordSetupService($notRequired))->setup(
        'STUDENT1',
        'Amber-Forest-Cloud8!',
        'Amber-Forest-Cloud8!',
        '127.0.0.1'
    );
    $checks['established password cannot use initial setup'] = false;
} catch (UserPasswordChangeException $exception) {
    $checks['established password cannot use initial setup'] =
        $exception->reason === 'UC6_INITIAL_SETUP_NOT_REQUIRED'
        && $notRequired->passwordWrites === 0;
}

$failures = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));
foreach ($checks as $description => $passed) {
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $description);
}
printf("RESULT checks=%d failures=%d\n", count($checks), count($failures));
exit($failures === [] ? 0 : 1);
