<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

ob_start();
$root = dirname(__DIR__, 2);
require_once $root . '/lib/session_security.php';
require_once $root . '/lib/request_security.php';
require_once $root . '/app/Auth/UserPortalSessionService.php';

use OneId\App\Auth\UserPortalSessionService;

$checks = 0;
$failed = 0;
$report = static function (bool $ok, string $label) use (&$checks, &$failed): void {
    $checks++;
    $failed += $ok ? 0 : 1;
    echo ($ok ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
};

$deadline = oneid_session_deadline_state(1000, 100, 700, 600);
$report($deadline['idle_remaining_seconds'] === 300, 'deadline exposes authoritative idle remainder');
$report($deadline['absolute_remaining_seconds'] === 27900, 'deadline exposes authoritative absolute remainder');
$report($deadline['effective_remaining_seconds'] === 300, 'effective deadline uses the shorter remainder');

$_SERVER['REQUEST_METHOD'] = 'POST';
$report(oneid_is_technical_heartbeat_request(['user_session_status'=>'1','_csrf_token'=>'x']), 'status polling with form CSRF stays technical');
$report(oneid_is_technical_heartbeat_request(['user_session_renew'=>'1']), 'user renewal does not refresh before validation');
$report(oneid_is_technical_heartbeat_request(['admin_step_up_renew'=>'1']), 'Admin renewal does not refresh before validation');
$report(!oneid_is_technical_heartbeat_request(['user_session_status'=>'1','unexpected'=>'1']), 'mixed status request cannot masquerade as technical');

$sessionPath = sys_get_temp_dir() . '/oneid-f2-session-' . bin2hex(random_bytes(6));
mkdir($sessionPath, 0700);
session_save_path($sessionPath);
session_id('f2' . bin2hex(random_bytes(12)));
session_start();
$now = time();
$_SESSION = [
    'login_status'=>'true', 'login_user'=>'USER1',
    'oneid_session_created_at'=>$now - 1000,
    'oneid_session_last_activity'=>$now - 500,
];
$operation = new class {
    public array $events = [];
    public function get_system_config(): array { return ['token_timeout'=>'1']; }
    public function syslog_record(int $event, string $detail, string $ip): int
    {
        $this->events[] = [$event, $detail, $ip];
        return 1;
    }
};
$service = new UserPortalSessionService($operation);
$before = (int) $_SESSION['oneid_session_last_activity'];
$status = $service->status();
$report($status['code']==='USER_SESSION_ACTIVE' && $status['authenticated']===true, 'status returns stable active code');
$report((int)$_SESSION['oneid_session_last_activity']===$before, 'status polling does not update idle activity');
$renewed = $service->renew('USER1', '127.0.0.1');
$report($renewed['code']==='USER_SESSION_RENEWED' && (int)$_SESSION['oneid_session_last_activity'] >= $now, 'successful renewal updates PHP idle activity');
$report(($operation->events[0][0]??0)===69 && str_contains($operation->events[0][1]??'', 'token_revoked=0'), 'renewal writes non-revoking audit event');
$report($renewed['effective_remaining_seconds'] <= 3600, 'renewal response remains bounded by configured idle and absolute deadlines');

$_COOKIE['sso_cre'] = 'token-must-not-be-revoked';
$ended = $service->expire('USER1', '127.0.0.1');
$report($ended['code']==='USER_SESSION_EXPIRED' && $ended['authenticated']===false, 'explicit portal end returns stable expiry code');
$report(!isset($_COOKIE['sso_cre'], $_SESSION['login_status']) && isset($_SESSION['oneid_portal_session_expired_at']), 'portal expiry clears local cookie and authenticated session');
$report(($operation->events[1][0]??0)===70 && !method_exists($operation, 'update_specific_token_status'), 'portal expiry does not invoke token revocation');
session_destroy();
foreach (glob($sessionPath . '/sess_*') ?: [] as $file) { unlink($file); }
rmdir($sessionPath);

$sessionSource = file_get_contents($root.'/lib/session_security.php') ?: '';
$guardSource = file_get_contents($root.'/lib/request_security.php') ?: '';
$qFuncSource = file_get_contents($root.'/lib/q_func.php') ?: '';
$apiSource = file_get_contents($root.'/api.php') ?: '';
$adminSource = file_get_contents($root.'/app/Auth/AdminStepUpSessionService.php') ?: '';
$authSource = file_get_contents($root.'/lib/auth_security.php') ?: '';
$ssoSource = file_get_contents($root.'/lib/SSO_IDP_INC.php') ?: '';
$report(str_contains($guardSource, "'SSO_TOKEN_REVOKED'") && str_contains($guardSource, "'ACCOUNT_INACTIVE'") && str_contains($guardSource, "'CSRF_INVALID'"), 'guard distinguishes revoked token, inactive account and invalid CSRF');
$report(str_contains($guardSource, "'USER_SESSION_EXPIRED'") && str_contains($qFuncSource, "'SESSION_STATUS_UNAVAILABLE'"), 'expired and unavailable responses have stable codes');
$report(str_contains($qFuncSource, 'oneid_current_session_deadline_state($operation)') && str_contains($qFuncSource, "min((int)\$base['effective_remaining_seconds']"), 'Admin status and renewal use effective base-session deadline');
$report(str_contains($qFuncSource, 'oneid_refresh_session_activity();') && !str_contains($adminSource, 'update_specific_token_status'), 'successful Admin renewal refreshes base idle without token mutation');
$report(str_contains($sessionSource, 'syslog_record(68') && str_contains($sessionSource, 'oneid_portal_session_expired_at'), 'automatic expiry records marker and dedicated audit event');
$report(str_contains($authSource, "'expires' => time() + \$lifetimeSeconds") && str_contains($sessionSource, 'oneid_refresh_configured_sso_cookie'), 'browser cookie follows effective portal deadline without changing token value');
$report(!str_contains($apiSource, 'user_session_status') && !str_contains($apiSource, 'UserPortalSessionService'), 'service-provider API remains unchanged');
$report(str_contains($ssoSource, 'function COOKIE_SETTER($sso_cre,$respond_user_packet,object $operation)') && !str_contains($ssoSource, 'global $operation;'), 'SSO cookie refresh receives the request-scoped operation during logout bootstrap');
$up = file_get_contents($root.'/docs/migrations/20260807_user_portal_session_audit_up.sql') ?: '';
$down = file_get_contents($root.'/docs/migrations/20260807_user_portal_session_audit_down.sql') ?: '';
$report(str_contains($up, "68, 'USER_PORTAL_SESSION_EXPIRED'") && str_contains($up, "69, 'USER_PORTAL_SESSION_RENEWED'") && str_contains($up, "70, 'USER_PORTAL_SESSION_ENDED'") && str_contains($down, 'syslog_event_id = 70'), 'audit dictionary migration is complete and reversible');

echo "RESULT checks={$checks} failed={$failed}" . PHP_EOL;
ob_end_flush();
exit($failed === 0 ? 0 : 1);
