<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

$root = dirname(__DIR__);
$page = (string) file_get_contents($root . '/admin/dashboard.php');
$css = (string) file_get_contents($root . '/public/dist/css/oneid-web-app-modal.css');
$checks = 0;
$failed = 0;
$report = static function (bool $passed, string $label) use (&$checks, &$failed): void {
    $checks++;
    $failed += $passed ? 0 : 1;
    echo ($passed ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
};

$report(
    str_contains($page, 'oneid-web-app-modal.css?v=20260814-7'),
    'Administrator dashboard loads the refreshed category modal stylesheet'
);
$report(
    str_contains($page, 'oneid-category-dialog--manage')
        && str_contains($page, 'oneid-category-dialog--form')
        && substr_count($page, 'oneid-category-modal') === 3,
    'manage add and edit category dialogs share one professional modal system'
);
$report(
    str_contains($page, 'Safe category management')
        && str_contains($page, 'category-manage-icon')
        && str_contains($page, 'category-manage-counts'),
    'category manager presents safety guidance identity and assignment counts'
);
$report(
    str_contains($page, 'open_add_new_webapp_category_from_manager')
        && str_contains($page, 'id="btn_add_webapp_category"')
        && str_contains($page, "submitButton.prop('disabled', true)"),
    'manager-to-add workflow and duplicate-submit protection remain wired'
);
$report(
    str_contains($css, '.oneid-category-modal{')
        && str_contains($css, 'border-radius:14px!important')
        && str_contains($css, '.oneid-category-form-card{')
        && str_contains($css, 'grid-template-columns:40px minmax(0,1fr) auto auto'),
    'category dialogs use rounded cards and a correctly sized action grid'
);
$report(
    str_contains($css, '@media(max-width:640px)')
        && str_contains($css, 'grid-template-columns:36px minmax(0,1fr) auto')
        && str_contains($css, '.oneid-category-footer{display:grid;grid-template-columns:1fr 1fr'),
    'category dialogs retain a compact responsive layout'
);
$report(
    str_contains($css, '#modal_add_new_app .oneid-app-info-dialog,#modal_add_new_webapp_category .oneid-category-dialog,#modal_manage_webapp_categories .oneid-category-dialog')
        && str_contains($css, 'max-width:880px;width:min(880px,calc(100vw - 30px))')
        && str_contains($css, 'background:linear-gradient(135deg,#087eaf 0%,#1398d0 100%)!important')
        && str_contains($css, 'border-radius:12px 12px 0 0!important')
        && str_contains($css, 'color:rgba(255,255,255,.84)!important'),
    'manage category add category and add application share the Metadata Translation modal shell'
);
$report(
    str_contains($page, "assignedCount === 0")
        && str_contains($page, "String(category.sp_group_id) === '0'")
        && str_contains($page, '.category-manage-remove:not(:disabled)'),
    'protected and assigned categories remain non-removable in the UI'
);

printf("RESULT checks=%d failed=%d\n", $checks, $failed);
exit($failed === 0 ? 0 : 1);
