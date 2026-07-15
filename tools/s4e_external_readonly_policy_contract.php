<?php

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(2);
}

$root = dirname(__DIR__);
$policy = (string) file_get_contents($root . '/lib/readonly_odbc.php');
$callers = [
    'lib/external_data_source_API.php',
    'lib/skp_api.php',
    'tools/s4d_external_readonly_evidence.php',
];
$checks = 0;
$failed = 0;
$report = static function (bool $ok, string $label) use (&$checks, &$failed): void {
    $checks++;
    if (!$ok) $failed++;
    printf("%s %s\n", $ok ? 'PASS' : 'FAIL', $label);
};

$report(str_contains($policy, "preg_match('/\\ASELECT\\b/i'") && str_contains($policy, "'INSERT', 'UPDATE', 'DELETE'"), 'policy requires SELECT and explicitly denies DML');
$report(str_contains($policy, "'CREATE', 'ALTER', 'DROP'") && str_contains($policy, "'GRANT', 'REVOKE', 'DENY'"), 'policy explicitly denies DDL and privilege changes');
$report(str_contains($policy, "str_contains(\$statement, ';')") && str_contains($policy, "str_contains(\$statement, '--')"), 'policy rejects multi-statement and comments');

foreach ($callers as $file) {
    $source = (string) file_get_contents($root . '/' . $file);
    $report(str_contains($source, "readonly_odbc.php"), $file . ' loads read-only policy');
    $withoutWrapperNames = str_replace(
        ['oneid_readonly_odbc_exec', 'oneid_readonly_odbc_prepare'],
        ['', ''],
        $source
    );
    $report(!str_contains($withoutWrapperNames, 'odbc_exec(') && !str_contains($withoutWrapperNames, 'odbc_prepare('), $file . ' has no direct ODBC execution or prepare');
}

$output = [];
$code = 1;
exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/tests/characterization/s4e_external_readonly_sql_policy.php') . ' 2>&1', $output, $code);
$report($code === 0 && in_array('RESULT checks=20 failed=0', $output, true), 'pure rejection fixture passes');

printf("RESULT checks=%d failed=%d\n", $checks, $failed);
exit($failed === 0 ? 0 : 1);

