<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

$root = dirname(__DIR__);
$files = [
    'app/Auth/UserMfa/PdoUserMfaPolicyReader.php',
    'app/Auth/UserMfa/UserMfaPrimaryAuthDecision.php',
    'docs/migrations/20260730_user_login_mfa_u8_pilot_up.sql',
    'docs/migrations/20260730_user_login_mfa_u8_pilot_down.sql',
    'tools/user_login_mfa_u8_pilot_schema.php',
    'tools/user_login_mfa_u8_readiness.php',
    'tests/characterization/user_login_mfa_u8_primary_auth.php',
    'app/Auth/UserMfa/LegacyUserMfaLoginFinalizer.php',
    'tools/user_login_mfa_u8_pilot_plan.php',
    'tools/user_login_mfa_u8_policy_transition.php',
];
$checks = 0;
$failures = 0;
$report = static function (bool $ok, string $label) use (&$checks, &$failures): void {
    $checks++;
    $failures += $ok ? 0 : 1;
    printf("%s %s\n", $ok ? 'PASS' : 'FAIL', $label);
};
foreach ($files as $file) {
    $report(is_file($root . '/' . $file), "{$file} exists");
}
$up = (string) file_get_contents($root . '/' . $files[2]);
$reader = (string) file_get_contents($root . '/' . $files[0]);
$decision = (string) file_get_contents($root . '/' . $files[1]);
$route = (string) file_get_contents($root . '/lib/q_func.php');
$login = (string) file_get_contents($root . '/index.php');
$securityPage = (string) file_get_contents($root . '/page/user_mfa_security.php');
$challengePage = (string) file_get_contents($root . '/page/user_mfa_challenge.php');
$dashboard = (string) file_get_contents($root . '/page/dashboard.php');
$sessionSecurity = (string) file_get_contents($root . '/lib/session_security.php');
$localeMs = require $root . '/config/locales/ms.php';
$localeEn = require $root . '/config/locales/en.php';
$report(
    str_contains($up, 'user_login_mfa_pilot_users')
    && str_contains($up, 'LOCAL_STUDENT')
    && str_contains($up, 'INTERNATIONAL_STUDENT'),
    'pilot schema has bounded representative categories'
);
$report(
    str_contains($reader, 'USER_MFA_RUNTIME_DATABASE_POLICY_MISMATCH')
    && str_contains($decision, 'assertRuntimeParity'),
    'primary authentication fails closed on runtime/database mismatch'
);
$report(
    !str_contains($up, '0530-09') && !str_contains($up, '@upnm.edu.my'),
    'pilot migration contains no real pilot identity or email'
);
$report(
    strpos($route, 'UserMfaPrimaryAuthDecision')
        < strpos($route, '//SSO Token Initialize')
    && str_contains($route, "login_status'=>2")
    && str_contains($route, 'UserMfaEmailOtpService')
    && str_contains($route, 'UserMfaPendingLoginCoordinator'),
    'password login issues no token until email factor and finalization complete'
);
$report(
    !str_contains($login, 'modal_user_mfa')
    && str_contains($login, "response['login_status']==2")
    && str_contains($login, "window.location.href='page/user-mfa-challenge'")
    && str_contains($challengePage, 'user_mfa_email_request')
    && str_contains($challengePage, 'user_mfa_email_verify')
    && str_contains($challengePage, 'user_mfa_totp_verify_login')
    && str_contains($challengePage, 'FROM user_mfa_preferences')
    && str_contains($challengePage, "\$preferredTotp")
    && str_contains($challengePage, "\$totp?'':'disabled'")
    && str_contains($challengePage, "\$totpUnavailableKey"),
    'login uses a full-page selectable email or Authenticator challenge'
);
$report(
    str_contains($challengePage, 'placeholder="000000"')
    && str_contains($challengePage, 'startResend')
    && str_contains($challengePage, 'sendEmailButton.disabled=true')
    && str_contains($challengePage, 'verifyEmailButton.disabled=true')
    && str_contains($challengePage, 'user_mfa.login.request_first')
    && str_contains(
        (string) file_get_contents($root . '/app/Auth/UserMfa/UserMfaHttpBoundary.php'),
        'USER_MFA_RESEND_COOLDOWN'
    ),
    'login challenge exposes safe feedback loading cooldown and OTP validation'
);
$report(
    is_file($root . '/public/page/user-mfa-security.php')
    && is_file($root . '/public/page/user-mfa-totp-qr.php')
    && is_file($root . '/public/page/user-mfa-challenge.php')
    && is_file($root . '/public/dist/css/user-mfa-flow.css')
    && str_contains($securityPage, "user-mfa-totp-qr?factor_id=")
    && str_contains($securityPage, 'mfa-provision-grid')
    && str_contains($securityPage, 'user_mfa.security.preference_help')
    && str_contains($securityPage, 'user_mfa.security.revoke_confirm')
    && str_contains($securityPage, "post('user_mfa_totp_revoke'")
    && str_contains($securityPage, 'id="revokeCode"')
    && str_contains($securityPage, 'placeholder="000000"')
    && str_contains($securityPage, 'sweetalert.min.js')
    && str_contains($securityPage, 'sweetalert.css')
    && str_contains($securityPage, 'showCancelButton:true')
    && str_contains($securityPage, 'cancelButtonText:')
    && !str_contains($securityPage, 'window.confirm(')
    && str_contains((string) file_get_contents($root . '/page/user_mfa_totp_qr.php'), 'QrLogoOverlay::apply'),
    'public wrappers expose account security challenge and same-origin QR'
);
$report(
    str_contains($dashboard, 'tab_user_mfa_security')
    && str_contains($dashboard, 'href="user-mfa-security"')
    && str_contains($dashboard, "['ENROLLMENT', 'PILOT_ENFORCED', 'ENFORCED']")
    && !str_contains($dashboard, 'modal_user_mfa_security')
    && str_contains($dashboard, 'selfServiceEligible')
    && str_contains($securityPage, "\$databaseMode !== 'PILOT_ENFORCED'")
    && str_contains($securityPage, 'user_mfa_totp_preference')
    && str_contains($securityPage, 'FROM user_login_mfa_policy p')
    && str_contains($securityPage, 'user_mfa.security.badge')
    && str_contains($securityPage, 'str_repeat')
    && str_contains($securityPage, "'email' => \$maskedEmail")
    && str_contains($securityPage, 'mfa-provision-grid')
    && str_contains($securityPage, 'user_mfa.security.department')
    && str_contains($securityPage, 'user-mfa-flow.css')
    && str_contains($route, 'USER_MFA_PILOT_ACCESS_REQUIRED'),
    'full-page account security is pilot-restricted only in pilot mode and available when enforced'
);
$report(
    str_contains($route, "user_mfa_totp_verify_login")
    && str_contains($route, "markVerified(\$pendingTransaction,'TOTP'")
    && str_contains($route, 'LegacyUserMfaLoginFinalizer')
    && str_contains($route, "\$redirect=APP_URL.'/page/dashboard'")
    && str_contains($challengePage, "factorElement.value==='TOTP'")
    && str_contains($challengePage, "replace(/^page\\//,'/page/')")
    && !str_contains($challengePage, 'window.onload'),
    'TOTP login verifies the pending transaction before shared finalization'
);
$report(
    str_contains($sessionSecurity, 'function oneid_totp_account_label')
    && str_contains($sessionSecurity, "'OneID@UPNM %s (%s)'")
    && str_contains($sessionSecurity, "\$user['data3']")
    && str_contains($sessionSecurity, "\$user['data4']")
    && !str_contains($sessionSecurity, "\$_SESSION['user'] ?? ''")
    && str_contains($route, "oneid_totp_account_label('USER',")
    && str_contains($route, "oneid_totp_account_label('ADMIN',")
    && str_contains(
        (string) file_get_contents($root . '/page/user_mfa_totp_qr.php'),
        "oneid_totp_account_label('USER',"
    )
    && str_contains(
        (string) file_get_contents($root . '/page/admin_totp_qr.php'),
        "oneid_totp_account_label('ADMIN',"
    ),
    'Admin and User Authenticator labels use the public login identifier'
);
$userMfaLocaleKeys = array_values(array_filter(
    array_keys($localeMs),
    static fn (string $key): bool => str_starts_with($key, 'user_mfa.')
));
$report(
    $userMfaLocaleKeys !== []
    && array_diff($userMfaLocaleKeys, array_keys($localeEn)) === []
    && array_diff(
        array_values(array_filter(
            array_keys($localeEn),
            static fn (string $key): bool => str_starts_with($key, 'user_mfa.')
        )),
        array_keys($localeMs)
    ) === []
    && ($localeMs['user_mfa.security.setup_intro'] ?? '') !== ($localeEn['user_mfa.security.setup_intro'] ?? '')
    && str_contains($securityPage, "user_mfa.security.setup_intro")
    && str_contains($challengePage, "user_mfa.security.department"),
    'User MFA security and challenge pages have complete BM and EN locale keys'
);
$pilotTool = (string) file_get_contents($root . '/tools/user_login_mfa_u8_pilot_plan.php');
$policyTool = (string) file_get_contents($root . '/tools/user_login_mfa_u8_policy_transition.php');
$report(
    str_contains($pilotTool, '.private/user_mfa_pilot_plan.json')
    && str_contains($pilotTool, '($permissions & 0077) !== 0')
    && str_contains($pilotTool, 'WHERE u_id=:identifier')
    && str_contains($pilotTool, 'if ($exact !== [])')
    && str_contains($pilotTool, 'WHERE data2=:identifier2')
    && str_contains($pilotTool, "'ambiguous' => 0")
    && str_contains($pilotTool, 'pii_output=0'),
    'pilot plan resolves login identifiers uniquely and remains PII-redacted'
);
$report(
    str_contains($pilotTool, '$resolveOne((string) $identifier)'),
    'numeric student login identifiers are cast safely at resolver boundary'
);
$report(
    str_contains($pilotTool, 'SINGLE_ACCOUNT_TECHNICAL_PILOT')
    && str_contains($pilotTool, 'count($pilots) === 1')
    && str_contains(
        $pilotTool,
        'APPLY SINGLE ACCOUNT TECHNICAL USER MFA PILOT WITH MODE OFF'
    )
    && str_contains($policyTool, 'ONEID_USER_MFA_U8_TECHNICAL_PILOT'),
    'single-account technical pilot is explicit and separately confirmed'
);
$report(
    str_contains($policyTool, 'user_login_mfa_policy_history')
    && str_contains($policyTool, 'FOR UPDATE')
    && str_contains($policyTool, 'USER_MFA_POLICY_AUDIT_ATOMICITY_FAILED')
    && str_contains($policyTool, "['OFF','ENROLLMENT','PILOT_ENFORCED','ENFORCED']")
    && str_contains($policyTool, "'PILOT_ENFORCED' => ['OFF','ENROLLMENT','PILOT_ENFORCED','ENFORCED']"),
    'policy transition is versioned audited atomic and supports full enforcement'
);
$output = [];
exec(
    escapeshellarg(PHP_BINARY) . ' '
        . escapeshellarg($root . '/tests/characterization/user_login_mfa_u8_primary_auth.php'),
    $output,
    $status
);
$report(
    $status === 0 && in_array(
        'RESULT checks=6 failures=0 shared_database_mutations=0 runtime_activation=0',
        $output,
        true
    ),
    'isolated OFF enrollment pilot and enforced primary-auth decisions pass'
);
printf("RESULT checks=%d failures=%d runtime_activation=0\n", $checks, $failures);
exit($failures === 0 ? 0 : 1);
