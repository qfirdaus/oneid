<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

$root = dirname(__DIR__);
$preview = (string) file_get_contents(
    $root . '/app/Sync/Provenance/ProvenanceBackfillPreview.php'
);
$tool = (string) file_get_contents(
    $root . '/tools/odl_f2_provenance_backfill_preview.php'
);
$writer = (string) file_get_contents(
    $root . '/tools/odl_f2_provenance_backfill.php'
);
$runtime = '';
foreach ([
    'lib/q_func.php',
    'lib/external_data_source_API.php',
    'admin/dashboard.php',
    'bootstrap/sync_runtime.php',
    'app/Sync/SyncEngineFactory.php',
] as $file) {
    $runtime .= (string) file_get_contents($root . '/' . $file);
}

$checks = 0;
$failed = 0;
$report = static function (bool $ok, string $label) use (&$checks, &$failed): void {
    $checks++;
    if (!$ok) {
        $failed++;
    }
    printf("%s %s\n", $ok ? 'PASS' : 'FAIL', $label);
};

$report(
    str_contains($preview, "'mode' => 'preview'")
        && str_contains($preview, "'can_apply' => false"),
    'preview is permanently non-applying'
);
$report(
    !str_contains($preview, 'PDO')
        && !str_contains($preview, 'odbc_')
        && !str_contains($preview, 'file_get_contents')
        && !str_contains($preview, 'file_put_contents'),
    'planner is pure and performs no I/O'
);
$report(
    str_contains($tool, 'oneid_readonly_odbc_exec')
        && substr_count($tool, '$pdo->query(') === 2
        && !str_contains($tool, '$pdo->exec(')
        && !str_contains($tool, '$pdo->prepare('),
    'CLI performs external and internal SELECT only'
);
$report(
    !preg_match(
        '/\b(?:INSERT|UPDATE|DELETE|REPLACE|ALTER|CREATE|DROP|TRUNCATE)\b\s+/i',
        $tool
    ),
    'CLI contains no mutation statement'
);
$report(
    str_contains($tool, "'STUDENT_UG'")
        && !str_contains($tool, "'STUDENT_ASIS_ACTIVE'"),
    'owner-confirmed undergraduate source code is fixed'
);
$report(
    !str_contains($runtime, 'ProvenanceBackfillPreview')
        && !str_contains($runtime, 'STUDENT_UG'),
    'Fasa 2 preview remains outside runtime wiring'
);
$report(
    str_contains($preview, "'plan_digest'")
        && !str_contains($preview, "'identities'")
        && !str_contains($preview, "'u_ids'"),
    'result exposes aggregate digest without identity lists'
);
$report(
    str_contains($writer, 'ODL_F2_EXPECTED_SOURCE_ROWS = 5452')
        && str_contains($writer, 'ODL_F2_EXPECTED_CANDIDATES = 5423')
        && str_contains($writer, 'ODL_F2_EXPECTED_REVIEW_FINDINGS = 29')
        && str_contains($writer, 'ODL_F2_EXPECTED_DIGEST'),
    'writer binds exact approved preview counts and digest'
);
$report(
    str_contains($writer, 'GET_LOCK(:lock_name, 0)')
        && str_contains($writer, 'beginTransaction()')
        && str_contains($writer, 'rollBack()')
        && str_contains($writer, 'ODL_F2_BACKFILL_RECONCILIATION_FAILED'),
    'writer is lock transaction and reconciliation bound'
);
$report(
    !preg_match(
        '/(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+user_tbl\b/i',
        $writer
    )
        && str_contains($writer, 'user_mutations=0'),
    'writer cannot mutate user_tbl'
);
$report(
    str_contains($writer, "lifecycle_state='dormant'")
        && str_contains($writer, 'ODL_F2_ODL_SOURCE_NOT_DORMANT')
        && str_contains($writer, 'ODL_F2_CHANGE_ID_REQUIRED'),
    'writer preserves dormant lifecycle and requires change ID'
);

$output = [];
$exitCode = 1;
exec(
    escapeshellarg(PHP_BINARY) . ' '
        . escapeshellarg(
            $root . '/tests/characterization/odl_f2_provenance_backfill_preview.php'
        )
        . ' 2>&1',
    $output,
    $exitCode
);
$report(
    $exitCode === 0
        && in_array('RESULT checks=22 failed=0', $output, true),
    'characterization fixture passes'
);

printf("RESULT checks=%d failed=%d\n", $checks, $failed);
exit($failed === 0 ? 0 : 1);
