<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$userTop = (string) file_get_contents($root . '/page/const/top.php');
$adminTop = (string) file_get_contents($root . '/admin/const/top.php');
$userDashboard = (string) file_get_contents($root . '/page/dashboard.php');
$adminDashboard = (string) file_get_contents($root . '/admin/dashboard.php');
$css = (string) file_get_contents($root . '/public/dist/css/oneid-header-motion.css');

$checks = [
    'shared header class is present for user and admin' =>
        str_contains($userTop, 'oneid-header-lightline')
        && str_contains($adminTop, 'oneid-header-lightline'),
    'motion stylesheet is loaded by user and admin dashboards' =>
        str_contains($userDashboard, 'oneid-header-motion.css')
        && str_contains($adminDashboard, 'oneid-header-motion.css'),
    'light pass uses a visible alternating animation' =>
        str_contains($css, 'oneid-header-light-pass 8s linear infinite alternate')
        && str_contains($css, 'height: 4px')
        && str_contains($css, 'opacity: 1'),
    'light pass uses the OneID multicolour palette' =>
        str_contains($css, 'rgba(7, 91, 154, 0.88)')
        && str_contains($css, 'rgba(8, 127, 189, 0.96)')
        && str_contains($css, 'rgba(9, 163, 198, 0.96)')
        && str_contains($css, 'rgba(8, 203, 196, 0.88)'),
    'motion respects accessibility preference' =>
        str_contains($css, 'prefers-reduced-motion: reduce')
        && str_contains($css, 'animation: none'),
    'decorative layers cannot intercept interaction' =>
        substr_count($css, 'pointer-events: none') >= 2,
];

$failures = 0;
foreach ($checks as $description => $passed) {
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $description);
    if (!$passed) {
        $failures++;
    }
}
printf("RESULT checks=%d failures=%d\n", count($checks), $failures);
exit($failures === 0 ? 0 : 1);
