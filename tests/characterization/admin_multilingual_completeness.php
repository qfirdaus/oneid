<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$dashboard = file_get_contents($root . '/admin/dashboard.php');
$userList = file_get_contents($root . '/admin/user_list.php');
$ms = require $root . '/config/locales/ms.php';
$en = require $root . '/config/locales/en.php';

$checks = 0;
$failed = 0;

$assert = static function (bool $condition, string $label) use (&$checks, &$failed): void {
    $checks++;
    if (!$condition) {
        $failed++;
    }
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
};

$assert(array_keys($ms) === array_keys($en), 'BM and English catalogue ordered parity');

foreach (['admin.sessions.', 'admin.audit.', 'admin.synclog.', 'admin.configuration.'] as $prefix) {
    $keys = array_filter(array_keys($ms), static fn (string $key): bool => str_starts_with($key, $prefix));
    $assert(count($keys) >= 10, $prefix . ' catalogue coverage');
}

$assert(str_contains($dashboard, "function adminText(key,parameters)"), 'dynamic Administrator text uses locale helper');
$assert(str_contains($dashboard, "adminText('admin.sessions.loading')"), 'Active Sessions dynamic states localized');
$assert(str_contains($dashboard, "adminText('admin.audit.searching')"), 'Audit Log dynamic states localized');
$assert(str_contains($dashboard, "adminText('admin.synclog.loading')"), 'Sync Audit dynamic states localized');
$assert(str_contains($dashboard, "adminText('admin.configuration.saving')"), 'Configuration dynamic states localized');
$assert(str_contains($userList, "oneid_translate('admin.user_list.title')"), 'category user list localized');

foreach (['STAFF_HR', 'STUDENT_UG', 'STUDENT_ODL_PG', 'MANUAL'] as $sourceCode) {
    $assert(str_contains($dashboard, $sourceCode), 'canonical source retained: ' . $sourceCode);
}

$assert(
    str_contains($dashboard, "fullConfirmation = response.full_confirmation")
    && str_contains($dashboard, "operationalConfirmation = response.operational_confirmation"),
    'exact Apply confirmation remains server-supplied and canonical'
);
$assert(str_contains($dashboard, "purpose=SECURITY_CONFIGURATION_CHANGE"), 'Admin Step-Up purpose remains canonical');

echo "RESULT checks={$checks} failed={$failed}" . PHP_EOL;
exit($failed === 0 ? 0 : 1);
