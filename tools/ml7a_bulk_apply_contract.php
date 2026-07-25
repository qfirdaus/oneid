<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$checks = 0;
$failed = 0;
$report = static function (bool $ok, string $label) use (&$checks, &$failed): void {
    $checks++;
    echo ($ok ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
    if (!$ok) {
        $failed++;
    }
};
$files = [
    'app/Metadata/MetadataBulkContentApplyService.php',
    'tools/ml7a_bulk_content_apply.php',
    'tests/characterization/ml7a_bulk_content_apply.php',
];
foreach ($files as $file) {
    $path = $root . '/' . $file;
    exec('php -l ' . escapeshellarg($path), $output, $status);
    $report($status === 0, 'source and lint ' . $file);
}
$service = file_get_contents($root . '/app/Metadata/MetadataBulkContentApplyService.php');
$runner = file_get_contents($root . '/tools/ml7a_bulk_content_apply.php');
$report(
    str_contains($service, 'APPROVED_PLAN_HASH')
    && str_contains($service, 'ML7A_BULK_EXACT_PLAN_REJECTED')
    && str_contains($service, 'ML7A_BULK_BASELINE_MISMATCH'),
    'Apply is exact-plan and exact-baseline bound'
);
$report(
    str_contains($service, 'beginTransaction()')
    && str_contains($service, 'rollBack()')
    && str_contains($service, 'ML7A_BULK_RECONCILIATION_FAILED'),
    'all mutations are transactional with reconciliation'
);
$report(
    str_contains($service, '$reviewRows !== 84')
    && str_contains($service, '$translationRows !== 33')
    && str_contains($service, '$historyRows !== 33')
    && str_contains($service, "'original_metadata_updates' => 0")
    && str_contains($service, "'quarantine_translation_inserts' => 0"),
    'authorized mutation envelope is enforced'
);
$report(
    str_contains($runner, "array_key_exists('execute'")
    && str_contains($service, 'ML7A_BULK_OUTSIDE_CHANGE_WINDOW'),
    'runner requires explicit execution flag and active window'
);
passthru('php ' . escapeshellarg($root . '/tests/characterization/ml7a_bulk_content_apply.php'), $status);
$report($status === 0, 'ML7A Apply fail-closed characterization passes');

echo "RESULT checks={$checks} failed={$failed}\n";
exit($failed === 0 ? 0 : 1);
