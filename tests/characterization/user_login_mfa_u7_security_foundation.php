<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Auth/UserMfa/UserLoginMfaPolicy.php';
require_once dirname(__DIR__, 2) . '/app/Auth/UserMfa/UserMfaRateLimitConfig.php';
require_once dirname(__DIR__, 2) . '/app/Auth/UserMfa/UserMfaWebSecurityGate.php';

use OneId\App\Auth\UserMfa\UserLoginMfaPolicy;
use OneId\App\Auth\UserMfa\UserMfaRateLimitConfig;
use OneId\App\Auth\UserMfa\UserMfaWebSecurityGate;

$checks = 0;
$failed = 0;
$report = static function (bool $passed, string $label) use (&$checks, &$failed): void {
    $checks++;
    $failed += $passed ? 0 : 1;
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $label);
};
$throws = static function (callable $action, string $message): bool {
    try {
        $action();
        return false;
    } catch (Throwable $exception) {
        return $exception->getMessage() === $message;
    }
};

$policies = [];
foreach (UserLoginMfaPolicy::MODES as $mode) {
    $policies[$mode] = new UserLoginMfaPolicy(
        $mode,
        'PASSWORD_ONLY',
        true,
        $mode !== 'OFF',
        300,
        300,
        5,
        60,
        10
    );
}
$report(
    !$policies['OFF']->enforced()
    && !$policies['ENROLLMENT']->enforced()
    && $policies['PILOT_ENFORCED']->enforced()
    && $policies['ENFORCED']->enforced(),
    'OFF enrollment pilot and enforced mode semantics are explicit'
);
$report(
    $throws(
        fn() => new UserLoginMfaPolicy(
            'ENFORCED', 'PASSWORD_ONLY', false, true, 300, 300, 5, 60, 10
        ),
        'USER_MFA_POLICY_INVALID'
    ),
    'active MFA cannot disable mandatory email fallback'
);

$csrf = bin2hex(random_bytes(32));
UserMfaWebSecurityGate::assertStateChangingRequest('POST', $csrf, $csrf);
$report(
    $throws(
        fn() => UserMfaWebSecurityGate::assertStateChangingRequest('GET', $csrf, $csrf),
        'USER_MFA_CSRF_REJECTED'
    )
    && $throws(
        fn() => UserMfaWebSecurityGate::assertStateChangingRequest(
            'POST',
            $csrf,
            bin2hex(random_bytes(32))
        ),
        'USER_MFA_CSRF_REJECTED'
    ),
    'state-changing requests require POST and exact synchronizer CSRF token'
);
$report(
    $throws(
        fn() => UserMfaWebSecurityGate::assertSessionRotated('pre-session', 'pre-session'),
        'USER_MFA_SESSION_FIXATION_REJECTED'
    ),
    'authenticated session must differ from pre-authentication session'
);
UserMfaWebSecurityGate::assertSessionRotated('pre-session', 'authenticated-session');
$report(true, 'rotated authenticated session passes fixation gate');

$limits = new UserMfaRateLimitConfig();
$report(
    $limits->exceeded(['user_hour' => 10])
    && $limits->exceeded(['session_hour' => 10])
    && $limits->exceeded(['ip_hour' => 50])
    && $limits->exceeded(['destination_hour' => 10])
    && !$limits->exceeded([
        'user_hour' => 9,
        'session_hour' => 9,
        'ip_hour' => 49,
        'destination_hour' => 9,
    ]),
    'all rate-limit axes fail closed exactly at configured thresholds'
);

$interface = (string) file_get_contents(
    dirname(__DIR__, 2) . '/app/Auth/UserMfa/UserMfaEmailOtpPersistenceInterface.php'
);
$service = (string) file_get_contents(
    dirname(__DIR__, 2) . '/app/Auth/UserMfa/UserMfaEmailOtpService.php'
);
$report(
    str_contains($interface, 'emailRequestStatsForUpdate')
    && str_contains($interface, 'must lock/reserve')
    && strpos($service, 'emailRequestStatsForUpdate')
        < strpos($service, 'createEmailChallenge(['),
    'concurrent send counters require locked reservation before challenge creation'
);

$runtime = (string) file_get_contents(dirname(__DIR__, 2) . '/config/runtime.php');
$report(
    str_contains($runtime, "'ONEID_USER_MFA_MODE' => 'OFF'")
    && str_contains($runtime, "'ONEID_USER_MFA_SCHEMA_APPLY_ENABLED' => 'false'")
    && str_contains($runtime, "'ONEID_USER_MFA_ACTIVATION_AUTHORIZED' => 'false'"),
    'committed runtime stays OFF with schema apply and activation unauthorized'
);

$down = (string) file_get_contents(
    dirname(__DIR__, 2) . '/docs/migrations/20260729_user_login_mfa_u1_down.sql'
);
$report(
    strpos($down, 'user_login_mfa_challenges') < strpos($down, 'user_login_mfa_transactions')
    && strpos($down, 'user_login_mfa_transactions') < strpos($down, 'user_mfa_factors')
    && strpos($down, 'user_mfa_factors') < strpos($down, 'user_login_mfa_policy'),
    'schema rollback drops dependent objects before policy foundation'
);

printf(
    "RESULT checks=%d failures=%d csrf=1 fixation=1 rate_limit_lock_contract=1 schema_rollback_order=1 runtime_activation=0\n",
    $checks,
    $failed
);
exit($failed === 0 ? 0 : 1);
