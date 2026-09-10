<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

$root = dirname(__DIR__);
$entries = [
    'admin/dashboard.php' => '../dist/',
    'admin/user_list.php' => '../dist/',
    'page/dashboard.php' => '../dist/',
    'page/admin_step_up.php' => '../dist/',
    'page/user_mfa_security.php' => '../dist/',
    'index.php' => 'dist/',
];
$failures = [];

foreach ($entries as $file => $prefix) {
    $source = (string) file_get_contents($root . '/' . $file);
    $hasSweetAlert = str_contains($source, 'sweetalert.min.js');
    $hasThemeCss = str_contains($source, $prefix . 'css/oneid-professional-alert.css');
    $hasThemeJs = str_contains($source, $prefix . 'js/oneid-professional-alert.js');
    $ordered = strpos($source, 'sweetalert.min.js') < strpos($source, 'oneid-professional-alert.js');
    $passed = $hasSweetAlert && $hasThemeCss && $hasThemeJs && $ordered;
    echo ($passed ? 'PASS ' : 'FAIL ') . $file . " loads the presentation theme after SweetAlert\n";
    if (!$passed) {
        $failures[] = $file;
    }
}

$javascript = (string) file_get_contents($root . '/public/dist/js/oneid-professional-alert.js');
$presentationOnly = !preg_match('/\b(?:fetch|XMLHttpRequest|ajax|location\s*=|submit)\b/i', $javascript)
    && str_contains($javascript, 'MutationObserver')
    && str_contains($javascript, 'oneid-professional-alert');
echo ($presentationOnly ? 'PASS ' : 'FAIL ') . "theme is DOM presentation only\n";
if (!$presentationOnly) {
    $failures[] = 'presentation_only';
}

$observerIsBounded = str_contains($javascript, "attributeFilter:['class']")
    && !str_contains($javascript, "attributes:true,childList:true,subtree:true")
    && str_contains($javascript, 'needsTheme');
echo ($observerIsBounded ? 'PASS ' : 'FAIL ') . "observer cannot loop over its own subtree and style mutations\n";
if (!$observerIsBounded) {
    $failures[] = 'observer_bounded';
}

$css = (string) file_get_contents($root . '/public/dist/css/oneid-professional-alert.css');
$responsive = str_contains($css, '@media(max-width:730px)')
    && str_contains($css, 'max-width:calc(100vw - 32px)');
echo ($responsive ? 'PASS ' : 'FAIL ') . "theme is viewport bounded and responsive\n";
if (!$responsive) {
    $failures[] = 'responsive';
}

printf("RESULT checks=%d failed=%d\n", count($entries) + 3, count($failures));
exit($failures === [] ? 0 : 1);
