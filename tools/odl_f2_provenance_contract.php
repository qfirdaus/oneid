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
    str_contains($tool, "'STUDENT_ASIS_ACTIVE'")
        && !str_contains($tool, "'STUDENT_UG'"),
    'unconfirmed source uses neutral proposed code'
);
$report(
    !str_contains($runtime, 'ProvenanceBackfillPreview')
        && !str_contains($runtime, 'STUDENT_ASIS_ACTIVE'),
    'Fasa 2 preview remains outside runtime wiring'
);
$report(
    str_contains($preview, "'plan_digest'")
        && !str_contains($preview, "'identities'")
        && !str_contains($preview, "'u_ids'"),
    'result exposes aggregate digest without identity lists'
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
        && in_array('RESULT checks=20 failed=0', $output, true),
    'characterization fixture passes'
);

printf("RESULT checks=%d failed=%d\n", $checks, $failed);
exit($failed === 0 ? 0 : 1);
