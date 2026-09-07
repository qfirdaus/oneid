<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

$root = dirname(__DIR__);
$up = (string) file_get_contents($root . '/docs/migrations/20260907_user_mfa_resilience_mr2_up.sql');
$down = (string) file_get_contents($root . '/docs/migrations/20260907_user_mfa_resilience_mr2_down.sql');
$spec = (string) file_get_contents($root . '/docs/USER_MFA_RESILIENCE_MR1_SPECIFICATION.md');
$schema = (string) file_get_contents($root . '/docs/USER_MFA_RESILIENCE_MR2_SCHEMA.md');

$checks = [
    'existing User MFA mode adds emergency bypass without table replacement' =>
        str_contains($up, "'EMERGENCY_BYPASS'")
        && str_contains($up, 'ALTER TABLE user_login_mfa_policy')
        && !str_contains($up, 'DROP TABLE user_login_mfa_policy'),
    'maintenance MFA policy is separate and defaults enforced' =>
        str_contains($up, 'CREATE TABLE maintenance_mfa_policy')
        && str_contains($up, "VALUES (1,'ENFORCED',1,1"),
    'maintenance e-mail factor cannot be disabled in schema' =>
        str_contains($up, 'CHECK (email_enabled=1)'),
    'request lifecycle has one open request per environment' =>
        str_contains($up, 'open_environment_slot')
        && str_contains($up, 'uq_user_mfa_change_open_environment'),
    'emergency bypass requires restore and bounded eight-hour expiry' =>
        str_contains($up, "requested_mode='EMERGENCY_BYPASS'")
        && str_contains($up, 'expires_at<=starts_at+INTERVAL 8 HOUR'),
    'grace transition is bounded to five minutes' =>
        str_contains($up, "transition_strategy='GRACE'")
        && str_contains($up, 'grace_until<=starts_at+INTERVAL 5 MINUTE'),
    'production self approval is rejected' =>
        str_contains($up, "environment<>'production' OR approved_by IS NULL OR requested_by<>approved_by"),
    'approval binds request digest and policy version' =>
        str_contains($up, 'request_payload_digest')
        && str_contains($up, 'expected_policy_version'),
    'worker attempts are bounded and uniquely tracked' =>
        str_contains($up, 'CREATE TABLE user_mfa_policy_transition_runs')
        && str_contains($up, 'uq_user_mfa_transition_attempt')
        && str_contains($up, 'attempt_number BETWEEN 1 AND 100'),
    'rollback removes children before parents' =>
        strpos($down, 'user_mfa_policy_transition_runs') < strpos($down, 'user_mfa_policy_change_requests')
        && strpos($down, 'user_mfa_policy_change_approvals') < strpos($down, 'user_mfa_policy_change_requests'),
    'rollback restores original four-mode constraint' =>
        str_contains($down, "policy_mode IN ('OFF','ENROLLMENT','PILOT_ENFORCED','ENFORCED')")
        && !str_contains(substr($down, (int) strpos($down, 'ALTER TABLE')), "'EMERGENCY_BYPASS'"),
    'schema document preserves dormant boundary and migration evidence' =>
        str_contains($schema, 'DORMANT / POST-CHECK PASS')
        && str_contains($schema, 'ONEID-MR2-STAGING-20260907-01')
        && str_contains($schema, 'Tiada trigger, event atau scheduled job dicipta.'),
    'phase one requires independent maintenance MFA and step-up' =>
        str_contains($spec, '**Maintenance MFA**')
        && str_contains($spec, '**Admin Step-Up**'),
];

$failed = 0;
foreach ($checks as $label => $passed) {
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $label);
    $failed += $passed ? 0 : 1;
}

printf("RESULT checks=%d failed=%d\n", count($checks), $failed);
exit($failed === 0 ? 0 : 1);
