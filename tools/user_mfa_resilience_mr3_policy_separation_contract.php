<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

use OneId\App\Auth\UserMfa\UserLoginMfaPolicy;
use OneId\App\Maintenance\MaintenanceMfaPolicy;
use OneId\App\Maintenance\MaintenanceMfaRuntimeGate;

$root = dirname(__DIR__);
$api = (string) file_get_contents($root . '/lib/q_func.php');
$challenge = (string) file_get_contents($root . '/page/user_mfa_challenge.php');
$runtime = (string) file_get_contents($root . '/config/runtime.php');
$globalPolicy = (string) file_get_contents($root . '/app/Admin/UserMfaGlobalPolicyService.php');
$phase = (string) file_get_contents($root . '/docs/USER_MFA_RESILIENCE_MR3_POLICY_SEPARATION.md');

$userOff = UserLoginMfaPolicy::committedDefault();
$maintenance = new MaintenanceMfaPolicy('ENFORCED', true, true, 300, 300, 5, 60, 10, 1);
$maintenanceLogin = $maintenance->loginPolicy();
$gate = new MaintenanceMfaRuntimeGate(true, true, true, true);
$gatePassed = true;
try {
    $gate->assertAvailable(true, $maintenance);
} catch (Throwable) {
    $gatePassed = false;
}
$disabledRejected = false;
try {
    (new MaintenanceMfaPolicy('DISABLED', true, false, 300, 300, 5, 60, 10, 1))->loginPolicy();
} catch (InvalidArgumentException $exception) {
    $disabledRejected = $exception->getMessage() === 'MAINTENANCE_MFA_DISABLED';
}
$gateFailClosed = false;
try {
    (new MaintenanceMfaRuntimeGate(false, true, true, true))->assertAvailable(true, $maintenance);
} catch (RuntimeException $exception) {
    $gateFailClosed = $exception->getMessage() === 'MAINTENANCE_MFA_RUNTIME_UNAVAILABLE';
}

$checks = [
    'user OFF and maintenance ENFORCED are independent policy objects' =>
        $userOff->mode === 'OFF' && $maintenanceLogin->mode === 'ENFORCED',
    'maintenance policy always produces password scoped enforced login' =>
        $maintenanceLogin->scope === 'PASSWORD_ONLY' && $maintenanceLogin->enforced(),
    'disabled maintenance policy fails closed' => $disabledRejected,
    'maintenance runtime gate accepts exact authorized state' => $gatePassed,
    'maintenance runtime gate rejects disabled ceiling' => $gateFailClosed,
    'maintenance runtime keys are separate and dormant by default' =>
        str_contains($runtime, "'ONEID_MAINTENANCE_MFA_ENABLED' => 'false'")
        && str_contains($runtime, "'ONEID_MAINTENANCE_MFA_ACTIVATION_AUTHORIZED' => 'false'"),
    'ordinary user runtime authorization check excludes maintenance routes' =>
        str_contains($api, '!$maintenanceAdminLogin&&!$maintenanceDeveloperLogin&&$userMfaMode'),
    'initial maintenance login reads independent database policy' =>
        substr_count($api, 'PdoMaintenanceMfaPolicyReader') >= 2
        && substr_count($api, '$policy=$maintenanceMfaPolicy') >= 2
        && str_contains($api, "require_once dirname(__DIR__) . '/app/Maintenance/PdoMaintenanceMfaPolicyReader.php'"),
    'pending maintenance challenge reads independent policy and gate' =>
        str_contains($api, '$maintenanceMfaPending')
        && str_contains($api, 'MaintenanceMfaRuntimeGate'),
    'challenge presentation reads maintenance policy and enforces TOTP setting' =>
        str_contains($challenge, "\$policyTable=\$maintenanceMfa?'maintenance_mfa_policy':'user_login_mfa_policy'")
        && str_contains($challenge, 'ONEID_MAINTENANCE_MFA_TOTP_ENABLED')
        && str_contains($api, 'MAINTENANCE_MFA_TOTP_DISABLED'),
    'ordinary login still uses primary auth decision and User MFA reader' =>
        str_contains($api, 'UserMfaPrimaryAuthDecision')
        && str_contains($api, 'PdoUserMfaPolicyReader'),
    'Admin Step-Up maintenance factor checks remain present' =>
        str_contains($api, 'admin_step_up_factor_status')
        && str_contains($api, "purpose'=>'ADMIN_ACCESS'"),
    'public administrator identity is resolved to internal foreign-key identity' =>
        str_contains($globalPolicy, 'persistenceAdminId')
        && str_contains($globalPolicy, 'u_type=1 AND avail_status=1')
        && str_contains($globalPolicy, '$publicAdminId'),
    'global shutdown purges OTP material while revoking active challenges' =>
        str_contains($globalPolicy, 'c.otp_hash=CASE WHEN c.factor_type=')
        && str_contains($globalPolicy, 'THEN NULL ELSE c.otp_hash END'),
    'phase document records no UI or worker scope' =>
        str_contains($phase, 'UI dan worker') && str_contains($phase, 'di luar skop Fasa 3'),
];

$failed = 0;
foreach ($checks as $label => $passed) {
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $label);
    $failed += $passed ? 0 : 1;
}
printf("RESULT checks=%d failed=%d\n", count($checks), $failed);
exit($failed === 0 ? 0 : 1);
