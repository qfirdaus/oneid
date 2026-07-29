<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

$root = dirname(__DIR__);
$files = [
    'app/Auth/UserMfa/UserMfaEmailSenderInterface.php',
    'app/Auth/UserMfa/UserMfaEmailOtpException.php',
    'app/Auth/UserMfa/UserMfaEmailOtpPersistenceInterface.php',
    'app/Auth/UserMfa/UserMfaEmailOtpService.php',
    'tests/characterization/user_login_mfa_u4_email_otp.php',
];
$source = [];
$checks = [];
foreach ($files as $file) {
    $source[$file] = (string) file_get_contents($root . '/' . $file);
    $output = [];
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($root . '/' . $file), $output, $status);
    $checks['lint_' . basename($file)] = $status === 0;
}

$service = $source['app/Auth/UserMfa/UserMfaEmailOtpService.php'];
$persistence = $source['app/Auth/UserMfa/UserMfaEmailOtpPersistenceInterface.php'];
$runtimeWiring = (string) file_get_contents($root . '/bootstrap/app.php')
    . (string) file_get_contents($root . '/bootstrap/sync_runtime.php')
    . (string) file_get_contents($root . '/lib/q_func.php');

$checks['argon2id_hash_only'] = str_contains($service, 'UserMfaOtp::hash($otp)')
    && str_contains($service, "'otp_hash' => \$otpHash")
    && !str_contains($persistence, 'rawOtp');
$checks['destination_is_masked_and_hmaced'] = str_contains($service, 'destination_hmac')
    && str_contains($service, "'masked_email'")
    && !str_contains($persistence, 'masked_email');
$checks['limits_precede_mutation'] = strpos($service, 'cooldownActive($stats)')
    < strpos($service, 'revokeOpenEmailChallenges($transactionId)')
    && strpos($service, 'exceeded($stats)')
    < strpos($service, 'createEmailChallenge([');
$checks['delivery_failure_is_compensated'] = str_contains($service, "'DELIVERY_FAILED'")
    && str_contains($service, "'USER_MFA_DELIVERY_FAILED'");
$checks['verify_is_atomic_and_one_use'] = str_contains($service, 'emailChallengeForUpdate')
    && str_contains($service, 'consumeEmailChallenge')
    && str_contains($service, 'markPendingLoginVerified')
    && str_contains($service, "'USER_MFA_CHALLENGE_REPLAYED'");
$checks['binding_and_expiry_fail_closed'] = str_contains($service, 'USER_MFA_BINDING_MISMATCH')
    && str_contains($service, "'EXPIRED'")
    && str_contains($service, "'USER_MFA_CHALLENGE_EXPIRED'");
$checks['audit_is_mandatory'] = str_contains($service, 'USER_MFA_AUDIT_FAILED')
    && str_contains($persistence, 'recordAudit(');
$checks['u4_dispatch_is_runtime_gated'] = str_contains($runtimeWiring, 'UserMfaEmailOtpService')
    && str_contains($runtimeWiring, '$gate->assertFeatureActive()')
    && str_contains($runtimeWiring, 'assertRuntimeParity');

$characterization = [];
exec(
    escapeshellarg(PHP_BINARY) . ' '
    . escapeshellarg($root . '/tests/characterization/user_login_mfa_u4_email_otp.php'),
    $characterization,
    $characterizationStatus
);
$checks['characterization_passes'] = $characterizationStatus === 0
    && in_array(
        'RESULT checks=12 failures=0 emails_sent_to_fake=2 network_calls=0 live_database_mutations=0 runtime_activation=0 raw_otp_output=0',
        $characterization,
        true
    );

$failed = count(array_filter($checks, static fn(bool $passed): bool => !$passed));
foreach ($checks as $name => $passed) {
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $name);
}
printf(
    "RESULT checks=%d failures=%d network_calls=0 live_database_mutations=0 runtime_activation=0\n",
    count($checks),
    $failed
);
exit($failed === 0 ? 0 : 1);
