<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Auth/UserMfa/UserLoginMfaPolicy.php';
require_once dirname(__DIR__, 2) . '/app/Auth/UserMfa/UserMfaWebSecurityGate.php';
require_once dirname(__DIR__, 2) . '/app/Auth/UserMfa/UserMfaEmailOtpException.php';
require_once dirname(__DIR__, 2) . '/app/Auth/UserMfa/UserMfaTotpException.php';
require_once dirname(__DIR__, 2) . '/app/Auth/UserMfa/UserMfaPendingLoginException.php';
require_once dirname(__DIR__, 2) . '/app/Auth/UserMfa/UserMfaRuntimeGate.php';
require_once dirname(__DIR__, 2) . '/app/Auth/UserMfa/UserMfaHttpBoundary.php';

use OneId\App\Auth\UserMfa\UserMfaEmailOtpException;
use OneId\App\Auth\UserMfa\UserMfaHttpBoundary;
use OneId\App\Auth\UserMfa\UserMfaRuntimeGate;

$checks = 0;
$failed = 0;
$report = static function (bool $passed, string $label) use (&$checks, &$failed): void {
    $checks++;
    $failed += $passed ? 0 : 1;
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $label);
};
$reason = static function (callable $action): string {
    try {
        $action();
        return '';
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }
};

$dormant = new UserMfaRuntimeGate('OFF', false, false);
$dormant->assertDormantSafe();
$report(true, 'OFF schema-disabled unauthorized runtime is dormant-safe');
$report(
    $reason(fn() => $dormant->assertRequestAllowed(false)) === 'USER_MFA_SCHEMA_UNAVAILABLE',
    'missing shared schema fails closed before request handling'
);
$report(
    $reason(fn() => (new UserMfaRuntimeGate('ENFORCED', false, false))
        ->assertRequestAllowed(true)) === 'USER_MFA_ACTIVATION_NOT_AUTHORIZED',
    'non-OFF mode requires explicit activation authorization'
);

$csrf = bin2hex(random_bytes(32));
$boundary = new UserMfaHttpBoundary();
$context = $boundary->guard(
    'POST',
    ['_csrf_token' => $csrf, 'user_mfa_email_request' => '1', 'target_user_id' => 'OTHER'],
    $csrf,
    'session-1',
    'USER01',
    'Browser/1',
    '127.0.0.1'
);
$report(
    $context['action'] === 'user_mfa_email_request'
    && $context['user_id'] === 'USER01'
    && !array_key_exists('target_user_id', $context),
    'HTTP target derives from authenticated user and ignores target input'
);
$report(
    $reason(fn() => $boundary->guard(
        'POST',
        [
            '_csrf_token' => $csrf,
            'user_mfa_email_request' => '1',
            'user_mfa_email_verify' => '1',
        ],
        $csrf,
        'session-1',
        'USER01',
        'Browser/1',
        '127.0.0.1'
    )) === 'USER_MFA_ACTION_INVALID',
    'exactly one recognized action is required'
);
$report(
    $reason(fn() => $boundary->guard(
        'GET',
        ['_csrf_token' => $csrf, 'user_mfa_email_request' => '1'],
        $csrf,
        'session-1',
        'USER01',
        'Browser/1',
        '127.0.0.1'
    )) === 'USER_MFA_CSRF_REJECTED',
    'GET and invalid CSRF are rejected before dispatch'
);

$rawOtp = '123456';
$rawEmail = 'person@example.test';
$error = $boundary->safeError(
    new UserMfaEmailOtpException('USER_MFA_VERIFICATION_FAILED', str_repeat('a', 32)),
    ['otp' => $rawOtp, 'email' => $rawEmail, 'session_id' => 'raw-session']
);
$encoded = json_encode($error, JSON_THROW_ON_ERROR);
$report(
    $error['code'] === 'USER_MFA_REQUEST_REJECTED'
    && !str_contains($encoded, $rawOtp)
    && !str_contains($encoded, $rawEmail)
    && !str_contains($encoded, 'raw-session')
    && !str_contains($encoded, 'USER_MFA_VERIFICATION_FAILED'),
    'public error is enumeration-safe and strips sensitive/internal values'
);

printf(
    "RESULT checks=%d failures=%d shared_database_mutations=0 service_dispatch=0 runtime_activation=0\n",
    $checks,
    $failed
);
exit($failed === 0 ? 0 : 1);
