<?php

declare(strict_types=1);

$source = (string) file_get_contents(dirname(__DIR__, 2) . '/lib/Database.php');
$checks = [
    'Admin SSO category total uses administrator role' =>
        str_contains($source, "WHEN A.uc_name='Admin SSO' THEN U.u_type=1"),
    'Admin SSO user listing uses administrator role' =>
        str_contains($source, "WHEN C.uc_name='Admin SSO' THEN U.u_type=1"),
    'ordinary category total remains category scoped' =>
        str_contains($source, 'ELSE U.u_category=A.uc_id'),
    'ordinary category listing remains category scoped' =>
        str_contains($source, 'ELSE U.u_category=C.uc_id'),
];

$failed = 0;
foreach ($checks as $label => $passed) {
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $label);
    if (!$passed) $failed++;
}
printf("RESULT checks=%d failed=%d\n", count($checks), $failed);
exit($failed === 0 ? 0 : 1);
