<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

ob_start();

$root = dirname(__DIR__, 2);
require_once $root . '/lib/session_security.php';

use OneId\App\Auth\UserSessionTimeoutPolicy;

$checks = 0;
$failed = 0;
$report = static function (bool $ok, string $label) use (&$checks, &$failed): void {
    $checks++;
    if (!$ok) {
        $failed++;
    }
    printf("%s %s\n", $ok ? 'PASS' : 'FAIL', $label);
};

$report(UserSessionTimeoutPolicy::idleSeconds('0.5') === 1800, '30-minute Administrator setting maps to 1800 seconds');
$report(UserSessionTimeoutPolicy::idleSeconds('1') === 3600, 'one-hour Administrator setting maps to 3600 seconds');
$report(UserSessionTimeoutPolicy::idleSeconds('2') === 7200, 'two-hour Administrator setting maps to 7200 seconds');
$report(UserSessionTimeoutPolicy::idleSeconds('168') === 604800, 'longest allowed Administrator setting maps without truncating idle policy');

$invalidRejected = 0;
foreach ([null, '', '0', '0.50', '3', '999'] as $invalid) {
    try {
        UserSessionTimeoutPolicy::idleSeconds($invalid);
    } catch (InvalidArgumentException) {
        $invalidRejected++;
    }
}
$report($invalidRejected === 6, 'missing, malformed and unsupported settings are rejected');

$report(!oneid_session_is_expired(1800, 0, 0, 1800), '30-minute session remains valid at exact idle boundary');
$report(oneid_session_is_expired(1801, 0, 0, 1800), '30-minute session expires one second after idle boundary');
$report(!oneid_session_is_expired(3600, 0, 0, 3600), 'one-hour session remains valid at exact idle boundary');
$report(oneid_session_is_expired(3601, 0, 0, 3600), 'one-hour session expires one second after idle boundary');
$report(!oneid_session_is_expired(28800, 0, 28799, 604800), 'long idle setting cannot shorten exact 8-hour absolute boundary');
$report(oneid_session_is_expired(28801, 0, 28800, 604800), 'long idle setting cannot extend beyond 8-hour absolute boundary');

$configured = new class {
    public function get_system_config(): array
    {
        return ['token_timeout' => '1'];
    }
};
$report(oneid_configured_session_idle_seconds($configured) === 3600, 'shared session reader uses current Administrator setting');

$invalidConfiguration = new class {
    public function get_system_config(): array
    {
        return ['token_timeout' => 'invalid'];
    }
};
$report(
    oneid_configured_session_idle_seconds($invalidConfiguration) === UserSessionTimeoutPolicy::DEFAULT_IDLE_SECONDS,
    'invalid stored setting fails safely to 30-minute idle timeout'
);

$sessionPath = sys_get_temp_dir() . '/oneid-f1-session-' . bin2hex(random_bytes(6));
if (!mkdir($sessionPath, 0700) && !is_dir($sessionPath)) {
    throw new RuntimeException('Unable to create isolated session path.');
}
session_save_path($sessionPath);
session_id('f1' . bin2hex(random_bytes(12)));
session_start();

$now = time();
$_SERVER['REQUEST_METHOD'] = 'GET';
$_POST = [];
$_SESSION = [
    'login_status' => 'true',
    'login_user' => 'USER1',
    'oneid_session_created_at' => $now - 2000,
    'oneid_session_last_activity' => $now - 2000,
];
oneid_apply_configured_session_policy($configured);
$report(
    ($_SESSION['login_status'] ?? '') === 'true'
        && (int) $_SESSION['oneid_session_last_activity'] >= $now,
    'meaningful request at 33 minutes remains authenticated and advances activity under one-hour setting'
);

$now = time();
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = ['update_specific_token_datetime' => '1'];
$_SESSION = [
    'login_status' => 'true',
    'login_user' => 'USER1',
    'oneid_session_created_at' => $now - 2000,
    'oneid_session_last_activity' => $now - 2000,
];
oneid_apply_configured_session_policy($configured);
$report(
    ($_SESSION['login_status'] ?? '') === 'true'
        && (int) $_SESSION['oneid_session_last_activity'] === $now - 2000,
    'technical heartbeat at 33 minutes remains valid but does not advance activity under one-hour setting'
);

$now = time();
$_SERVER['REQUEST_METHOD'] = 'GET';
$_POST = [];
$_SESSION = [
    'login_status' => 'true',
    'login_user' => 'USER1',
    'oneid_session_created_at' => $now - 3601,
    'oneid_session_last_activity' => $now - 3601,
];
oneid_apply_configured_session_policy($configured);
$report(
    !isset($_SESSION['login_status'], $_SESSION['login_user'])
        && (int) ($_SESSION['oneid_session_created_at'] ?? 0) >= $now,
    'request one second beyond configured one-hour idle boundary clears authenticated session'
);
oneid_establish_authenticated_session([
    'u_id' => 'USER1',
    'data1' => 'Test User',
    'data3' => '',
    'data4' => '',
    'u_type' => '2',
    'password_change_required' => 0,
]);
$report(
    ($_SESSION['login_status'] ?? '') === 'true'
        && ($_SESSION['login_user'] ?? '') === 'USER1'
        && !isset($_SESSION['oneid_portal_session_expired_at'])
        && (int) ($_SESSION['oneid_session_last_activity'] ?? 0) >= $now,
    'successful login after idle expiry establishes a clean active session'
);
session_destroy();
foreach (glob($sessionPath . '/sess_*') ?: [] as $sessionFile) {
    unlink($sessionFile);
}
rmdir($sessionPath);

$sessionSource = (string) file_get_contents($root . '/lib/session_security.php');
$configSource = (string) file_get_contents($root . '/lib/config.php');
$apiSource = (string) file_get_contents($root . '/api.php');
$report(
    str_contains($configSource, 'oneid_apply_configured_session_policy($operation)')
        && str_contains($sessionSource, "['token_timeout']"),
    'configured policy is applied after database bootstrap at one shared boundary'
);
$report(
    strpos($sessionSource, 'oneid_apply_configured_session_policy')
        > strpos($sessionSource, 'function oneid_start_secure_session'),
    'secure session start preserves timestamps until configured policy is available'
);
$report(
    str_contains($sessionSource, 'oneid_is_technical_heartbeat_request($_POST ?? [])')
        && str_contains($sessionSource, 'oneid_session_next_activity('),
    'configured enforcement preserves technical-heartbeat idle semantics'
);
$report(
    str_contains($apiSource, "switch(\$data['flag'] ?? null)")
        && !str_contains($apiSource, 'UserSessionTimeoutPolicy'),
    'service-provider token validation contract remains outside PHP user-session policy'
);

printf("RESULT checks=%d failed=%d\n", $checks, $failed);
ob_end_flush();
exit($failed === 0 ? 0 : 1);
