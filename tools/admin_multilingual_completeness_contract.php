<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$files = [
    'admin/dashboard.php',
    'admin/user_list.php',
    'config/locales/ms.php',
    'config/locales/en.php',
    'tests/characterization/admin_multilingual_completeness.php',
];

$failed = 0;
foreach ($files as $file) {
    $path = $root . '/' . $file;
    $output = [];
    $code = 0;
    exec('php -l ' . escapeshellarg($path), $output, $code);
    $ok = $code === 0;
    echo ($ok ? 'PASS ' : 'FAIL ') . 'source and lint ' . $file . PHP_EOL;
    if (!$ok) {
        $failed++;
    }
}

$output = [];
$code = 0;
exec('php ' . escapeshellarg($root . '/tests/characterization/admin_multilingual_completeness.php'), $output, $code);
echo implode(PHP_EOL, $output) . PHP_EOL;
if ($code !== 0) {
    $failed++;
}

echo 'RESULT failed=' . $failed . PHP_EOL;
exit($failed === 0 ? 0 : 1);
