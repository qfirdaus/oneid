<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/bootstrap/app.php';
require_once $projectRoot . '/lib/device_info.php';

$checks = 0;
$failed = 0;

$report = static function (bool $passed, string $label) use (&$checks, &$failed): void {
    $checks++;
    if (!$passed) $failed++;
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $label);
};

$report(ONEID_APP_VERSION === '2.0.7', 'central application version is 2.0.7');
$report(
    oneid_application_footer() === '2026 © PTMK | Aplikasi Digital. Version 2.0.7',
    'central copyright and footer text match the approved release'
);

foreach (['index.php', 'page/dashboard.php', 'admin/dashboard.php'] as $page) {
    $contents = (string) file_get_contents($projectRoot . '/' . $page);
    $report(str_contains($contents, 'oneid_application_footer()'), $page . ' reads the shared footer source');
}

$adminDashboard = (string) file_get_contents($projectRoot . '/admin/dashboard.php');
$report(
    str_contains($adminDashboard, 'version: <?php echo json_encode(ONEID_APP_VERSION); ?>')
        && str_contains($adminDashboard, 'Konfigurasi SSO pentadbir diperkukuh')
        && str_contains($adminDashboard, 'session-lock release')
        && str_contains($adminDashboard, 'WA6 menyediakan reconciliation read-only'),
    'latest admin release card reads shared v2.0.7 metadata and notes'
);
$release206Position = strpos($adminDashboard, 'version: "2.0.6"');
$release205Position = strpos($adminDashboard, 'version: "2.0.5"');
$report(
    $release206Position !== false
        && $release205Position !== false
        && $release206Position < $release205Position
        && str_contains($adminDashboard, 'Controlled Pilot External Sync'),
    'release history preserves v2.0.6 between v2.0.7 and v2.0.5'
);
$report(
    str_contains($adminDashboard, 'version-release-toggle')
        && str_contains($adminDashboard, "releaseList.querySelectorAll('.version-release-card')")
        && str_contains($adminDashboard, 'selectedContent.hidden = false'),
    'release history uses exclusive show-hide accordion behavior'
);

$report(
    oneid_format_device_info('desktop', '', '', 'Firefox', 'Windows') === 'Desktop · Firefox · Windows',
    'desktop label omits empty hardware parentheses'
);
$report(
    oneid_format_device_info('smartphone', 'Apple', 'iPhone', 'Mobile Safari', 'iOS')
        === 'Smartphone (Apple iPhone) · Mobile Safari · iOS',
    'mobile label includes available brand and model'
);
$report(oneid_normalize_device_info('desktop ()') === 'Desktop', 'legacy empty brand parentheses are normalized for display');
$report(oneid_normalize_device_info('') === 'Unknown device', 'empty legacy device information has a safe label');

printf("RESULT checks=%d failed=%d\n", $checks, $failed);
exit($failed === 0 ? 0 : 1);
