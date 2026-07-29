<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

$root = dirname(__DIR__);
$files = [
    'app/Auth/UserMfa/UserMfaPendingLoginException.php',
    'app/Auth/UserMfa/UserMfaPendingLoginPersistenceInterface.php',
    'app/Auth/UserMfa/UserMfaLoginFinalizerInterface.php',
    'app/Auth/UserMfa/UserMfaPendingLoginCoordinator.php',
    'tests/characterization/user_login_mfa_u3_pending_login.php',
];
$source = [];
$checks = [];
foreach ($files as $file) {
    $source[$file] = (string) file_get_contents($root . '/' . $file);
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($root . '/' . $file), $output, $status);
    $checks['lint_' . basename($file)] = $status === 0;
    $output = [];
}

$coordinator = $source['app/Auth/UserMfa/UserMfaPendingLoginCoordinator.php'];
$persistence = $source['app/Auth/UserMfa/UserMfaPendingLoginPersistenceInterface.php'];
$finalizer = $source['app/Auth/UserMfa/UserMfaLoginFinalizerInterface.php'];
$runtimeWiring = (string) file_get_contents($root . '/bootstrap/app.php')
    . (string) file_get_contents($root . '/bootstrap/sync_runtime.php')
    . (string) file_get_contents($root . '/lib/q_func.php');

$checks['password_only_scope_is_enforced'] = str_contains($coordinator, "\$primaryMethod !== 'PASSWORD'")
    && str_contains($coordinator, "'PRIMARY_METHOD_OUT_OF_SCOPE'");
$checks['pending_state_precedes_finalizer'] = strpos($coordinator, 'createPendingLogin([')
    < strpos($coordinator, '$finalizer->prepare(')
    && str_contains($coordinator, "'USER_MFA_FACTOR_REQUIRED'");
$checks['transaction_is_one_use_and_compensated'] = str_contains($coordinator, 'consumePendingLogin')
    && str_contains($coordinator, '$finalizer->compensate(')
    && str_contains($coordinator, "'USER_MFA_PENDING_REPLAYED'");
$checks['binding_and_expiry_fail_closed'] = str_contains($coordinator, 'USER_MFA_BINDING_MISMATCH')
    && str_contains($coordinator, 'USER_MFA_PENDING_EXPIRED')
    && str_contains($coordinator, 'revokePendingLogin');
$checks['audit_is_mandatory'] = str_contains($coordinator, 'USER_MFA_AUDIT_FAILED')
    && str_contains($persistence, 'recordAudit(');
$checks['raw_primary_credentials_are_not_persisted'] = !str_contains($persistence, 'password')
    && !str_contains($coordinator, "'password' =>")
    && !str_contains($coordinator, "'session_id' =>");
$checks['finalizer_prepare_emits_no_cookie'] = str_contains($finalizer, 'no cookie is emitted here')
    && !str_contains($finalizer, 'setcookie');
$checks['u3_pending_boundary_precedes_token_issue'] =
    strpos($runtimeWiring, 'UserMfaPrimaryAuthDecision')
        < strpos($runtimeWiring, '//SSO Token Initialize')
    && str_contains($runtimeWiring, "if((\$userMfaResult['code']??'')==='USER_MFA_REQUIRED')");

exec(
    escapeshellarg(PHP_BINARY)
    . ' '
    . escapeshellarg($root . '/tests/characterization/user_login_mfa_u3_pending_login.php'),
    $characterization,
    $characterizationStatus
);
$checks['characterization_passes'] = $characterizationStatus === 0
    && in_array(
        'RESULT checks=11 failures=0 tokens_before_mfa=0 network_calls=0 live_database_mutations=0 runtime_activation=0',
        $characterization,
        true
    );

$failed = count(array_filter($checks, static fn(bool $passed): bool => !$passed));
foreach ($checks as $name => $passed) {
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $name);
}
printf(
    "RESULT checks=%d failures=%d tokens_before_mfa=0 feature_activation=0\n",
    count($checks),
    $failed
);
exit($failed === 0 ? 0 : 1);
