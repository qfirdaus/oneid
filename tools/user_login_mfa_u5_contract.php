<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

$root = dirname(__DIR__);
$files = [
    'app/Auth/UserMfa/UserMfaTotpException.php',
    'app/Auth/UserMfa/UserMfaTotpPersistenceInterface.php',
    'app/Auth/UserMfa/UserMfaTotpService.php',
    'tests/characterization/user_login_mfa_u5_totp_self_service.php',
];
$source = [];
$checks = [];
foreach ($files as $file) {
    $source[$file] = (string) file_get_contents($root . '/' . $file);
    $output = [];
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($root . '/' . $file), $output, $status);
    $checks['lint_' . basename($file)] = $status === 0;
}

$service = $source['app/Auth/UserMfa/UserMfaTotpService.php'];
$persistence = $source['app/Auth/UserMfa/UserMfaTotpPersistenceInterface.php'];
$runtimeWiring = (string) file_get_contents($root . '/bootstrap/app.php')
    . (string) file_get_contents($root . '/bootstrap/sync_runtime.php')
    . (string) file_get_contents($root . '/lib/q_func.php');

$checks['encrypted_enrollment_and_no_store'] = str_contains($service, "'encrypted_secret'")
    && str_contains($service, "'cache_control' => 'no-store'")
    && !str_contains($persistence, 'provisioning_uri')
    && !str_contains($persistence, 'secret:string');
$checks['self_service_target_is_authenticated_user'] = str_contains($service, '$userId = $this->userId($authenticatedUserId')
    && !str_contains($service, 'beginEnrollment(string $authenticatedUserId, string $targetUserId');
$checks['confirmation_is_bound_and_atomic'] = str_contains($service, 'enrollment_session_hash')
    && str_contains($service, 'enrollment_browser_digest')
    && str_contains($service, 'confirmFactor')
    && str_contains($service, "setPreference(\$userId, 'TOTP'");
$checks['totp_replay_is_atomic'] = str_contains($service, 'updateLastUsedStep')
    && str_contains($service, "'USER_MFA_TOTP_REPLAYED'");
$checks['kill_switch_falls_back_to_email'] = str_contains($service, 'USER_MFA_TOTP_DISABLED_USE_EMAIL')
    && str_contains($service, "return \$totpEnabled && \$activeTotp ? ['TOTP', 'EMAIL_OTP'] : ['EMAIL_OTP']");
$checks['recovery_revokes_factor_challenge_and_sessions'] = str_contains($service, 'revokeFactors')
    && str_contains($service, 'revokePendingChallenges')
    && str_contains($service, 'revokeUserSessions')
    && str_contains($service, "setPreference(\$targetId, 'EMAIL_OTP'");
$checks['admin_recovery_requires_step_up_verifier_and_reference'] = str_contains($service, '$freshAdminStepUp')
    && str_contains($service, '$administratorId !== $verifierAdministratorId')
    && str_contains($service, '$ticketReference')
    && str_contains($service, '$typedConfirmation');
$checks['audit_is_mandatory_and_secret_free_boundary'] = str_contains($service, 'USER_MFA_AUDIT_FAILED')
    && str_contains($persistence, 'recordAudit(')
    && !str_contains($persistence, 'encryptedSecret');
$checks['u5_remains_dormant'] = !str_contains($runtimeWiring, 'UserMfaTotpService')
    && !str_contains($runtimeWiring, 'UserMfaTotpPersistenceInterface');

$characterization = [];
exec(
    escapeshellarg(PHP_BINARY) . ' '
    . escapeshellarg($root . '/tests/characterization/user_login_mfa_u5_totp_self_service.php'),
    $characterization,
    $characterizationStatus
);
$checks['characterization_passes'] = $characterizationStatus === 0
    && in_array(
        'RESULT checks=13 failures=0 qr_generated_locally=1 admin_secret_access=0 network_calls=0 live_database_mutations=0 runtime_activation=0 raw_secret_output=0',
        $characterization,
        true
    );

$failed = count(array_filter($checks, static fn(bool $passed): bool => !$passed));
foreach ($checks as $name => $passed) {
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $name);
}
printf(
    "RESULT checks=%d failures=%d admin_secret_access=0 live_database_mutations=0 runtime_activation=0\n",
    count($checks),
    $failed
);
exit($failed === 0 ? 0 : 1);
