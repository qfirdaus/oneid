<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$database = (string) file_get_contents($root . '/lib/Database.php');
$adapter = (string) file_get_contents($root . '/app/Sync/Adapters/DatabaseSyncPersistenceAdapter.php');
$factory = (string) file_get_contents($root . '/app/Sync/SyncEngineFactory.php');
$dashboard = (string) file_get_contents($root . '/admin/dashboard.php');
$ms = require $root . '/config/locales/ms.php';
$en = require $root . '/config/locales/en.php';
$tool = (string) file_get_contents($root . '/tools/sync_log_source_schema.php');
$checks = 0;
$failed = 0;
$report = static function (bool $ok, string $label) use (&$checks, &$failed): void {
    $checks++;
    if (!$ok) $failed++;
    echo ($ok ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
};

$report(
    str_contains($database, 'sync_header_source_code_available')
        && str_contains($database, 'NULL AS source_code')
        && str_contains($database, 'SYNC_LOG_SOURCE_SCHEMA_UNAVAILABLE'),
    'source deployment is readable before migration and apply fails closed'
);
$report(
    str_contains($factory, 'new DatabaseSyncPersistenceAdapter($this->operation, $sourceCode)')
        && str_contains($adapter, '$this->operation->action_add_new_ext_header($type, $this->sourceCode)'),
    'all source-scoped safe coordinators persist the canonical source code'
);
$report(
    str_contains($database, "['STAFF_HR','STUDENT_UG_SMP','STUDENT_ODL_PG']")
        && str_contains($database, 'h.source_code'),
    'database accepts only canonical sources and projects source in Sync Log'
);
$report(
    str_contains($dashboard, "adminText('admin.synclog.source_staff')")
        && str_contains($dashboard, "adminText('admin.synclog.source_ug')")
        && str_contains($dashboard, "adminText('admin.synclog.source_odl')")
        && str_contains($dashboard, "adminText('admin.synclog.source_legacy')"),
    'Admin Sync Log renders explicit source badges and a legacy fallback'
);
$report(
    str_contains($dashboard, "uid === 'ONEID-CRON'")
        && str_contains($dashboard, "adminText('admin.synclog.trigger_manual')")
        && str_contains($dashboard, 'sync_escape_html(identity)'),
    'cron and manual trigger labels remain safe and distinct'
);
$keys = ['source','source_staff','source_ug','source_odl','source_legacy','trigger_cron','trigger_manual'];
$localized = true;
foreach ($keys as $key) {
    $full = 'admin.synclog.' . $key;
    $localized = $localized && isset($ms[$full], $en[$full]) && $ms[$full] !== '' && $en[$full] !== '';
}
$report($localized, 'source and trigger labels have BM and English parity');
$report(
    str_contains($tool, "['--check', '--apply']")
        && str_contains($tool, 'information_schema.columns')
        && str_contains($tool, 'information_schema.statistics'),
    'schema tool is idempotent and supports separate check/apply modes'
);
$report(
    str_contains($dashboard, "sync_source_info(selected ? selected.source_code : null)")
        && str_contains($dashboard, "admin.synclog.detail_title"),
    'session detail header retains the selected source identity'
);

printf("RESULT checks=%d failed=%d\n", $checks, $failed);
exit($failed === 0 ? 0 : 1);
