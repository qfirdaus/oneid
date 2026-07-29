<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

$root = dirname(__DIR__);
$read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);
$baseline = $read('docs/USER_LOGIN_MFA_U0_BASELINE_DAN_CONTRACT.md');
$plan = $read('docs/USER_LOGIN_MFA_PELAN_IMPLEMENTASI_BERFASA.md');
$emailTool = $read('tools/user_login_mfa_u0_email_readiness.php');
$adminOtpContract = $read('tools/f7_2_email_otp_service_contract.php');

$checks = [
    'u0_is_closed_without_activation' => str_contains($baseline, '**Status:** `PASS / CLOSED FOR U0`')
        && str_contains($baseline, 'Global activation kekal `NOT AUTHORIZED`'),
    'pending_login_boundary_is_locked' => str_contains($baseline, 'Hanya selepas faktor kedua lulus')
        && str_contains($baseline, '`PASSWORD_ONLY`'),
    'threat_model_has_critical_boundaries' => str_contains($baseline, 'Token/session sebelum MFA')
        && str_contains($baseline, 'TOTP replay')
        && str_contains($baseline, 'Audit failure hides mutation'),
    'audit_map_is_secret_free' => str_contains($baseline, '`USER_MFA_PRIMARY_AUTH_PENDING`')
        && str_contains($baseline, '`USER_MFA_POLICY_CHANGE`')
        && str_contains($baseline, 'Dilarang: raw OTP/TOTP'),
    'recovery_uses_existing_admins' => str_contains($baseline, 'Administrator kedua bertindak sebagai verifier')
        && str_contains($baseline, 'Admin tidak enroll factor bagi pihak pengguna'),
    'email_readiness_is_aggregate_only' => str_contains($emailTool, 'raw_email_output=0')
        && str_contains($emailTool, 'mutation_statements=0')
        && !str_contains($emailTool, 'echo $row[\'email\']'),
    'admin_otp_fixture_matches_current_limit' => str_contains($adminOtpContract, "\$op4->stats['admin_hour']=10"),
    'plan_records_u0_closure' => str_contains($plan, '### B. U0 implementation readiness — `PASS / CLOSED`')
        && str_contains($plan, 'USER_LOGIN_MFA_U0_BASELINE_DAN_CONTRACT.md'),
];

$failed = count(array_filter($checks, static fn(bool $passed): bool => !$passed));
foreach ($checks as $name => $passed) {
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $name);
}
printf("RESULT checks=%d failures=%d runtime_mutations=0 schema_mutations=0\n", count($checks), $failed);
exit($failed === 0 ? 0 : 1);
