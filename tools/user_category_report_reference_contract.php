<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Admin/UserCategoryReportReference.php';

use OneId\App\Admin\UserCategoryReportReference;

$root = dirname(__DIR__);
$dashboard = (string) file_get_contents($root . '/admin/dashboard.php');
$endpoint = (string) file_get_contents($root . '/admin/user_list.php');
$ajax = (string) file_get_contents($root . '/lib/q_func.php');
$checks = 0;
$failed = 0;
$report = static function (bool $passed, string $label) use (&$checks, &$failed): void {
    $checks++;
    $failed += $passed ? 0 : 1;
    echo ($passed ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
};

$session = [];
$token = UserCategoryReportReference::issue($session, 'ADMIN-1', 9, 1000);
$report(strlen($token) === 64 && ctype_xdigit($token), 'reference uses an unguessable 256-bit hexadecimal token');
$report(UserCategoryReportReference::resolve($session, $token, 'ADMIN-1', 1001) === 9, 'valid reference resolves to its server-side category');

$wrongAdminRejected = false;
try {
    UserCategoryReportReference::resolve($session, $token, 'ADMIN-2', 1001);
} catch (RuntimeException $exception) {
    $wrongAdminRejected = $exception->getMessage() === 'USER_CATEGORY_REPORT_REFERENCE_FORBIDDEN';
}
$report($wrongAdminRejected, 'reference is bound to the administrator that issued it');

$expiredRejected = false;
try {
    UserCategoryReportReference::resolve($session, $token, 'ADMIN-1', 1901);
} catch (RuntimeException $exception) {
    $expiredRejected = $exception->getMessage() === 'USER_CATEGORY_REPORT_REFERENCE_EXPIRED';
}
$report($expiredRejected, 'reference expires after its bounded lifetime');

$report(
    str_contains($ajax, "['report_ref']")
        && str_contains($ajax, 'UserCategoryReportReference::issue'),
    'category response issues report references on the authenticated server session'
);
$report(
    str_contains($dashboard, 'user_list.php?ref=')
        && str_contains($dashboard, "'_blank', 'noopener,noreferrer'")
        && !str_contains($dashboard, 'user_list.php?category_id='),
    'dashboard opens a protected new tab without exposing category ID or name'
);
$report(
    str_contains($endpoint, 'UserCategoryReportReference::resolve')
        && str_contains($endpoint, 'admin_get_active_user_category($categoryId)')
        && !str_contains($endpoint, "\$_GET['category_name']"),
    'report validates the reference and obtains trusted category metadata from the database'
);
$report(
    str_contains($endpoint, 'oneid_require_admin_page();')
        && str_contains($endpoint, "oneid_require_admin_step_up(\$operation, 'ADMIN_ACCESS', false)"),
    'existing admin authorization and step-up controls remain enforced'
);
$report(
    str_contains($endpoint, 'onclick="window.close()"')
        && str_contains($endpoint, "oneid_translate('admin.user_list.close')")
        && !str_contains($endpoint, 'href="./dashboard.php"'),
    'dedicated report tab closes without opening another User Accounts page'
);
$report(
    str_contains($endpoint, '.report-shell { max-width: 1280px; margin: 24px auto; padding: 0 18px; width: 100%; }')
        && str_contains($endpoint, '.report-content { padding: 5mm; }'),
    'screen and print dimensions align with the shared administrative report format'
);

printf("RESULT checks=%d failed=%d\n", $checks, $failed);
exit($failed === 0 ? 0 : 1);
