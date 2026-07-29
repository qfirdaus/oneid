<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

$root = dirname(__DIR__);
$files = [
    'app/Auth/UserMfa/UserMfaOtp.php',
    'app/Auth/UserMfa/UserMfaRateLimitConfig.php',
    'app/Auth/UserMfa/UserMfaRequestBinding.php',
    'app/Auth/UserMfa/UserMfaTotpPrimitive.php',
    'tests/characterization/user_login_mfa_u2_primitives.php',
];
$source = [];
$checks = [];
foreach ($files as $file) {
    $source[$file] = (string) file_get_contents($root . '/' . $file);
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($root . '/' . $file), $output, $status);
    $checks['lint_' . basename($file)] = $status === 0;
    $output = [];
}

$otp = $source['app/Auth/UserMfa/UserMfaOtp.php'];
$limits = $source['app/Auth/UserMfa/UserMfaRateLimitConfig.php'];
$binding = $source['app/Auth/UserMfa/UserMfaRequestBinding.php'];
$totp = $source['app/Auth/UserMfa/UserMfaTotpPrimitive.php'];
$bootstrap = (string) file_get_contents($root . '/bootstrap/app.php')
    . (string) file_get_contents($root . '/bootstrap/sync_runtime.php')
    . (string) file_get_contents($root . '/lib/q_func.php');

$checks['otp_is_argon2id_and_format_bounded'] = str_contains($otp, 'PASSWORD_ARGON2ID')
    && str_contains($otp, "/\\A[0-9]{6}\\z/");
$checks['limits_are_user_specific'] = str_contains($limits, "'user_hour'")
    && str_contains($limits, "'destination_hour'")
    && !str_contains($limits, 'admin_hour');
$checks['binding_does_not_return_raw_session'] = str_contains($binding, "'session_hash'")
    && !str_contains($binding, "'session_id' =>");
$checks['totp_reuses_generic_crypto_only'] = str_contains($totp, 'TotpSecretCipher')
    && str_contains($totp, 'Totp::matchTimeStep')
    && !str_contains($totp, 'AdminStepUp')
    && !str_contains($totp, 'AdminTotp');
$checks['u2_wiring_remains_runtime_gated'] = str_contains($bootstrap, 'UserMfaTotpPrimitive')
    && str_contains($bootstrap, '$gate->assertFeatureActive()')
    && str_contains($bootstrap, 'ONEID_USER_MFA_ACTIVATION_AUTHORIZED');

exec(
    escapeshellarg(PHP_BINARY)
    . ' '
    . escapeshellarg($root . '/tests/characterization/user_login_mfa_u2_primitives.php'),
    $characterization,
    $characterizationStatus
);
$checks['characterization_passes'] = $characterizationStatus === 0
    && in_array('RESULT checks=8 failures=0 network_calls=0 database_mutations=0 runtime_activation=0 raw_secret_output=0', $characterization, true);

$failed = count(array_filter($checks, static fn(bool $passed): bool => !$passed));
foreach ($checks as $name => $passed) {
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $name);
}
printf(
    "RESULT checks=%d failures=%d feature_activation=0 database_mutations=0\n",
    count($checks),
    $failed
);
exit($failed === 0 ? 0 : 1);
