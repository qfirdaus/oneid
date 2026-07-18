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

$report(ONEID_APP_VERSION === '2.5.0', 'central application version is 2.5.0');
$report(
    oneid_application_footer() === '2026 © PTMK | Aplikasi Digital. Version 2.5.0',
    'central copyright and footer text match the approved release'
);

foreach (['index.php', 'page/dashboard.php', 'admin/dashboard.php'] as $page) {
    $contents = (string) file_get_contents($projectRoot . '/' . $page);
    $report(str_contains($contents, 'oneid_application_footer()'), $page . ' reads the shared footer source');
}

$adminDashboard = (string) file_get_contents($projectRoot . '/admin/dashboard.php');
$report(
    str_contains($adminDashboard, 'version: <?php echo json_encode(ONEID_APP_VERSION); ?>')
        && str_contains($adminDashboard, 'kanan bawah pada desktop')
        && str_contains($adminDashboard, 'disabled state')
        && str_contains($adminDashboard, 'kembali ke tengah'),
    'latest admin release card reads shared v2.5.0 metadata and Audit History pagination notes'
);
$expectedHistory = [
    '2.4.4','2.4.3','2.4.2','2.4.1','2.4.0',
    '2.3.4','2.3.3','2.3.2','2.3.1','2.3.0',
    '2.2.4','2.2.3','2.2.2','2.2.1','2.2.0',
    '2.1.4','2.1.3','2.1.2','2.1.1','2.1.0',
    '2.0.4','2.0.3','2.0.2','2.0.1','2.0.0',
];
$previousPosition = -1;
$historyValid = true;
foreach ($expectedHistory as $version) {
    $position = strpos($adminDashboard, 'version: "' . $version . '"');
    if ($position === false || $position <= $previousPosition) {
        $historyValid = false;
        break;
    }
    $previousPosition = $position;
}
$report(
    $historyValid
        && str_contains($adminDashboard, 'Konfigurasi SSO pentadbir diperkukuh')
        && str_contains($adminDashboard, 'WA6 menyediakan reconciliation read-only')
        && str_contains($adminDashboard, 'Controlled Pilot External Sync'),
    'release history preserves normalized v2.4.4 through v2.0.0 in order'
);
$policy = (string) file_get_contents($projectRoot . '/docs/VERSION_NUMBERING_POLICY.md');
$package = json_decode((string) file_get_contents($projectRoot . '/package.json'), true);
$report(($package['version'] ?? '') === ONEID_APP_VERSION, 'package metadata matches the central application version');
$report(
    preg_match('/^\d+\.\d+\.[0-4]$/', ONEID_APP_VERSION) === 1
        && str_contains($policy, 'Selepas `MAJOR.MINOR.4`')
        && str_contains($policy, '`MAJOR.(MINOR+1).0`'),
    'version policy caps patch at 4 and rolls the next release to a new minor series'
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
