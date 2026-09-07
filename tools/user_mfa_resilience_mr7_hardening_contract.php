<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$reader = (string) file_get_contents($root . '/app/Auth/UserMfa/PdoUserMfaPolicyReader.php');
$lifecycle = (string) file_get_contents($root . '/app/Admin/UserMfaLifecycleService.php');
$api = (string) file_get_contents($root . '/lib/q_func.php');
$ui = (string) file_get_contents($root . '/public/assetsM/js/user-mfa-admin-policy.js');
$readiness = (string) file_get_contents($root . '/tools/user_mfa_resilience_mr7_readiness.php');
$runbook = (string) file_get_contents($root . '/docs/USER_MFA_RESILIENCE_MR7_RELEASE_RUNBOOK.md');
$checks = [
    'bypass_reader_exact_environment' => str_contains($reader, 'WHERE environment=:environment') && !str_contains($reader, "environment IN ('local','staging','production')"),
    'unknown_environment_fails_closed' => str_contains($reader, "\$this->environment = in_array") && str_contains($reader, "resolve(\$storedMode, null"),
    'manual_restore_exact_environment' => str_contains($lifecycle, 'request_id=:id AND environment=:environment') && str_contains($lifecycle, 'USER_MFA_RESTORE_ENVIRONMENT_INVALID'),
    'manual_restore_stepup_resume' => str_contains($ui, 'oneid_user_mfa_restore_workflow') && str_contains($ui, 'persistRestore(rd)'),
    'api_supplies_runtime_environment' => str_contains($api, "UserMfaLifecycleService(\$pdo,oneid_admin_email_notification_callback(\$pdo),strtolower"),
    'readiness_is_read_only' => str_contains($readiness, 'mutation=0') && !preg_match('/\b(?:INSERT|UPDATE|DELETE|REPLACE|TRUNCATE)\b/i', $readiness),
    'release_and_activation_are_separate' => str_contains($readiness, "['--release', '--activation']") && str_contains($readiness, "lifecycle_worker_enabled"),
    'runbook_has_backup_rollback_and_stop_gates' => str_contains($runbook, 'Backup wajib') && str_contains($runbook, 'Rollback') && str_contains($runbook, 'Stop conditions'),
];
$failed = 0;
foreach ($checks as $name => $passed) {
    echo ($passed ? 'PASS ' : 'FAIL ') . $name . PHP_EOL;
    if (!$passed) $failed++;
}
printf("RESULT checks=%d failed=%d\n", count($checks), $failed);
exit($failed === 0 ? 0 : 1);
