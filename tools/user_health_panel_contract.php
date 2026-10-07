<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$top = (string) file_get_contents($root . '/page/const/top.php');
$page = (string) file_get_contents($root . '/page/dashboard.php');
$css = (string) file_get_contents($root . '/public/dist/css/oneid-user-health.css');
$js = (string) file_get_contents($root . '/public/dist/js/oneid-user-health.js');
$en = require $root . '/config/locales/en.php';
$ms = require $root . '/config/locales/ms.php';

$checks = [
    'traffic-light trigger is beside the user session indicator' => str_contains($top, 'oneid_user_health_trigger') && str_contains($top, 'oneid-status-lights'),
    'versioned panel assets are loaded' => str_contains($page, 'oneid-user-health.css?v=20261007-4') && str_contains($page, 'oneid-user-health.js?v=20261007-3'),
    'diagnostic environment follows runtime configuration' => str_contains($page, "oneid_config('ONEID_ENVIRONMENT', 'unknown')"),
    'desktop panel uses four balanced metric and action columns' => substr_count($css, 'grid-template-columns:repeat(4,minmax(0,1fr))') === 2,
    'mobile panel remains inside viewport with two columns' => str_contains($css, 'left:9px;max-height:calc(100vh - 90px);right:9px') && str_contains($css, '.oneid-health-grid,.oneid-health-actions{grid-template-columns:1fr 1fr}'),
    'all action labels use the same font size' => str_contains($css, '.oneid-health-action{') && str_contains($css, 'font-size:11px!important'),
    'cache action has concise bilingual labels' => ($en['dashboard.health.clear'] ?? '') === 'Clear cache' && ($ms['dashboard.health.clear'] ?? '') === 'Kosongkan cache',
    'panel never clears browser storage broadly' => !str_contains($js, 'localStorage.clear') && !str_contains($js, 'sessionStorage.clear'),
    'panel never reads or writes cookies' => !str_contains($js, 'document.cookie'),
    'diagnostics exclude URL query and identity values' => !str_contains($js, 'location.search') && !str_contains($js, 'location.href') && !str_contains($js, 'login_user'),
];

$failed = 0;
foreach ($checks as $label => $passed) {
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $label);
    if (!$passed) $failed++;
}
printf("RESULT checks=%d failed=%d\n", count($checks), $failed);
exit($failed === 0 ? 0 : 1);
