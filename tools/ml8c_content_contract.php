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
foreach ([
    'app/Documentation/Ml8cContentPreview.php',
    'tools/ml8c_content_preview.php',
    'tests/characterization/ml8c_content_preview.php',
] as $file) {
    exec('php -l ' . escapeshellarg($root . '/' . $file), $output, $status);
    $report($status === 0, 'source and lint ' . $file);
}
$source = (string) file_get_contents($root . '/app/Documentation/Ml8cContentPreview.php');
$report(
    str_contains($source, "'can_apply' => false")
    && str_contains($source, "'can_publish_english_manual' => false")
    && str_contains($source, "'automatic_translation_approval' => false")
    && str_contains($source, "'mutation_statements' => 0"),
    'ML8C boundaries are fail-closed'
);
$report(
    !preg_match('/\b(?:INSERT|UPDATE|DELETE|REPLACE|CREATE|DROP|ALTER)\s+/i', $source),
    'ML8C Preview contains no database mutation statement'
);
passthru('php ' . escapeshellarg($root . '/tests/characterization/ml8c_content_preview.php'), $status);
$report($status === 0, 'ML8C characterization passes');

echo "RESULT checks={$checks} failed={$failed}\n";
exit($failed === 0 ? 0 : 1);
