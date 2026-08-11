<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { exit(2); }

$root = dirname(__DIR__, 2);
$runtime = file_get_contents($root.'/config/runtime.php') ?: '';
$ms = require $root.'/config/locales/ms.php';
$en = require $root.'/config/locales/en.php';
$presentation = file_get_contents($root.'/lib/user_session_presentation.php') ?: '';
$controller = file_get_contents($root.'/public/dist/js/oneid-user-session.js') ?: '';
$css = file_get_contents($root.'/public/dist/css/oneid-user-session.css') ?: '';
$dashboard = file_get_contents($root.'/page/dashboard.php') ?: '';
$security = file_get_contents($root.'/page/user_mfa_security.php') ?: '';
$admin = file_get_contents($root.'/admin/dashboard.php') ?: '';
$challenge = file_get_contents($root.'/page/user_mfa_challenge.php') ?: '';
$qr = file_get_contents($root.'/page/user_mfa_totp_qr.php') ?: '';
$api = file_get_contents($root.'/api.php') ?: '';

$checks=0;$failed=0;
$report=static function(bool $ok,string $label)use(&$checks,&$failed):void{
    $checks++;$failed+=$ok?0:1;echo($ok?'PASS ':'FAIL ').$label.PHP_EOL;
};

$report(str_contains($runtime, "'ONEID_USER_SESSION_WARNING_ENABLED' => 'false'"), 'presentation feature flag fails closed by default');
$report(str_contains($presentation, "oneid_config('ONEID_USER_SESSION_WARNING_ENABLED', 'false')") && str_contains($presentation, "'warningSeconds' => 120"), 'server presentation config controls activation and two-minute warning');
$report(str_contains($dashboard, 'oneid-user-session.js?v=20260811-1') && str_contains($dashboard, 'oneid-user-session.css?v=20260808-2'), 'user dashboard loads cache-busted controller assets');
$report(str_contains($dashboard, "password_change_required'] ?? 0) !== 1"), 'mandatory password-change dashboard disables session popup controller');
$report(str_contains($security, 'oneid-user-session.js?v=20260811-1') && str_contains($security, 'OneIdUserSessionConfig'), 'authenticated Account Security page loads user controller');
$report(!str_contains($admin, 'oneid-user-session.js') && !str_contains($challenge, 'oneid-user-session.js') && !str_contains($qr, 'oneid-user-session.js'), 'Admin, pre-auth challenge and QR endpoints do not load user controller');
$report(str_contains($controller, "post('user_session_status')") && str_contains($controller, "post('user_session_renew')") && str_contains($controller, "post('user_session_expire')"), 'controller consumes only the F2 portal session actions');
$report(str_contains($controller, 'payload.effective_remaining_seconds') && str_contains($controller, 'config.warningSeconds || 120'), 'warning schedule uses authoritative effective remainder');
$report(str_contains($controller, 'synchronizeForWarning,') && !str_contains($controller, "setInterval(synchronize"), 'controller does not create session-keeping status polling');
$report(str_contains($controller, "error.code === 'USER_SESSION_EXPIRED'") && str_contains($controller, "error.code === 'SSO_TOKEN_REVOKED'") && str_contains($controller, "error.code === 'ACCOUNT_INACTIVE'"), 'terminal backend states are handled distinctly');
$report(str_contains($controller, "error.code === 'CSRF_INVALID'") && str_contains($controller, 'oneid-user-session-csrf-retried'), 'invalid CSRF triggers one controlled page revalidation without assuming renewal');
$report(str_contains($controller, "window.setTimeout(synchronize, 15000)") && str_contains($controller, "error.code === 'SESSION_STATUS_UNAVAILABLE'"), 'network or service failure retries without assuming expiry');
$report(str_contains($controller, 'BroadcastChannel') && str_contains($controller, "addEventListener('storage'") && str_contains($controller, "addEventListener('visibilitychange'") && str_contains($controller, "addEventListener('pageshow'"), 'multi-tab, visibility and back-forward cache revalidation are wired');
$report(str_contains($controller, 'var sessionSwal = window.swal;') && substr_count($controller, 'sessionSwal.close()') >= 3, 'session popup retains its original SweetAlert close API across notification wrappers and tabs');
$report(str_contains($controller, 'var terminalDialogOpen = false;') && str_contains($controller, 'if (terminalDialogOpen)') && str_contains($controller, 'terminalDialogOpen = true;'), 'overlapping terminal responses can open only one expiry dialog');
$report(strpos($controller, 'sessionSwal.close();', strpos($controller, "post('user_session_renew')")) < strpos($controller, 'schedule(payload);', strpos($controller, "post('user_session_renew')")), 'renewal closes the old warning before scheduling its new deadline');
$report(str_contains($controller, 'absoluteLimited') && str_contains($controller, 'config.text.absoluteLimitBody'), 'renewal near the absolute boundary explains that the session cannot be extended further');
$report(str_contains($controller, 'clearSensitiveInputs') && str_contains($controller, 'input[type="password"]') && str_contains($controller, 'input[autocomplete="one-time-code"]'), 'portal expiry clears sensitive password and OTP fields');
$report(str_contains($controller, 'anotherDialogIsOpen') && str_contains($controller, '.modal.in, .modal.show'), 'warning avoids replacing an active Bootstrap or SweetAlert dialog');
$report(
    str_contains($controller, "dialog.getAttribute('aria-hidden') !== 'true'")
        && str_contains($controller, "style.display !== 'none'")
        && str_contains($controller, 'dialog.offsetWidth > 0 || dialog.offsetHeight > 0'),
    'stale hidden Bootstrap modal cannot suppress the session warning'
);
$report(
    str_contains($css, 'linear-gradient(90deg, #ff5b2b')
        && str_contains($css, 'background: rgba(9, 29, 54, .68)')
        && str_contains($css, 'width: 520px')
        && str_contains($controller, 'window.setTimeout(markTerminalDialog, 0)'),
    'warning renewal and terminal dialogs share the Administrator session visual system'
);
$report(str_contains($controller, 'config.text.otherAppsNote') && str_contains($controller, 'textContent ='), 'popup safely states that other applications are not closed');
$report(str_contains($css, 'z-index: 13000') && str_contains($css, '@media (max-width: 560px)') && str_contains($css, 'font-variant-numeric: tabular-nums'), 'popup has isolated modal-safe, mobile and countdown styling');
$keys=['eyebrow','warning_title','warning_body','other_apps_note','stay_connected','end_session','renewed_title','renewed_body','absolute_limit_title','absolute_limit_body','expired_title','expired_body','revoked_body','inactive_body','request_failed','ok'];
$localized=true;foreach($keys as $key){$full='user_session.'.$key;if(!isset($ms[$full],$en[$full])||trim($ms[$full])===''||trim($en[$full])===''){$localized=false;}}
$report($localized, 'all popup states and controls are localized in BM and English');
$report(!str_contains($controller, 'update_specific_token_status') && !str_contains($controller, 'new_sso_cre') && !str_contains($api, 'OneIdUserSessionConfig'), 'presentation does not mutate token or service-provider API contracts');
$report(is_file($root.'/public/dist/js/oneid-user-session.js') && is_file($root.'/public/dist/css/oneid-user-session.css'), 'controller assets reside under the public web asset root');

echo "RESULT checks={$checks} failed={$failed}".PHP_EOL;
exit($failed===0?0:1);
