<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$files = [
    'app/Documentation/DocumentInventory.php',
    'tests/characterization/multilanguage_pre_ml9_reconciliation.php',
];

$failed = 0;
foreach ($files as $file) {
    $output = [];
    $code = 0;
    exec('php -l ' . escapeshellarg($root . '/' . $file), $output, $code);
    echo ($code === 0 ? 'PASS ' : 'FAIL ') . 'source and lint ' . $file . PHP_EOL;
    if ($code !== 0) {
        $failed++;
    }
}

$output = [];
$code = 0;
exec(
    'php ' . escapeshellarg($root . '/tests/characterization/multilanguage_pre_ml9_reconciliation.php'),
    $output,
    $code
);
echo implode(PHP_EOL, $output) . PHP_EOL;
if ($code !== 0) {
    $failed++;
}

echo 'RESULT failed=' . $failed . PHP_EOL;
exit($failed === 0 ? 0 : 1);
