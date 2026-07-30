<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

$root = dirname(__DIR__);
$service = (string) file_get_contents($root . '/app/Admin/UserMfaGlobalPolicyService.php');
$route = (string) file_get_contents($root . '/lib/q_func.php');
$guard = (string) file_get_contents($root . '/lib/request_security.php');
$admin = (string) file_get_contents($root . '/admin/dashboard.php');
$reader = (string) file_get_contents($root . '/app/Auth/UserMfa/PdoUserMfaPolicyReader.php');
$userDashboard = (string) file_get_contents($root . '/page/dashboard.php');
$userSecurity = (string) file_get_contents($root . '/page/user_mfa_security.php');
$docs = (string) file_get_contents(
    $root . '/docs/USER_LOGIN_MFA_ADMIN_CONTROL_AUDIT_DAN_PELAKSANAAN.md'
);
$ms = require $root . '/config/locales/ms.php';
$en = require $root . '/config/locales/en.php';

$checks = 0;
$failures = 0;
$report = static function (bool $ok, string $label) use (&$checks, &$failures): void {
    $checks++;
    $failures += $ok ? 0 : 1;
    printf("%s %s\n", $ok ? 'PASS' : 'FAIL', $label);
};

$report(
    str_contains($service, "'ENABLE USER MFA'")
    && str_contains($service, "'DISABLE USER MFA'")
    && str_contains($service, 'USER_MFA_GLOBAL_POLICY_INPUT_INVALID'),
    'typed confirmation reason reference and validation are server enforced'
);
$report(
    str_contains($guard, "'admin_update_user_mfa_global_policy'")
    && str_contains($guard, "'SECURITY_CONFIGURATION_CHANGE'"),
    'global mutation requires Administrator Security Configuration Step-Up'
);
$report(
    str_contains($service, "transaction_status='REVOKED'")
    && str_contains($service, 'user_login_mfa_challenges')
    && str_contains($service, 'factor_status=\'ACTIVE\'')
    && !str_contains($service, "UPDATE user_mfa_factors"),
    'shutdown revokes pending work while preserving enrolled factors'
);
$report(
    str_contains($service, 'FOR UPDATE')
    && str_contains($service, 'configuration_version=:version')
    && str_contains($service, 'user_login_mfa_policy_history')
    && str_contains($service, 'INSERT INTO syslog')
    && str_contains($service, 'rollBack'),
    'policy update is concurrent safe transactional and audit atomic'
);
$report(
    str_contains($reader, "\$databaseMode !== 'OFF'")
    && str_contains($reader, 'USER_MFA_RUNTIME_DATABASE_POLICY_MISMATCH')
    && str_contains($route, "\$userMfaPolicies->policy()->mode==='OFF'"),
    'database OFF is a safe operational override without permitting active mismatch'
);
$report(
    str_contains($admin, 'id="configuration_user_mfa"')
    && str_contains($admin, 'id="user_mfa_global_enabled"')
    && str_contains($admin, 'saveUserMfaGlobalPolicy')
    && str_contains($admin, 'showCancelButton:true')
    && str_contains($admin, 'oneid_user_mfa_global_enabled')
    && str_contains($admin, 'userMfaGlobalPendingTarget')
    && str_contains($admin, 'setTimeout(saveUserMfaGlobalPolicy,0)')
    && str_contains($admin, 'userMfaGlobalResumeAfterStepUp')
    && str_contains($admin, 'persistUserMfaGlobalPolicy')
    && str_contains($admin, 'change_reference'),
    'Administrator UI provides bilingual SweetAlert global control'
);
$report(
    str_contains($userDashboard, "\$userMfaEffectiveMode !== 'OFF'")
    && str_contains($userSecurity, "\$databaseMode === 'OFF'"),
    'Account Security UI is hidden when effective database policy is OFF'
);
$report(
    isset($ms['admin.configuration.user_mfa_title'], $en['admin.configuration.user_mfa_title'])
    && array_diff(
        array_keys(array_filter($ms, static fn ($v, $k) => str_starts_with($k, 'admin.configuration.user_mfa'), ARRAY_FILTER_USE_BOTH)),
        array_keys($en)
    ) === [],
    'BM and English User MFA administration labels are complete'
);
$report(
    str_contains($docs, 'Prioriti 1')
    && str_contains($docs, 'Prioriti 6')
    && str_contains($docs, 'MENUNGGU KELULUSAN OWNER')
    && str_contains($docs, 'pembangunan berhenti'),
    'implementation document enforces Priority 1 to 6 approval gates'
);

printf("RESULT checks=%d failures=%d database_mutations=0\n", $checks, $failures);
exit($failures === 0 ? 0 : 1);
