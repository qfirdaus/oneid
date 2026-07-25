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
    'app/Documentation/DocumentInventory.php',
    'tools/ml8a_document_inventory.php',
    'tests/characterization/ml8a_document_inventory.php',
];
foreach ($files as $file) {
    $path = $root . '/' . $file;
    exec('php -l ' . escapeshellarg($path), $output, $status);
    $report($status === 0, 'source and lint ' . $file);
}
$inventory = file_get_contents($root . '/app/Documentation/DocumentInventory.php');
$report(
    str_contains($inventory, "'can_apply' => false")
    && str_contains($inventory, "'automatic_translation' => false")
    && str_contains($inventory, "'mutation_statements' => 0"),
    'inventory is hard-coded read-only with no automatic translation'
);
$report(
    str_contains($inventory, 'BM_ONLY_EXPLICIT_FALLBACK_REQUIRED')
    && str_contains($inventory, 'INTERNAL_TECHNICAL_INVARIANT')
    && str_contains($inventory, 'MIXED_TRANSLATION_REQUIRED')
    && str_contains($inventory, 'REVIEW_REQUIRED'),
    'classification and fallback contract is explicit'
);
$report(
    !preg_match('/\\b(?:INSERT|UPDATE|DELETE|REPLACE|CREATE|DROP|ALTER)\\s+/i', $inventory),
    'inventory contains no database or filesystem mutation statement'
);
passthru('php ' . escapeshellarg($root . '/tests/characterization/ml8a_document_inventory.php'), $status);
$report($status === 0, 'ML8A characterization passes');

echo "RESULT checks={$checks} failed={$failed}\n";
exit($failed === 0 ? 0 : 1);
