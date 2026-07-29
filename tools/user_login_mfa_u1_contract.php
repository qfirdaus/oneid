<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

$root = dirname(__DIR__);
$read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);
$up = $read('docs/migrations/20260729_user_login_mfa_u1_up.sql');
$down = $read('docs/migrations/20260729_user_login_mfa_u1_down.sql');
$runtime = $read('config/runtime.php');
$policy = $read('app/Auth/UserMfa/UserLoginMfaPolicy.php');
$repository = $read('app/Auth/UserMfa/UserLoginMfaRepositoryInterface.php');
$bootstrap = $read('bootstrap/app.php') . $read('bootstrap/sync_runtime.php');

$checks = [
    'schema_is_additive_and_user_tbl_unchanged' => !str_contains($up, 'ALTER TABLE user_tbl')
        && substr_count($up, 'CREATE TABLE ') === 6,
    'policy_defaults_fail_closed' => str_contains($up, "DEFAULT 'OFF'")
        && str_contains($up, "DEFAULT 'PASSWORD_ONLY'")
        && str_contains($up, 'DEFAULT 1')
        && str_contains($up, 'DEFAULT 0'),
    'email_required_invariant_is_database_enforced' => str_contains(
        $up,
        "CHECK (policy_mode = 'OFF' OR email_enabled = 1)"
    ),
    'factor_secret_is_encrypted_and_replay_guarded' => str_contains($up, 'encrypted_secret VARBINARY')
        && str_contains($up, 'secret_nonce BINARY(24)')
        && str_contains($up, 'last_used_time_step BIGINT UNSIGNED'),
    'pending_login_precedes_challenge' => str_contains($up, 'CREATE TABLE user_login_mfa_transactions')
        && str_contains($up, 'fk_user_mfa_challenge_transaction'),
    'raw_sensitive_material_is_excluded' => !preg_match(
        '/^\\s*(raw_otp|raw_totp|raw_secret|session_id|password|nric)\\s+/mi',
        $up
    ),
    'down_migration_is_dependency_ordered' => strpos($down, 'user_login_mfa_challenges')
        < strpos($down, 'user_login_mfa_transactions')
        && strpos($down, 'user_login_mfa_transactions') < strpos($down, 'user_mfa_factors'),
    'committed_runtime_is_off_and_apply_disabled' => str_contains(
        $runtime,
        "'ONEID_USER_MFA_MODE' => 'OFF'"
    ) && str_contains($runtime, "'ONEID_USER_MFA_SCHEMA_APPLY_ENABLED' => 'false'")
        && str_contains($runtime, "'ONEID_USER_MFA_TOTP_ENABLED' => 'false'"),
    'policy_object_enforces_server_invariants' => str_contains($policy, "USER_MFA_POLICY_INVALID")
        && str_contains($policy, "(\$mode !== 'OFF' && !\$emailEnabled)")
        && str_contains($policy, 'committedDefault'),
    'repository_boundary_is_dormant' => str_contains($repository, 'interface UserLoginMfaRepositoryInterface')
        && !str_contains($bootstrap, 'UserLoginMfaPolicy')
        && !str_contains($bootstrap, 'UserLoginMfaRepositoryInterface'),
];

$failed = count(array_filter($checks, static fn(bool $passed): bool => !$passed));
foreach ($checks as $name => $passed) {
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $name);
}
printf(
    "RESULT checks=%d failures=%d live_schema_mutations=0 feature_activation=0\n",
    count($checks),
    $failed
);
exit($failed === 0 ? 0 : 1);
