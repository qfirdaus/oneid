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
    'coloured high-gauge trigger is beside the user session indicator' => str_contains($top, 'oneid_user_health_trigger') && str_contains($top, 'fa-gauge-high') && str_contains($top, 'oneid-gauge-icon__needle'),
    'versioned panel assets are loaded' => str_contains($page, 'oneid-user-health.css?v=20261008-4') && str_contains($page, 'oneid-user-health.js?v=20261008-4'),
    'gauge state follows response and connectivity' => str_contains($js, "gaugeState = !online") && str_contains($css, '.oneid-gauge-icon.is-fast') && str_contains($css, '.oneid-gauge-icon.is-offline'),
    'diagnostic environment follows runtime configuration' => str_contains($page, "oneid_config('ONEID_ENVIRONMENT', 'unknown')"),
    'desktop panel uses four balanced metric and action columns' => substr_count($css, 'grid-template-columns:repeat(4,minmax(0,1fr))') === 2,
    'mobile panel remains inside viewport with two columns' => str_contains($css, 'left:9px;max-height:calc(100vh - 90px);right:9px') && str_contains($css, '.oneid-health-grid,.oneid-health-actions{grid-template-columns:1fr 1fr}'),
    'all action labels use the same font size' => str_contains($css, '.oneid-health-action{') && str_contains($css, 'font-size:11px!important'),
    'cache action has concise bilingual labels' => ($en['dashboard.health.clear'] ?? '') === 'Clear cache' && ($ms['dashboard.health.clear'] ?? '') === 'Kosongkan cache',
    'actions show progress success timestamp and recoverable errors' => str_contains($js, "setStatus(text.refreshing, 'working', false)") && str_contains($js, "formatMessage(message, updatedAt)") && str_contains($js, "setStatus(successful ? formatMessage(message, updatedAt) : text.failed") && str_contains($js, 'data-health-retry'),
    'clear cache explains its narrow display-only scope' => str_contains($en['dashboard.health.cleared'] ?? '', 'Temporary application and session display data') && str_contains($ms['dashboard.health.cleared'] ?? '', 'Data paparan sementara aplikasi dan sesi'),
    'network failure is red and retry is available' => str_contains($css, '.oneid-health-status.is-error{color:#b53042}') && str_contains($js, "retry.addEventListener('click'") && str_contains($js, "'fa fa-refresh fa-spin'"),
    'safe diagnostics can be viewed without copying first' => str_contains($js, 'data-health-view') && str_contains($js, 'data-health-diagnostics') && str_contains($js, 'function toggleDiagnostics'),
    'diagnostics show approved operational fields and reference ID' => str_contains($js, 'data-diagnostic-environment') && str_contains($js, 'data-diagnostic-browser') && str_contains($js, 'data-diagnostic-viewport') && str_contains($js, 'data-diagnostic-response') && str_contains($js, 'data-diagnostic-reference') && str_contains($js, 'ONEID-UI-'),
    'view and copy diagnostics share the same safe values' => str_contains($js, 'var values = updateDiagnostics()') && str_contains($js, "'Reference ID: ' + values.reference"),
    'PTMK support is an icon link beside the footer email' => str_contains($js, 'oneid-health-support__right') && str_contains($js, 'oneid-health-support__link') && str_contains($js, 'fa fa-life-ring') && !str_contains($js, 'class="oneid-health-action" href="mailto:'),
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
