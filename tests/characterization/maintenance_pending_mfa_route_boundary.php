<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Maintenance/MaintenanceGate.php';

use OneId\App\Maintenance\MaintenanceGate;

$checks = [
    'challenge page is allowed' => MaintenanceGate::isPendingAdminMfaRoute('/page/user-mfa-challenge', 'GET', []),
    'challenge source path is allowed' => MaintenanceGate::isPendingAdminMfaRoute('/oneid/page/user_mfa_challenge.php', 'GET', []),
    'root login remains blocked' => !MaintenanceGate::isPendingAdminMfaRoute('/', 'GET', []),
    'admin login remains blocked by pending state alone' => !MaintenanceGate::isPendingAdminMfaRoute('/admin/login.php', 'GET', []),
    'MyDigital ID login remains blocked' => !MaintenanceGate::isPendingAdminMfaRoute('/auth/mydigitalid/login.php', 'GET', []),
    'dashboard remains blocked' => !MaintenanceGate::isPendingAdminMfaRoute('/page/dashboard', 'GET', []),
    'MFA verification is allowed' => MaintenanceGate::isPendingAdminMfaRoute('/lib/q_func', 'POST', ['user_mfa_totp_verify_login' => '1']),
    'MFA cancellation is allowed' => MaintenanceGate::isPendingAdminMfaRoute('/lib/q_func.php', 'POST', ['user_mfa_cancel_login' => '1']),
    'ordinary authentication POST remains blocked' => !MaintenanceGate::isPendingAdminMfaRoute('/lib/q_func', 'POST', ['auth' => 'auth']),
    'mixed cancel and authentication POST is blocked' => !MaintenanceGate::isPendingAdminMfaRoute('/lib/q_func', 'POST', ['user_mfa_cancel_login' => '1', 'auth' => 'auth']),
    'multiple MFA actions in one POST are blocked' => !MaintenanceGate::isPendingAdminMfaRoute('/lib/q_func', 'POST', ['user_mfa_cancel_login' => '1', 'user_mfa_email_request' => '1']),
    'MFA action on an unrelated endpoint is blocked' => !MaintenanceGate::isPendingAdminMfaRoute('/auth/mydigitalid/login.php', 'POST', ['user_mfa_cancel_login' => '1']),
];

$failures = 0;
foreach ($checks as $label => $passed) {
    echo ($passed ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
    if (!$passed) $failures++;
}
echo 'RESULT checks=' . count($checks) . ' failures=' . $failures . PHP_EOL;
exit($failures === 0 ? 0 : 1);
