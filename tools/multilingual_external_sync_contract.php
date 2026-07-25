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
    'admin/dashboard.php',
    'config/locales/ms.php',
    'config/locales/en.php',
    'tests/characterization/multilingual_external_sync.php',
] as $relative) {
    exec('php -l ' . escapeshellarg($root . '/' . $relative) . ' 2>&1', $output, $status);
    $report($status === 0, "source and lint {$relative}");
}

passthru(
    'php ' . escapeshellarg($root . '/tests/characterization/multilingual_external_sync.php'),
    $status
);
$report($status === 0, 'External Sync multilingual characterization passes');

foreach ([
    'tools/source_scoped_sync_apply_contract.php',
    'tools/odl_f9_manual_operational_contract.php',
    'tools/odl_f6_shadow_contract.php',
] as $contract) {
    passthru('php ' . escapeshellarg($root . '/' . $contract), $status);
    $report($status === 0, basename($contract) . ' regression passes');
}

echo "RESULT checks={$checks} failed={$failed}" . PHP_EOL;
exit($failed === 0 ? 0 : 1);
