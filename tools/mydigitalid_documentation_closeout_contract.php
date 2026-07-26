<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

$root = dirname(__DIR__);
$phaseDocs = [
    'docs/MYDIGITALID_F0_PREFLIGHT_DAN_BASELINE.md',
    'docs/MYDIGITALID_F1_DORMANT_OIDC_FOUNDATION.md',
    'docs/MYDIGITALID_F2_DORMANT_IDENTITY_AUDIT_SCHEMA.md',
    'docs/MYDIGITALID_F3_DORMANT_CALLBACK_FOUNDATION.md',
    'docs/MYDIGITALID_F4_DORMANT_ACCOUNT_LINKING_AUDIT.md',
    'docs/MYDIGITALID_F4B_DORMANT_CALLBACK_SESSION.md',
    'docs/MYDIGITALID_F5_FLAGGED_UI_ERRORS_LOGOUT.md',
    'docs/MYDIGITALID_F6_AUTOMATED_SECURITY_REGRESSION.md',
    'docs/MYDIGITALID_F7_REJECTION_UX_ACCOUNT_SWITCH_LOG_HARDENING.md',
];
$read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);
$audit = $read('docs/MYDIGITALID_SSO_AUDIT_DAN_PELAN_PELAKSANAAN.md');
$closeout = $read('docs/MYDIGITALID_STAGING_IMPLEMENTATION_CLOSEOUT.md');
$checks = [];

$checks['phase_docs_reference_canonical_closeout'] = count(array_filter(
    $phaseDocs,
    static fn(string $path): bool => is_file($root . '/' . $path)
        && str_contains($read($path), 'MYDIGITALID_STAGING_IMPLEMENTATION_CLOSEOUT.md')
)) === count($phaseDocs);
$checks['audit_records_staging_activation'] = str_contains(
    $audit,
    'STAGING IMPLEMENTED/ACTIVE'
) && str_contains($audit, '## 28. Rekod Pelaksanaan Staging dan Status Akhir Audit');
$checks['closeout_has_migration_evidence'] = str_contains($closeout, 'checks=10 blocked=0')
    && str_contains($closeout, 'user_rows=9793')
    && str_contains($closeout, 'user_structure_unchanged=yes');
$checks['closeout_has_security_evidence'] = str_contains($closeout, 'commands=23')
    && str_contains($closeout, 'local_mutations=0')
    && str_contains(strtolower($closeout), 'query-redaction')
    && str_contains(strtolower($closeout), 'canary');
$checks['pending_acceptance_is_explicit'] = str_contains(
    $closeout,
    '## 9. Acceptance yang masih belum selesai'
) && str_contains($closeout, 'PENDING browser acceptance');
$checks['production_remains_no_go'] = str_contains(
    $closeout,
    '## 10. Production gates — semuanya masih tertutup'
) && str_contains($audit, 'PRODUCTION: NO-GO');
$checks['secret_reference_closeout_exists'] = str_contains(
    $closeout,
    '## 11. Reference-folder dan secret close-out'
);
$allDocs = $audit . $closeout;
foreach ($phaseDocs as $path) {
    $allDocs .= $read($path);
}
$checks['no_stale_suite_count'] = !str_contains($allDocs, 'commands=20')
    && !str_contains($allDocs, 'menjalankan 20 command');

$failed = count(array_filter($checks, static fn(bool $passed): bool => !$passed));
foreach ($checks as $name => $passed) {
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $name);
}
printf("RESULT checks=%d failures=%d documentation_mutations=0\n", count($checks), $failed);
exit($failed === 0 ? 0 : 1);
