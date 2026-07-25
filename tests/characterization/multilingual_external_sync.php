<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$checks = 0;
$failed = 0;
$report = static function (bool $ok, string $label) use (&$checks, &$failed): void {
    $checks++;
    echo ($ok ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
    if (!$ok) {
        $failed++;
    }
};

$admin = file_get_contents($root . '/admin/dashboard.php') ?: '';
$ms = require $root . '/config/locales/ms.php';
$en = require $root . '/config/locales/en.php';
$syncKeys = array_values(array_filter(
    array_keys($ms),
    static fn(string $key): bool => str_starts_with($key, 'admin.sync.')
));

$report(array_keys($ms) === array_keys($en), 'BM and English catalogue ordered parity');
$report(count($syncKeys) >= 120, 'External Sync catalogue covers static and dynamic states');
$report(
    str_contains($admin, "oneid_translate('admin.sync.title')")
    && str_contains($admin, "oneid_translate('admin.sync.summary_title')")
    && str_contains($admin, 'var externalSyncText ='),
    'parent, child and JavaScript presentation use locale catalogue'
);
$report(
    str_contains($admin, "STAFF_HR: externalSyncText.staff")
    && str_contains($admin, "STUDENT_UG: externalSyncText.ug")
    && str_contains($admin, "STUDENT_ODL_PG: externalSyncText.odl"),
    'all three source views have localized display labels'
);
$report(
    str_contains($admin, 'operationalConfirmation = response.operational_confirmation')
    && str_contains($admin, 'typedConfirmation !== operationalConfirmation')
    && str_contains($admin, 'operational_sync_confirmation: typedConfirmation'),
    'canonical operational confirmation remains byte-for-byte server bound'
);
$report(
    str_contains($admin, 'fullConfirmation = response.full_confirmation')
    && str_contains($admin, 'typedConfirmation !== fullConfirmation')
    && str_contains($admin, 'full_sync_confirmation: typedConfirmation'),
    'canonical full confirmation remains byte-for-byte server bound'
);
$report(
    str_contains($admin, "sync_source_code:sourceCode")
    && str_contains($admin, "sync_source_code: sourceCode")
    && !str_contains($admin, 'sync_source_code:externalSyncText'),
    'source scope remains canonical and never comes from translation'
);
$report(
    str_contains($admin, "data: {admin_preview_odl_shadow:''}")
    && !str_contains($admin, 'admin_apply_odl_shadow'),
    'summary remains read-only'
);
$report(
    !array_filter($syncKeys, static fn(string $key): bool => str_contains($key, 'step_up')),
    'Admin Step-Up remains outside External Sync translation scope'
);

echo "RESULT checks={$checks} failed={$failed}" . PHP_EOL;
exit($failed === 0 ? 0 : 1);
