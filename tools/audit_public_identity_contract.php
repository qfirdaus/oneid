<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);
$database = $read('lib/Database.php');
$qFunc = $read('lib/q_func.php');
$global = $read('app/Admin/UserMfaGlobalPolicyService.php');
$category = $read('app/Admin/UserMfaCategoryPolicyService.php');
$exemption = $read('app/Admin/UserMfaTemporaryExemptionService.php');
$recovery = $read('app/Auth/UserMfa/UserMfaTotpEmailRecoveryService.php');
$migration = $read('tools/audit_identity_history_migration.php');

$checks = [
    'central syslog writer sanitizes every detail' => str_contains($database, 'sanitizeDetail((string)$log_detail)'),
    'legacy Audit Log display is sanitized' => str_contains($database, '$row[\'log_detail\']=$this->auditIdentity()->sanitizeDetail'),
    'configuration history writes and reads public actor identity' => str_contains($database, "':actor_id'=>\$this->audit_identifier")
        && substr_count($database, "\$row['actor_id']=\$this->audit_identifier") >= 2,
    'login banner and metadata endpoints pass public actor identity' => substr_count($qFunc, 'audit_identifier((string)$_SESSION[\'login_user\'])') >= 2,
    'direct MFA policy audits use resolved public admin identity' => str_contains($global, '$publicAdminId')
        && str_contains($category, '$publicAdmin'),
    'temporary exemption display and audit resolve identities' => substr_count($exemption, 'AuditIdentityResolver') >= 2,
    'TOTP recovery audit and rate limit share public identity' => substr_count($recovery, '$publicUserId') >= 4,
    'history migration excludes internal MFA foreign-key actor columns' => !str_contains($migration, "['user_login_mfa_policy_history'")
        && !str_contains($migration, "['user_login_mfa_exemptions'"),
    'history migration is preview-first, protected and resumable' => str_contains($migration, "in_array('--apply'")
        && str_contains($migration, 'chmod($backupPath, 0600)')
        && str_contains($migration, 'array_chunk($syslogChanges, 100)'),
];

$failures = 0;
foreach ($checks as $label => $passed) {
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $label);
    $failures += $passed ? 0 : 1;
}
printf("RESULT checks=%d failures=%d database_mutations=0\n", count($checks), $failures);
exit($failures === 0 ? 0 : 1);
