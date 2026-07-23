<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(2);

$root = dirname(__DIR__);
$files = [
    'app/Sync/DTO/SourceSnapshot.php',
    'app/Sync/DTO/SourceAwarePlan.php',
    'app/Sync/SourceAware/SourceAwareSafetyPolicy.php',
    'app/Sync/SourceAware/SourceAwareStudentPlanner.php',
    'tests/characterization/odl_f5_source_aware_planner.php',
];
$all = '';
$checks = 0; $failed = 0;
$report = static function (bool $ok, string $label) use (&$checks, &$failed): void {
    $checks++; $failed += $ok ? 0 : 1;
    printf("%s %s\n", $ok ? 'PASS' : 'FAIL', $label);
};
foreach ($files as $file) {
    $path = $root . '/' . $file;
    $source = is_file($path) ? (string) file_get_contents($path) : '';
    $all .= "\n" . $source;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path), $out, $code);
    $report($source !== '' && $code === 0, 'source and lint: ' . $file);
    $out = []; $code = 1;
}
$report(!preg_match('/\b(?:PDO|mysqli|odbc_|file_put_contents|curl_)\b/', $all), 'planner boundary is pure with no I/O');
$report(str_contains($all, "'can_apply' => false") && str_contains($all, "'mutation_statements' => 0"), 'preview hard-coded zero mutation');
$report(
    str_contains($all, "? 'ODL'")
        && str_contains($all, "'_EMPTY_SOURCE'")
        && str_contains($all, "'_SOURCE_SHRINK_EXCEEDED'")
        && str_contains($all, "'_INVALID_IDENTITY_THRESHOLD_EXCEEDED'"),
    'ODL safety code construction covered'
);
$report(str_contains($all, 'KEEP_ACCOUNT_ACTIVE_OTHER_SOURCE') && str_contains($all, 'CANDIDATE_DEACTIVATE'), 'all-student-sources activation rule covered');
$runtime = (string) file_get_contents($root . '/bootstrap/sync_runtime.php');
$legacy = (string) file_get_contents($root . '/lib/sync_user_runner.php');
$report(str_contains($runtime, 'SourceAwareStudentPlanner.php') && !str_contains($legacy, 'SourceAwareStudentPlanner'), 'definitions dormant outside legacy runner');
$output = []; $code = 1;
exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/tests/characterization/odl_f5_source_aware_planner.php') . ' 2>&1', $output, $code);
$report($code === 0 && in_array('RESULT checks=14 failed=0', $output, true), 'multi-source characterization passes');
printf("RESULT checks=%d failed=%d\n", $checks, $failed);
exit($failed === 0 ? 0 : 1);
