<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$database = (string) file_get_contents($root . '/lib/Database.php');
$endpoint = (string) file_get_contents($root . '/lib/q_func.php');
$dashboard = (string) file_get_contents($root . '/admin/dashboard.php');
$ms = require $root . '/config/locales/ms.php';
$en = require $root . '/config/locales/en.php';

$checks = 0;
$failed = 0;
$report = static function (bool $passed, string $label) use (&$checks, &$failed): void {
    $checks++;
    if (!$passed) {
        $failed++;
    }
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $label);
};

$report(
    str_contains($database, 'bool $includeInactive = false')
        && str_contains($database, '(:include_inactive=1 OR avail_status=1)'),
    'user search excludes inactive accounts by default'
);
$report(
    str_contains($database, 'AS replacement_u_id')
        && str_contains($database, 'active_user.u_id<>user_tbl.u_id'),
    'historical search resolves a distinct active replacement account'
);
$report(
    str_contains($endpoint, "FILTER_VALIDATE_BOOLEAN")
        && str_contains($endpoint, '$includeInactive'),
    'server accepts an explicit historical-record opt-in'
);
$report(
    str_contains($dashboard, 'id="search_user_include_inactive"')
        && str_contains($dashboard, "include_inactive:$('#search_user_include_inactive').is(':checked')?1:0"),
    'administrator UI controls the historical-record opt-in'
);
$report(
    str_contains($dashboard, 'adminI18n.userLegacyId')
        && str_contains($dashboard, 'adminI18n.userReplacedBy')
        && str_contains($dashboard, 'is-historical'),
    'historical results clearly show the old ID and active replacement'
);
$report(
    str_contains($dashboard, 'grid-template-columns:minmax(140px,160px) minmax(0,1fr) 82px')
        && str_contains($dashboard, '.user-search-suggestion{align-items:start;display:grid')
        && str_contains($dashboard, '.user-search-suggestion>.user-search-suggestion-status{align-items:center'),
    'active and historical results share fixed top-aligned columns'
);
$report(
    ($ms['admin.users.historical_record'] ?? '') === 'Rekod lama'
        && ($en['admin.users.historical_record'] ?? '') === 'Historical',
    'historical-record labels are available in Malay and English'
);

printf("RESULT checks=%d failed=%d\n", $checks, $failed);
exit($failed === 0 ? 0 : 1);
