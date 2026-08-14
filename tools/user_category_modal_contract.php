<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

$dashboard = (string) file_get_contents(dirname(__DIR__) . '/admin/dashboard.php');
$checks = 0;
$failed = 0;
$report = static function (bool $passed, string $label) use (&$checks, &$failed): void {
    $checks++;
    $failed += $passed ? 0 : 1;
    echo ($passed ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
};

$report(
    str_contains($dashboard, 'oneid-user-category-dialog')
        && str_contains($dashboard, 'oneid-sync-child-modal oneid-user-category-modal')
        && str_contains($dashboard, 'oneid-sync-child-header oneid-user-category-header'),
    'new user category reuses the Sync User modal shell'
);
$report(
    str_contains($dashboard, 'oneid-sync-child-heading-icon')
        && str_contains($dashboard, 'Kategori Pengguna Baharu')
        && str_contains($dashboard, 'Cipta kumpulan akses'),
    'new user category header has a clear icon title and description'
);
$report(
    str_contains($dashboard, 'oneid-user-category-card__heading')
        && str_contains($dashboard, 'oneid-user-category-card__content')
        && str_contains($dashboard, 'maxlength="100"'),
    'category input is presented in a structured bounded form card'
);
$report(
    str_contains($dashboard, 'oneid-sync-child-footer oneid-user-category-footer')
        && str_contains($dashboard, 'id="btn_add_user_category"'),
    'category actions use the shared right-aligned Sync User footer'
);
$report(
    str_contains($dashboard, "data.push({name: 'action_add_new_category', value: ''})")
        && str_contains($dashboard, "admin_get_all_user_category(0)"),
    'existing category creation and refresh flow remains connected'
);
$report(
    str_contains($dashboard, '#modal_add_new_category .oneid-user-category-dialog')
        && str_contains($dashboard, 'width: min(680px, calc(100vw - 30px))')
        && str_contains($dashboard, '#modal_add_new_category .oneid-user-category-card'),
    'new user category has a responsive Sync User-sized presentation'
);

printf("RESULT checks=%d failed=%d\n", $checks, $failed);
exit($failed === 0 ? 0 : 1);
