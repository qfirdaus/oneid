<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Admin/SsoConfigurationException.php';
require_once dirname(__DIR__) . '/app/Admin/SsoConfigurationService.php';

use OneId\App\Admin\SsoConfigurationException;
use OneId\App\Admin\SsoConfigurationService;

final class Sc2FakeOperation
{
    public int $updates = 0;
    public int $affected = 1;
    public int $begins = 0;
    public int $commits = 0;
    public int $rollbacks = 0;
    public int $audits = 0;
    public bool $auditSucceeds = true;
    public array|false $stored = [
        'id' => 1,
        'configuration_version'=>1,
        'token_timeout' => 0.5,
        'multi_session' => 1,
        'password_reset_email_enabled' => 1,
        'future_secret' => 'must-not-be-projected',
    ];
    public array $lastUpdate = [];
    public array $histories=[];

    public function get_system_config(): array|false
    {
        return $this->stored;
    }
    public function configuration_history_latest_success(){return null;}
    public function configuration_history_record(array $entry):int{$this->histories[]=$entry;return 1;}

    public function beginTransaction(): bool
    {
        $this->begins++;
        return true;
    }

    public function commit(): bool
    {
        $this->commits++;
        return true;
    }

    public function rollback(): bool
    {
        $this->rollbacks++;
        return true;
    }

    public function get_system_config_for_update(): array|false
    {
        return $this->stored;
    }

    public function update_configuration_by_id(int $id, string $timeout, int $multiSession,int $version): int
    {
        $this->updates++;
        $this->lastUpdate = [$timeout, $multiSession];
        if ($this->affected === 1 && is_array($this->stored)) {
            $this->stored['token_timeout'] = $timeout;
            $this->stored['multi_session'] = $multiSession;
            $this->stored['configuration_version']++;
        }
        return $this->affected;
    }

    public function syslog_record(int $event, string $detail, string $ipAddress): int
    {
        $this->audits++;
        return $this->auditSucceeds && $event === 19 ? 1 : 0;
    }
    public function preview_policy_revocation(string $timeout,bool $reduced,bool $disable): array{return ['affected_tokens'=>0,'affected_users'=>0,'timeout_tokens'=>0,'multiple_tokens'=>0];}
    public function schedule_policy_revocation(string $timeout,bool $reduced,bool $disable,string $at,string $correlation): int{return 0;}
}

$checks = 0;
$failures = 0;
$check = static function (bool $condition, string $description) use (&$checks, &$failures): void {
    $checks++;
    if (!$condition) {
        $failures++;
    }
    printf("%s: %s\n", $condition ? 'PASS' : 'FAIL', $description);
};
$expectReason = static function (callable $operation, string $reason) use ($check): void {
    try {
        $operation();
        $check(false, 'invalid input is rejected with ' . $reason);
    } catch (SsoConfigurationException $exception) {
        $check($exception->reason === $reason, 'invalid input is rejected with ' . $reason);
    }
};

$fake = new Sc2FakeOperation();
$service = new SsoConfigurationService($fake);
$read = $service->read();
$check(
    $read['status'] === 1
        && $read['code'] === 'SC2_CONFIG_LOADED'
        && $read['data'] === ['configuration_version'=>1,'token_timeout' => '0.5', 'multi_session' => 1]
        && !array_key_exists('future_secret', $read['data']),
    'read response projects only the two SSO policy fields and safe revision'
);

$valid = [
    'update_configuration' => '',
    'token_timeout' => '12',
    'sso_settings_multi_session' => '0',
    'configuration_version'=>'1','change_reason'=>'Approved security policy update',
];
$updated = $service->update($valid, 'admin.test', '127.0.0.1', ['affected_tokens'=>0,'affected_users'=>0]);
$check(
    $updated['code'] === 'SC2_CONFIG_UPDATED'
        && $updated['changed'] === true
        && $fake->lastUpdate === ['12', 0]
        && $fake->audits === 1
        && $fake->commits === 1,
    'valid values are normalized, audited and committed atomically'
);

$valid['configuration_version']='2';$unchanged = $service->update($valid, 'admin.test', '127.0.0.1', ['affected_tokens'=>0,'affected_users'=>0]);
$check(
    $unchanged['code'] === 'SC2_CONFIG_UNCHANGED'
        && $unchanged['changed'] === false
        && $fake->audits === 1
        && $fake->commits === 2,
    'unchanged policy commits without update or duplicate audit event'
);

$beforeInvalid = $fake->updates;
$expectReason(fn () => $service->update(array_replace($valid, ['token_timeout' => '-1']), 'admin.test', '127.0.0.1'), 'SC2_TOKEN_TIMEOUT_INVALID');
$expectReason(fn () => $service->update(array_replace($valid, ['token_timeout' => '999']), 'admin.test', '127.0.0.1'), 'SC2_TOKEN_TIMEOUT_INVALID');
$expectReason(fn () => $service->update(array_replace($valid, ['sso_settings_multi_session' => '2']), 'admin.test', '127.0.0.1'), 'SC2_MULTI_SESSION_INVALID');
$expectReason(fn () => $service->update(array_replace($valid, ['unexpected' => 'value']), 'admin.test', '127.0.0.1'), 'SC2_UNEXPECTED_FIELD');
$check($fake->updates === $beforeInvalid, 'all invalid requests are rejected before persistence mutation');

$fake->affected = 2;
$changedAgain = array_replace($valid, ['token_timeout' => '24']);
$expectReason(fn () => $service->update($changedAgain, 'admin.test', '127.0.0.1', ['affected_tokens'=>0,'affected_users'=>0]), 'SC3_CONFIG_UPDATE_NOT_APPLIED');
$check($fake->rollbacks === 1, 'invalid targeted update count rolls back the transaction');

$fake->affected = 1;
$fake->auditSucceeds = false;
$expectReason(fn () => $service->update($changedAgain, 'admin.test', '127.0.0.1', ['affected_tokens'=>0,'affected_users'=>0]), 'SC3_AUDIT_NOT_WRITTEN');
$check($fake->rollbacks === 2, 'audit failure rolls back the configuration transaction');

$root = dirname(__DIR__);
$endpoint = (string) file_get_contents($root . '/lib/q_func.php');
$ui = (string) file_get_contents($root . '/admin/dashboard.php');
$check(
    str_contains($endpoint, 'SsoConfigurationService($operation)')
        && str_contains($endpoint, "(string) \$_SESSION['login_user']")
        && str_contains($endpoint, "'correlation_id'=>\$exception->correlationId"),
    'admin endpoint uses the service and returns correlated failures'
);
$check(
    str_contains($ui, "response.code !== 'SC2_CONFIG_LOADED'")
        && str_contains($ui, "response.code === 'SC2_CONFIG_UPDATED'")
        && str_contains($ui, "response.code === 'SC2_CONFIG_UNCHANGED'"),
    'admin UI consumes the structured SC2 response contract'
);

printf("RESULT: checks=%d failures=%d\n", $checks, $failures);
exit($failures === 0 ? 0 : 1);
