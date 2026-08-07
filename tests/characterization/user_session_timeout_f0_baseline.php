<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

$root = dirname(__DIR__, 2);
$session = (string) file_get_contents($root . '/lib/session_security.php');
$requestSecurity = (string) file_get_contents($root . '/lib/request_security.php');
$dashboard = (string) file_get_contents($root . '/page/dashboard.php');
$api = (string) file_get_contents($root . '/api.php');
$database = (string) file_get_contents($root . '/lib/Database.php');
$ssoInclude = (string) file_get_contents($root . '/lib/SSO_IDP_INC.php');
$authSecurity = (string) file_get_contents($root . '/lib/auth_security.php');
$logout = (string) file_get_contents($root . '/app/Auth/LogoutHandler.php');
$adminSession = (string) file_get_contents($root . '/public/dist/js/oneid-admin-session.js');
$adminRenewal = (string) file_get_contents($root . '/app/Auth/AdminStepUpSessionService.php');

$checks = 0;
$failed = 0;
$report = static function (bool $ok, string $label) use (&$checks, &$failed): void {
    $checks++;
    if (!$ok) {
        $failed++;
    }
    printf("%s %s\n", $ok ? 'PASS' : 'FAIL', $label);
};

// Current PHP-session baseline. Fasa 1 is expected to replace the hard-coded idle value.
$report(
    str_contains($session, '($now - $lastActivity) > 1800')
        && str_contains($session, '($now - $createdAt) > 28800'),
    'PHP session currently uses a hard-coded 30-minute idle and 8-hour absolute boundary'
);
$report(
    str_contains($session, "['update_specific_token_datetime']")
        && str_contains($session, "['admin_step_up_status', 'purpose']"),
    'user token heartbeat and Admin status polling are technical heartbeats'
);
$report(
    !str_contains($session, 'get_system_config') && !str_contains($session, 'token_timeout'),
    'PHP session timeout is not yet sourced from the Administrator setting'
);

// Current dashboard heartbeat and failure behavior.
$report(
    str_contains($dashboard, 'startTokenRefresh();')
        && str_contains($dashboard, 'update_specific_token_datetime:"1"')
        && str_contains($dashboard, '}, 300000);'),
    'user dashboard refreshes token activity every five minutes'
);
$report(
    str_contains($dashboard, 'location.reload(true);'),
    'user dashboard currently reloads after a token-heartbeat error'
);
$report(
    str_contains($dashboard, 'sweetalert.min.js')
        && !str_contains($dashboard, 'OneIdUserSessionConfig')
        && !str_contains($dashboard, 'oneid-user-session.js'),
    'SweetAlert is available but no user-session controller is active yet'
);

// Existing service-provider contract must remain unchanged by this project.
$report(
    str_contains($api, "switch(\$data['flag'] ?? null)")
        && str_contains($api, "case \"1\": //check SSO Token")
        && str_contains($api, "['respond_flag']")
        && str_contains($api, "['respond']")
        && str_contains($api, "['respond_user_packet']"),
    'integration API retains the existing flag-1 token-validation response contract'
);
$report(
    str_contains($api, "get_system_config()['token_timeout']")
        && str_contains($api, "['token_issued_at']")
        && str_contains($api, 'SsoTokenLifetimePolicy::LEGACY_REFRESH'),
    'SSO token lifetime currently follows Administrator setting and preserves legacy refresh'
);
$report(
    str_contains($database, 'UPDATE token_tbl SET token_datetime = NOW()')
        && !str_contains(
            substr(
                $database,
                strpos($database, 'public function update_specific_token_datetime'),
                900
            ),
            'token_issued_at = NOW()'
        ),
    'token heartbeat updates mutable activity without changing immutable issuance'
);

// Current portal-expiry and logout distinction.
$sessionExpiryBlock = substr(
    $session,
    strpos($session, 'if ($expired) {'),
    240
);
$report(
    str_contains($sessionExpiryBlock, '$_SESSION = [];')
        && !str_contains($sessionExpiryBlock, 'update_specific_token_status')
        && !str_contains($sessionExpiryBlock, 'oneid_clear_sso_cookie'),
    'idle PHP expiry currently clears session state without revoking the SSO token'
);
$report(
    str_contains($logout, 'update_specific_token_status')
        && str_contains($logout, 'oneid_clear_sso_cookie')
        && str_contains($logout, 'session_destroy()'),
    'manual logout revokes token, clears cookie and destroys PHP session'
);
$report(
    str_contains($ssoInclude, 'oneid_establish_authenticated_session')
        && str_contains($ssoInclude, 'COOKIE_SETTER'),
    'valid SSO flow can establish an authenticated OneID session from a token'
);
$report(
    str_contains($authSecurity, "'expires' => time() + 1800"),
    'OneID SSO browser cookie currently has a fixed 30-minute lifetime'
);

// Admin is a grant above the same authenticated PHP session.
$report(
    str_contains($requestSecurity, "'admin_step_up_status'")
        && str_contains($requestSecurity, "'admin_step_up_renew'"),
    'Admin status and renewal actions use the shared authenticated request boundary'
);
$report(
    str_contains($adminRenewal, 'admin_step_up_revoke_active_access_grants')
        && str_contains($adminRenewal, 'admin_step_up_create_grant')
        && !str_contains($adminRenewal, 'update_specific_token_status'),
    'Admin Stay Connected replaces only the ADMIN_ACCESS grant and does not revoke SSO token'
);
$report(
    str_contains($adminSession, "post('admin_step_up_status'")
        && str_contains($adminSession, "post('admin_step_up_renew')"),
    'Admin controller keeps technical status polling separate from explicit renewal'
);
$report(
    !str_contains($dashboard, 'oneid-admin-session.js'),
    'user dashboard does not load the Admin session controller'
);

printf("RESULT checks=%d failed=%d\n", $checks, $failed);
exit($failed === 0 ? 0 : 1);
