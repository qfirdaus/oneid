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
    'page/admin_step_up.php',
    'app/Auth/AdminStepUpPhpMailerSender.php',
    'config/locales/ms.php',
    'config/locales/en.php',
    'tests/characterization/multilingual_admin_step_up.php',
] as $relative) {
    exec('php -l ' . escapeshellarg($root . '/' . $relative) . ' 2>&1', $output, $status);
    $report($status === 0, "source and lint {$relative}");
}

foreach ([
    'tests/characterization/multilingual_admin_step_up.php',
    'tools/f7_3_totp_contract.php',
    'tools/f7_3_totp_service_contract.php',
    'tools/f7_4_authorization_persistence_contract.php',
    'tools/f7_5_grant_loader_contract.php',
] as $contract) {
    passthru('php ' . escapeshellarg($root . '/' . $contract), $status);
    $report($status === 0, basename($contract) . ' passes');
}

echo "RESULT checks={$checks} failed={$failed}" . PHP_EOL;
exit($failed === 0 ? 0 : 1);
