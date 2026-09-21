<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

require_once dirname(__DIR__, 2) . '/bootstrap/sync_runtime.php';

use OneId\App\Sync\SourceIdentityCollisionPolicy;

$base = [
    'u_id' => 'OLD-MATRIC',
    'same_source' => 0,
    'avail_status' => 0,
    'account_source' => 'external',
    'sync_protected' => 0,
    'active_membership' => 0,
];
$checks = [
    'same source owner remains writable' => SourceIdentityCollisionPolicy::permits(
        'CURRENT',
        array_replace($base, ['u_id' => 'CURRENT', 'same_source' => 1])
    ),
    'inactive unprotected external identity can be succeeded by a new source user' =>
        SourceIdentityCollisionPolicy::permits('NEW-MATRIC', $base),
    'same user without source ownership remains blocked' =>
        !SourceIdentityCollisionPolicy::permits('OLD-MATRIC', $base),
    'active identity collision remains blocked' =>
        !SourceIdentityCollisionPolicy::permits(
            'NEW-MATRIC',
            array_replace($base, ['avail_status' => 1])
        ),
    'manual identity collision remains blocked' =>
        !SourceIdentityCollisionPolicy::permits(
            'NEW-MATRIC',
            array_replace($base, ['account_source' => 'manual'])
        ),
    'protected identity collision remains blocked' =>
        !SourceIdentityCollisionPolicy::permits(
            'NEW-MATRIC',
            array_replace($base, ['sync_protected' => 1])
        ),
    'identity owned by an active source remains blocked' =>
        !SourceIdentityCollisionPolicy::permits(
            'NEW-MATRIC',
            array_replace($base, ['active_membership' => 1])
        ),
];

$failed = 0;
foreach ($checks as $label => $passed) {
    $failed += $passed ? 0 : 1;
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $label);
}
printf("RESULT checks=%d failed=%d\n", count($checks), $failed);
exit($failed === 0 ? 0 : 1);
