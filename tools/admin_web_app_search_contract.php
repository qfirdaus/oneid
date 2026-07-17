<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$dashboard = (string) file_get_contents($root . '/admin/dashboard.php');
$checks = [
    'search input and clear control exist' => str_contains($dashboard, 'id="admin_web_app_search"') && str_contains($dashboard, 'id="admin_web_app_search_clear"'),
    'search status is announced accessibly' => str_contains($dashboard, 'id="admin_web_app_search_status" aria-live="polite"'),
    'loaded directory data is retained client-side' => str_contains($dashboard, 'var adminWebAppGroups = []'),
    'search covers name description domain and app ID' => str_contains($dashboard, '[application.sp_name, application.sp_description, application.sp_domain, application.sp_id]'),
    'matching is case insensitive' => str_contains($dashboard, 'toLocaleLowerCase()'),
    'category counts use filtered applications' => str_contains($dashboard, "<strong>'+applications.length+'</strong>"),
    'first matching category is selected' => str_contains($dashboard, 'matchingTabs.length ? matchingTabs[0]'),
    'result status shows matching and total counts' => str_contains($dashboard, "'Memaparkan ' + matchingCount + ' daripada ' + totalCount"),
    'clear restores directory and input focus' => str_contains($dashboard, "$('#admin_web_app_search').val('').focus()") && str_contains($dashboard, 'renderAdminWebAppDirectory();'),
    'search does not introduce a backend action' => !str_contains((string) file_get_contents($root . '/lib/q_func.php'), 'admin_search_web_app'),
    'responsive search styling exists' => str_contains($dashboard, '#follo_8 .web-app-search-row') && str_contains($dashboard, '#follo_8 .web-app-search input:focus'),
];

$failed = 0;
foreach ($checks as $label => $passed) {
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $label);
    if (!$passed) $failed++;
}
printf("RESULT %d/%d\n", count($checks) - $failed, count($checks));
exit($failed === 0 ? 0 : 1);
