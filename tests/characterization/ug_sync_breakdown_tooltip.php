<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

$root = dirname(__DIR__, 2);
$dashboard = (string) file_get_contents($root . '/admin/dashboard.php');
$en = (string) file_get_contents($root . '/config/locales/en.php');
$ms = (string) file_get_contents($root . '/config/locales/ms.php');
$checks = [
    'UG source count exposes a dedicated information control' =>
        str_contains($dashboard, 'id="sync_preview_ug_breakdown"')
        && str_contains($dashboard, 'oneid-sync-info-button'),
    'breakdown appears only for the UG source' =>
        str_contains($dashboard, "sourceCode === 'STUDENT_UG'"),
    'breakdown counts reconcile to 6190' =>
        str_contains($dashboard, "ugBreakdownAsasi + ': 612")
        && str_contains($dashboard, "ugBreakdownDiploma + ': 760")
        && str_contains($dashboard, "ugBreakdownDegree + ': 4,285")
        && str_contains($dashboard, "ugBreakdownNieed + ': 533")
        && 612 + 760 + 4285 + 533 === 6190,
    'tooltip supports pointer and keyboard focus' =>
        str_contains($dashboard, "trigger:'hover focus'")
        && str_contains($dashboard, "attr('aria-label'"),
    'breakdown is localized in Malay and English' =>
        str_contains($en, "'admin.sync.ug_breakdown_nieed'")
        && str_contains($ms, "'admin.sync.ug_breakdown_nieed'"),
];

$failed = 0;
foreach ($checks as $label => $passed) {
    $failed += $passed ? 0 : 1;
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $label);
}
printf("RESULT checks=%d failed=%d\n", count($checks), $failed);
exit($failed === 0 ? 0 : 1);
