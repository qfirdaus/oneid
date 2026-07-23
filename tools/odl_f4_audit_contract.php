<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

$root = dirname(__DIR__);
$audit = file_get_contents($root . '/app/Sync/Odl/OdlDataQualityAudit.php');
$runner = file_get_contents($root . '/tools/odl_f4_data_quality_audit.php');
$adapter = file_get_contents($root . '/app/Sync/Odl/OdlStudentSource.php');
$test = file_get_contents(
    $root . '/tests/characterization/odl_f4_data_quality_audit.php'
);
if ($audit === false || $runner === false || $adapter === false || $test === false) {
    fwrite(STDERR, "FAIL ODL_F4_CONTRACT_FILE_MISSING\n");
    exit(1);
}

$checks = 0;
$failed = 0;
$report = static function (bool $ok, string $label) use (&$checks, &$failed): void {
    $checks++;
    $failed += $ok ? 0 : 1;
    printf("%s %s\n", $ok ? 'PASS' : 'FAIL', $label);
};

$report(
    str_contains($adapter, 'WHERE status_code IN (2, 4, 5)')
        && str_contains($adapter, 'external_status_code'),
    'adapter enforces approved active status scope'
);
$report(
    !preg_match(
        '/(?:->exec|->prepare|->query)\s*\(\s*[\'"]\s*'
        . '(?:INSERT|UPDATE|DELETE|REPLACE|ALTER|CREATE|DROP|TRUNCATE)\b/i',
        $runner
    ),
    'audit runner contains no mutation SQL'
);
$report(
    str_contains($runner, 'SELECT u_id,data2,avail_status,account_source,sync_protected')
        && str_contains($runner, 'SELECT u_id,source_code,external_user_id'),
    'OneID comparison queries are fixed SELECT only'
);
$report(
    str_contains($audit, "'can_apply' => false")
        && str_contains($audit, "'mutation_statements' => 0"),
    'audit result is explicitly non-applicable'
);
$report(
    str_contains($audit, "'snapshot_digest'")
        && !str_contains($runner, 'data1]')
        && !str_contains($runner, 'data2]'),
    'runner emits aggregate result without raw row fields'
);
$report(
    str_contains($audit, "'protected_collisions'")
        && str_contains($audit, "'ug_membership_overlap'")
        && str_contains($audit, "'membership_conflicts'"),
    'cross-source and protected collision classes covered'
);
$report(
    str_contains($audit, "'overlength_fields'")
        && str_contains($audit, "'invalid_utf8_rows'")
        && str_contains($audit, "'invalid_email_rows'"),
    'length encoding and email quality covered'
);

$output = [];
$exitCode = 1;
exec(
    escapeshellarg(PHP_BINARY) . ' '
        . escapeshellarg(
            $root . '/tests/characterization/odl_f4_data_quality_audit.php'
        )
        . ' 2>&1',
    $output,
    $exitCode
);
$resultLine = '';
foreach ($output as $line) {
    if (str_starts_with($line, 'RESULT ')) {
        $resultLine = $line;
    }
}
$report(
    $exitCode === 0 && $resultLine === 'RESULT checks=12 failed=0',
    'Fasa 4 characterization passes'
);

printf("RESULT checks=%d failed=%d\n", $checks, $failed);
exit($failed === 0 ? 0 : 1);
