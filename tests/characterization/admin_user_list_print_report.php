<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$source = (string) file_get_contents($root . '/admin/user_list.php');
$checks = [
    'report retains admin and active-session guards' =>
        str_contains($source, 'oneid_require_admin_page();')
        && str_contains($source, 'oneid_require_active_sso_page($operation);'),
    'report escapes every user-facing database field' =>
        str_contains($source, '$escape($user[\'data4\'] ?? \'\')')
        && str_contains($source, '$escape($user[\'data1\'] ?? \'\')')
        && str_contains($source, '$escape($description)'),
    'report provides print action without external JavaScript' =>
        str_contains($source, 'onclick="window.print()"')
        && !str_contains($source, '<script'),
    'print stylesheet targets A4 landscape and repeated headers' =>
        str_contains($source, '@page { size: A4 landscape;')
        && str_contains($source, 'thead { display: table-header-group; }'),
    'print rows avoid page splitting' =>
        str_contains($source, 'page-break-inside: avoid;'),
    'report contains branded header, metadata and total' =>
        str_contains($source, 'logo_upnm_30.png')
        && str_contains($source, 'logo_oneid.png')
        && str_contains($source, 'reportReference')
        && str_contains($source, 'count($userlist)'),
];

$failed = 0;
foreach ($checks as $label => $passed) {
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $label);
    if (!$passed) $failed++;
}
printf("RESULT checks=%d failed=%d\n", count($checks), $failed);
exit($failed === 0 ? 0 : 1);
