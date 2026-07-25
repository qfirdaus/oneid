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
    'app/Documentation/SharedFaqContent.php',
    'lib/shared_faq.php',
    'tests/characterization/ml8b_shared_faq.php',
    'index.php',
    'page/dashboard.php',
] as $file) {
    exec('php -l ' . escapeshellarg($root . '/' . $file), $output, $status);
    $report($status === 0, 'source and lint ' . $file);
}

passthru('php ' . escapeshellarg($root . '/tests/characterization/ml8b_shared_faq.php'), $status);
$report($status === 0, 'ML8B shared FAQ characterization passes');

echo "RESULT checks={$checks} failed={$failed}\n";
exit($failed === 0 ? 0 : 1);
