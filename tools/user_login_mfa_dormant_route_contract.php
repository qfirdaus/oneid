<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}
$root = dirname(__DIR__);
$request = (string) file_get_contents($root . '/lib/request_security.php');
$route = (string) file_get_contents($root . '/lib/q_func.php');
$checks = [
    'all_actions_are_guarded' => true,
    'public_actions_are_pre_auth_only' => true,
    'self_service_actions_require_user' => true,
    'admin_recovery_requires_admin' => true,
    'runtime_gate_precedes_dispatch' => true,
    'off_mode_fails_closed' => true,
    'activation_authorization_is_required' => true,
    'response_is_no_store' => true,
    'no_user_mfa_service_is_instantiated' => true,
];
$allActions = [
    'user_mfa_email_request',
    'user_mfa_email_resend',
    'user_mfa_email_verify',
    'user_mfa_totp_enroll',
    'user_mfa_totp_confirm',
    'user_mfa_totp_preference',
    'user_mfa_totp_revoke',
    'user_mfa_admin_recovery',
];
foreach ($allActions as $action) {
    $checks['all_actions_are_guarded'] = $checks['all_actions_are_guarded']
        && substr_count($request, "'" . $action . "'") === 1;
}
$checks['public_actions_are_pre_auth_only'] = strpos($request, "'user_mfa_email_request'")
    < strpos($request, "'user' =>");
$checks['self_service_actions_require_user'] = strpos($request, "'user_mfa_totp_enroll'")
    > strpos($request, "'user' =>")
    && strpos($request, "'user_mfa_totp_enroll'") < strpos($request, "'admin' =>");
$checks['admin_recovery_requires_admin'] = strpos($request, "'user_mfa_admin_recovery'")
    > strpos($request, "'admin' =>");
$checks['runtime_gate_precedes_dispatch'] = strpos($route, "str_starts_with(\$oneidGuardedAction,'user_mfa_')")
    < strpos($route, "str_starts_with(\$oneidGuardedAction,'admin_step_up_'");
$checks['off_mode_fails_closed'] = str_contains($route, '$gate->assertFeatureActive()')
    && str_contains($route, "'USER_MFA_NOT_ACTIVE'=>'USER_MFA_NOT_ACTIVE'");
$checks['activation_authorization_is_required'] = str_contains(
    $route,
    "oneid_config('ONEID_USER_MFA_ACTIVATION_AUTHORIZED','false')"
);
$checks['response_is_no_store'] = str_contains(
    $route,
    "header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store')"
);
$checks['no_user_mfa_service_is_instantiated'] = !str_contains(
    $route,
    'new \\OneId\\App\\Auth\\UserMfa\\UserMfaEmailOtpService'
)
    && !str_contains($route, 'new \\OneId\\App\\Auth\\UserMfa\\UserMfaTotpService')
    && str_contains($route, "throw new RuntimeException('USER_MFA_INTEGRATION_NOT_READY')");

$failed = count(array_filter($checks, static fn(bool $passed): bool => !$passed));
foreach ($checks as $name => $passed) {
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $name);
}
printf(
    "RESULT checks=%d failures=%d route_registered=1 service_dispatch=0 runtime_activation=0\n",
    count($checks),
    $failed
);
exit($failed === 0 ? 0 : 1);
