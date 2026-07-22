<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Auth/AdminStepUpRateLimitConfig.php';

use OneId\App\Auth\AdminStepUpRateLimitConfig;

$failed = 0;
$report = static function (bool $ok, string $label) use (&$failed): void {
    echo ($ok ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
    if (!$ok) {
        $failed++;
    }
};

$config = new AdminStepUpRateLimitConfig(10, 30, 10, 50);
$report(!$config->exceeded(['admin_hour' => 9, 'admin_day' => 29, 'session_hour' => 9, 'ip_hour' => 49]), 'values below limits are accepted');
$report($config->exceeded(['admin_hour' => 10]), 'admin hourly limit blocks');
$report($config->exceeded(['admin_day' => 30]), 'admin daily limit blocks');
$report($config->exceeded(['session_hour' => 10]), 'session hourly limit blocks');
$report($config->exceeded(['ip_hour' => 50]), 'IP hourly limit blocks');

try {
    new AdminStepUpRateLimitConfig(0, 30, 10, 50);
    $report(false, 'invalid configuration fails closed');
} catch (\InvalidArgumentException $exception) {
    $report($exception->getMessage() === 'STEP_UP_RATE_LIMIT_CONFIGURATION_INVALID', 'invalid configuration fails closed');
}

echo sprintf('RESULT checks=%d failed=%d', 6, $failed) . PHP_EOL;
exit($failed === 0 ? 0 : 1);
