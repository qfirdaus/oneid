<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

$root = dirname(__DIR__, 2);
$dashboard = (string) file_get_contents($root . '/admin/dashboard.php');
$previewService = (string) file_get_contents($root . '/app/Sync/SyncPreviewService.php');
$summaryService = (string) file_get_contents($root . '/app/Sync/Odl/OdlShadowPreviewService.php');
$breakdownClass = (string) file_get_contents($root . '/app/Sync/Odl/UgStudentBreakdown.php');
$en = (string) file_get_contents($root . '/config/locales/en.php');
$ms = (string) file_get_contents($root . '/config/locales/ms.php');
require_once $root . '/app/Sync/Odl/UgStudentBreakdown.php';
$dynamicBreakdown = \OneId\App\Sync\Odl\UgStudentBreakdown::fromRows(array_merge(
    array_fill(0, 612, ['data4' => '100001']),
    array_fill(0, 760, ['data4' => '700001']),
    array_fill(0, 4286, ['data4' => '200001']),
    array_fill(0, 533, ['data4' => '800001'])
));
$checks = [
    'UG source count exposes a dedicated information control' =>
        str_contains($dashboard, 'id="sync_preview_ug_breakdown"')
        && str_contains($dashboard, 'oneid-sync-info-button'),
    'summary UG count exposes the same information control' =>
        str_contains($dashboard, 'id="external_summary_ug_breakdown"')
        && str_contains(
            $dashboard,
            "'#external_summary_ug_breakdown',"
        ),
    'breakdown appears only for the UG source' =>
        str_contains($dashboard, "sourceCode === 'STUDENT_UG'"),
    'breakdown is derived from the current UG source snapshot' =>
        str_contains($previewService, "UgStudentBreakdown::fromRows(\$externalRows)")
        && str_contains($summaryService, 'UgStudentBreakdown::fromRows($ugSnapshot->rows)')
        && str_contains($breakdownClass, "'1' => 'asasi'")
        && str_contains($breakdownClass, "'7' => 'diploma'")
        && str_contains($breakdownClass, "'2' => 'degree'")
        && str_contains($breakdownClass, "'8' => 'nieed'"),
    'tooltip contains no hardcoded student totals' =>
        !str_contains($dashboard, "ugBreakdownAsasi + ': 612")
        && !str_contains($dashboard, "ugBreakdownTotal + ': 6,190")
        && str_contains($dashboard, 'response.ug_breakdown'),
    'dynamic breakdown reconciles the current 6191-row shape' =>
        $dynamicBreakdown === [
            'asasi' => 612,
            'diploma' => 760,
            'degree' => 4286,
            'nieed' => 533,
            'other' => 0,
            'total' => 6191,
        ],
    'tooltip supports pointer and keyboard focus' =>
        str_contains($dashboard, "trigger:'hover focus'")
        && str_contains($dashboard, "attr('aria-label'"),
    'tooltip provides a comfortable responsive reading width' =>
        str_contains($dashboard, 'width: min(390px, calc(100vw - 32px))'),
    'breakdown is localized in Malay and English' =>
        str_contains($en, "'admin.sync.ug_breakdown_nieed'")
        && str_contains($ms, "'admin.sync.ug_breakdown_nieed'")
        && str_contains($en, "'admin.sync.ug_breakdown_other'")
        && str_contains($ms, "'admin.sync.ug_breakdown_other'"),
];

$failed = 0;
foreach ($checks as $label => $passed) {
    $failed += $passed ? 0 : 1;
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $label);
}
printf("RESULT checks=%d failed=%d\n", count($checks), $failed);
exit($failed === 0 ? 0 : 1);
