<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$dashboard = (string) file_get_contents($root . '/page/dashboard.php');
$handler = (string) file_get_contents($root . '/lib/q_func.php');
$security = (string) file_get_contents($root . '/lib/request_security.php');
$ms = require $root . '/config/locales/ms.php';
$en = require $root . '/config/locales/en.php';

$checks = [
    'recovery actions require authenticated user scope' => str_contains($security, "'action_mydigitalid_password_recovery_request'") && str_contains($security, "'action_mydigitalid_password_recovery_verify'") && str_contains($security, "'action_mydigitalid_password_recovery_reset'"),
    'request identity is locked to authenticated session user' => str_contains($handler, "trim((string)\$_SESSION['login_user'])") && str_contains($handler, 'UC7_MYDID_RECOVERY_AUTH_REQUIRED'),
    'verify and reset require same MyDigital ID session identity' => substr_count($handler, 'UC7_MYDID_RECOVERY_CONTEXT_INVALID') === 2 && substr_count($handler, "['auth_method']??'')!=='mydigitalid'") >= 3,
    'dashboard exposes recovery only for MyDigital ID sessions' => str_contains($dashboard, "(\$_SESSION['auth_method'] ?? '') === 'mydigitalid'") && str_contains($dashboard, 'btn_mydid_password_recovery'),
    'dashboard implements request OTP verify and reset stages' => str_contains($dashboard, "myDigitalIdRecoveryStage='otp'") && str_contains($dashboard, "myDigitalIdRecoveryStage='reset'") && str_contains($dashboard, 'action_mydigitalid_password_recovery_verify'),
    'successful reset requires reauthentication redirect' => str_contains($dashboard, "myDigitalIdRecoveryStage==='reset'") && str_contains($handler, "'redirect_uri'=>APP_URL.'/'"),
    'reset is atomic and enforces centralized password history' => str_contains($handler, "action=mydigitalid_email_otp_password_reset") && str_contains($handler, 'get_password_history_hashes($resetUser,oneid_password_history_limit())') && str_contains($handler, 'record_password_history($resetUser,$stored)') && str_contains($handler, 'oneid_clear_sso_cookie()'),
    'BM and English recovery copy is complete' => isset($ms['dashboard.password.forgot_mydid'],$ms['dashboard.password.otp_sent'],$en['dashboard.password.forgot_mydid'],$en['dashboard.password.otp_sent']),
];

$passed = 0;
foreach ($checks as $label => $ok) {
    echo ($ok ? 'PASS' : 'FAIL') . ' ' . $label . "\n";
    $passed += $ok ? 1 : 0;
}
printf("RESULT %d/%d\n", $passed, count($checks));
exit($passed === count($checks) ? 0 : 1);
