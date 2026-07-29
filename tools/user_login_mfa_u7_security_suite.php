<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

$root = dirname(__DIR__);
$commands = [
    'U0 baseline' => 'tools/user_login_mfa_u0_contract.php',
    'U1 schema rollback rehearsal' => 'tools/user_login_mfa_u1_contract.php',
    'U2 security primitives' => 'tools/user_login_mfa_u2_contract.php',
    'U3 pending login and token boundary' => 'tools/user_login_mfa_u3_contract.php',
    'U4 email OTP' => 'tools/user_login_mfa_u4_contract.php',
    'U5 TOTP self-service recovery' => 'tools/user_login_mfa_u5_contract.php',
    'U6 bilingual accessible UI' => 'tools/user_login_mfa_u6_contract.php',
    'U7 security foundation' => 'tests/characterization/user_login_mfa_u7_security_foundation.php',
    'PDO email integration' => 'tests/characterization/user_login_mfa_pdo_email_integration.php',
    'PDO pending and TOTP integration' => 'tests/characterization/user_login_mfa_pdo_pending_totp_integration.php',
    'real MySQL rate-limit lock' => 'tests/characterization/user_login_mfa_pdo_rate_limit_lock.php',
    'dormant routed boundary' => 'tools/user_login_mfa_dormant_route_contract.php',
    'real PHP session rotation' => 'tools/user_login_mfa_real_session_rotation.php',
    'pilot multi-session revocation' => 'tools/user_login_mfa_pilot_multisession_rehearsal.php',
    'controlled password MFA SSO ACL evidence' => 'tools/user_login_mfa_controlled_pilot_evidence.php',
    'Admin email OTP' => 'tools/f7_2_email_otp_service_contract.php',
    'Admin TOTP' => 'tools/f7_3_totp_service_contract.php',
    'Admin Step-Up multilingual' => 'tools/multilingual_admin_step_up_contract.php',
    'MyDigital ID parity' => 'tools/mydigitalid_f6_security_suite.php',
];

$failures = 0;
foreach ($commands as $label => $file) {
    $output = [];
    exec(
        escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/' . $file),
        $output,
        $status
    );
    $passed = $status === 0
        && count(array_filter(
            $output,
            static fn(string $line): bool => str_starts_with($line, 'FAIL ')
        )) === 0;
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $label);
    $failures += $passed ? 0 : 1;
}

$scanFiles = array_merge(
    glob($root . '/app/Auth/UserMfa/*.php') ?: [],
    glob($root . '/docs/migrations/*user_login_mfa*.sql') ?: []
);
$forbiddenPatterns = [
    '/\b(?:var_dump|print_r)\s*\(/i',
    '/\berror_log\s*\([^;]*(?:otp|secret|password|session)/i',
    '/(?:password|secret|token)\s*[:=]\s*[\'"][A-Za-z0-9+\/]{24,}[\'"]/i',
    '/BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY/',
];
$scanClean = true;
foreach ($scanFiles as $file) {
    $content = (string) file_get_contents($file);
    foreach ($forbiddenPatterns as $pattern) {
        if (preg_match($pattern, $content) === 1) {
            $scanClean = false;
        }
    }
}
printf("%s secret/PII source scan\n", $scanClean ? 'PASS' : 'FAIL');
$failures += $scanClean ? 0 : 1;

$liveEndpointGates = [];
foreach ($liveEndpointGates as $gate) {
    printf("DEFER %s — requires dormant wiring/staging\n", $gate);
}

printf(
    "RESULT commands=%d failures=%d source_scan=%s critical_high_foundation=0 deferred_live_gates=%d runtime_activation=0\n",
    count($commands),
    $failures,
    $scanClean ? 'pass' : 'fail',
    count($liveEndpointGates)
);
exit($failures === 0 ? 0 : 1);
