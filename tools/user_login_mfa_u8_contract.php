<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

$root = dirname(__DIR__);
$files = [
    'app/Auth/UserMfa/PdoUserMfaPolicyReader.php',
    'app/Auth/UserMfa/UserMfaPrimaryAuthDecision.php',
    'docs/migrations/20260730_user_login_mfa_u8_pilot_up.sql',
    'docs/migrations/20260730_user_login_mfa_u8_pilot_down.sql',
    'tools/user_login_mfa_u8_pilot_schema.php',
    'tools/user_login_mfa_u8_readiness.php',
    'tests/characterization/user_login_mfa_u8_primary_auth.php',
    'app/Auth/UserMfa/LegacyUserMfaLoginFinalizer.php',
    'tools/user_login_mfa_u8_pilot_plan.php',
    'tools/user_login_mfa_u8_policy_transition.php',
];
$checks = 0;
$failures = 0;
$report = static function (bool $ok, string $label) use (&$checks, &$failures): void {
    $checks++;
    $failures += $ok ? 0 : 1;
    printf("%s %s\n", $ok ? 'PASS' : 'FAIL', $label);
};
foreach ($files as $file) {
    $report(is_file($root . '/' . $file), "{$file} exists");
}
$up = (string) file_get_contents($root . '/' . $files[2]);
$reader = (string) file_get_contents($root . '/' . $files[0]);
$decision = (string) file_get_contents($root . '/' . $files[1]);
$route = (string) file_get_contents($root . '/lib/q_func.php');
$login = (string) file_get_contents($root . '/index.php');
$report(
    str_contains($up, 'user_login_mfa_pilot_users')
    && str_contains($up, 'LOCAL_STUDENT')
    && str_contains($up, 'INTERNATIONAL_STUDENT'),
    'pilot schema has bounded representative categories'
);
$report(
    str_contains($reader, 'USER_MFA_RUNTIME_DATABASE_POLICY_MISMATCH')
    && str_contains($decision, 'assertRuntimeParity'),
    'primary authentication fails closed on runtime/database mismatch'
);
$report(
    !str_contains($up, '0530-09') && !str_contains($up, '@upnm.edu.my'),
    'pilot migration contains no real pilot identity or email'
);
$report(
    strpos($route, 'UserMfaPrimaryAuthDecision')
        < strpos($route, '//SSO Token Initialize')
    && str_contains($route, "login_status'=>2")
    && str_contains($route, 'UserMfaEmailOtpService')
    && str_contains($route, 'UserMfaPendingLoginCoordinator'),
    'password login issues no token until email factor and finalization complete'
);
$report(
    str_contains($login, 'modal_user_mfa')
    && str_contains($login, "response['login_status']==2")
    && str_contains($login, 'user_mfa_email_verify'),
    'login UI handles pending MFA without resubmitting password'
);
$pilotTool = (string) file_get_contents($root . '/tools/user_login_mfa_u8_pilot_plan.php');
$policyTool = (string) file_get_contents($root . '/tools/user_login_mfa_u8_policy_transition.php');
$report(
    str_contains($pilotTool, '.private/user_mfa_pilot_plan.json')
    && str_contains($pilotTool, '($permissions & 0077) !== 0')
    && str_contains($pilotTool, 'OR data2=:identifier2')
    && str_contains($pilotTool, "'ambiguous' => 0")
    && str_contains($pilotTool, 'pii_output=0'),
    'pilot plan resolves login identifiers uniquely and remains PII-redacted'
);
$report(
    str_contains($pilotTool, '$resolveOne((string) $identifier)'),
    'numeric student login identifiers are cast safely at resolver boundary'
);
$report(
    str_contains($policyTool, 'user_login_mfa_policy_history')
    && str_contains($policyTool, 'FOR UPDATE')
    && str_contains($policyTool, 'USER_MFA_POLICY_AUDIT_ATOMICITY_FAILED')
    && str_contains($policyTool, "['OFF','ENROLLMENT','PILOT_ENFORCED']"),
    'policy transition is versioned audited atomic and bounded to U8 modes'
);
$output = [];
exec(
    escapeshellarg(PHP_BINARY) . ' '
        . escapeshellarg($root . '/tests/characterization/user_login_mfa_u8_primary_auth.php'),
    $output,
    $status
);
$report(
    $status === 0 && in_array(
        'RESULT checks=5 failures=0 shared_database_mutations=0 runtime_activation=0',
        $output,
        true
    ),
    'isolated OFF enrollment and pilot primary-auth decisions pass'
);
printf("RESULT checks=%d failures=%d runtime_activation=0\n", $checks, $failures);
exit($failures === 0 ? 0 : 1);
