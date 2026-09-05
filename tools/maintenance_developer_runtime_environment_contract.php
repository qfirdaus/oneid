<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

require_once dirname(__DIR__) . '/config/runtime.php';

$at = new DateTimeImmutable('2026-09-05T12:30:00+08:00');
$set = static function (array $values): void {
    foreach ($values as $key => $value) {
        putenv($key . '=' . $value);
    }
};
$check = static function (bool $passed, string $label, int &$failed): void {
    echo ($passed ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
    $failed += $passed ? 0 : 1;
};
$failed = 0;
$set([
    'ONEID_MAINTENANCE_DEVELOPER_ACCESS_ENABLED' => 'true',
    'ONEID_ENVIRONMENT' => 'production',
    'ONEID_MAINTENANCE_DEVELOPER_PRODUCTION_APPROVED' => 'false',
]);
$check(!oneid_maintenance_developer_access_enabled($at), 'production rejects raw flag without deployment approval', $failed);
$set([
    'ONEID_MAINTENANCE_DEVELOPER_PRODUCTION_APPROVED' => 'true',
]);
$check(oneid_maintenance_developer_access_enabled($at), 'production accepts one-time deployment approval', $failed);
$set(['ONEID_MAINTENANCE_DEVELOPER_ACCESS_ENABLED' => 'false']);
$check(!oneid_maintenance_developer_access_enabled($at), 'production emergency kill switch always wins', $failed);
$set([
    'ONEID_MAINTENANCE_DEVELOPER_ACCESS_ENABLED' => 'true',
    'ONEID_ENVIRONMENT' => 'unknown',
]);
$check(!oneid_maintenance_developer_access_enabled($at), 'unknown environment remains fail closed', $failed);
$set([
    'ONEID_ENVIRONMENT' => 'staging',
    'ONEID_MAINTENANCE_DEVELOPER_STAGING_APPROVED' => 'false',
]);
$check(!oneid_maintenance_developer_access_enabled($at), 'staging rejects raw flag without deployment approval', $failed);
$set([
    'ONEID_MAINTENANCE_DEVELOPER_STAGING_APPROVED' => 'true',
]);
$check(oneid_maintenance_developer_access_enabled($at), 'staging accepts one-time deployment approval', $failed);

printf("RESULT checks=6 failed=%d\n", $failed);
exit($failed === 0 ? 0 : 1);
