<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/Auth/UserMfa/UserLoginMfaPolicy.php';
require dirname(__DIR__) . '/app/Auth/UserMfa/UserMfaOperationalModeResolver.php';

use OneId\App\Auth\UserMfa\UserLoginMfaPolicy;
use OneId\App\Auth\UserMfa\UserMfaOperationalModeResolver;

$checks = 0;
$failed = 0;
$check = static function (bool $condition, string $label) use (&$checks, &$failed): void {
    $checks++;
    if (!$condition) {
        $failed++;
    }
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
};

$resolver = new UserMfaOperationalModeResolver();
$readerSource = (string) file_get_contents(dirname(__DIR__) . '/app/Auth/UserMfa/PdoUserMfaPolicyReader.php');
$check(str_contains($readerSource, 'UNIX_TIMESTAMP(NOW(6))') && !str_contains($readerSource, 'UNIX_TIMESTAMP(UTC_TIMESTAMP())'), 'database window and current epoch use the same database clock');
$check(in_array('ENROLLMENT', UserLoginMfaPolicy::MODES, true), 'ENROLLMENT is a canonical operational mode');
$check(in_array('EMERGENCY_BYPASS', UserLoginMfaPolicy::MODES, true), 'EMERGENCY_BYPASS is a canonical operational mode');
$enrollment = new UserLoginMfaPolicy('ENROLLMENT', 'PASSWORD_ONLY', true, true, 300, 300, 5, 60, 10);
$bypass = new UserLoginMfaPolicy('EMERGENCY_BYPASS', 'PASSWORD_ONLY', true, true, 300, 300, 5, 60, 10);
$check(!$enrollment->enforced() && $enrollment->selfServiceAvailable(), 'ENROLLMENT permits self-service without login challenge');
$check(!$bypass->enforced() && $bypass->selfServiceAvailable(), 'EMERGENCY_BYPASS permits self-service without login challenge');

$active = $resolver->resolve('EMERGENCY_BYPASS', [
    'request_id' => 12,
    'requested_mode' => 'EMERGENCY_BYPASS',
    'request_status' => 'ACTIVE',
    'applied_policy_version' => 9,
    'restore_mode' => 'PILOT_ENFORCED',
    'starts_at' => '2026-09-07 10:00:00',
    'expires_at' => '2026-09-07 12:00:00',
], strtotime('2026-09-07 11:00:00 UTC'), 9);
$check($active['effective_mode'] === 'EMERGENCY_BYPASS' && $active['bypass_active'], 'active approved window produces temporary password-only mode');

$expired = $resolver->resolve('EMERGENCY_BYPASS', [
    'request_id' => 12,
    'requested_mode' => 'EMERGENCY_BYPASS',
    'request_status' => 'ACTIVE',
    'applied_policy_version' => 9,
    'restore_mode' => 'PILOT_ENFORCED',
    'starts_at' => '2026-09-07 10:00:00',
    'expires_at' => '2026-09-07 12:00:00',
], strtotime('2026-09-07 12:00:00 UTC'), 9);
$check($expired['effective_mode'] === 'PILOT_ENFORCED' && $expired['bypass_expired'], 'expired bypass restores exact prior mode logically');

$stale = $resolver->resolve('EMERGENCY_BYPASS', [
    'request_id' => 12,
    'requested_mode' => 'EMERGENCY_BYPASS',
    'request_status' => 'ACTIVE',
    'applied_policy_version' => 8,
    'restore_mode' => 'ENROLLMENT',
    'starts_at' => '2026-09-07 10:00:00',
    'expires_at' => '2026-09-07 12:00:00',
], strtotime('2026-09-07 11:00:00 UTC'), 9);
$check($stale['effective_mode'] === 'ENFORCED', 'stale bypass request cannot authorize the current policy version');

$missing = $resolver->resolve('EMERGENCY_BYPASS', null, strtotime('2026-09-07 11:00:00 UTC'));
$check($missing['effective_mode'] === 'ENFORCED' && $missing['bypass_expired'], 'missing bypass evidence fails closed');

try {
    $resolver->assertWithinRuntimeCeiling('ENROLLMENT', 'ENFORCED');
    $check(true, 'ENFORCED runtime ceiling permits ENROLLMENT database operation');
} catch (Throwable) {
    $check(false, 'ENFORCED runtime ceiling permits ENROLLMENT database operation');
}
try {
    $resolver->assertWithinRuntimeCeiling('ENFORCED', 'ENROLLMENT');
    $check(false, 'lower runtime ceiling rejects stronger database operation');
} catch (RuntimeException $exception) {
    $check($exception->getMessage() === 'USER_MFA_RUNTIME_DATABASE_POLICY_MISMATCH', 'lower runtime ceiling rejects stronger database operation');
}

echo "RESULT checks={$checks} failed={$failed}" . PHP_EOL;
exit($failed === 0 ? 0 : 1);
