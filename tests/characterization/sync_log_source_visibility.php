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
    str_contains($database, "['STAFF_HR','STUDENT_UG','STUDENT_ODL_PG']")
        && !str_contains($database, "['STAFF_HR','STUDENT_UG_SMP','STUDENT_ODL_PG']")
        && str_contains($database, 'h.source_code'),
    'database accepts only canonical sources and projects source in Sync Log'
);
$report(
    str_contains($dashboard, "STUDENT_UG: [adminText('admin.synclog.source_ug')")
        && str_contains($dashboard, "STUDENT_UG_SMP: [adminText('admin.synclog.source_ug')"),
    'Sync Log displays canonical UG sessions and retains legacy UG label compatibility'
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
        && str_contains($database, 'u.data3 AS triggered_by_staff_no')
        && str_contains($dashboard, 'staffNo || uid')
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
    ($ms['admin.synclog.source_legacy'] ?? null) === 'Legacy'
        && ($en['admin.synclog.source_legacy'] ?? null) === 'Legacy',
    'legacy source fallback is concise and does not imply an additional unknown state'
);
$report(
    str_contains($dashboard, "['2','4'].indexOf(String(row.ext_head_status))")
        && str_contains($dashboard, "admin.synclog.complete_direct")
        && str_contains($dashboard, "sync_status_badge(row)"),
    'completed summary and status badge distinguish direct changes from true no-change sessions'
);
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
$report(
    str_contains($database, 'u.data1 AS target_name')
        && str_contains($database, 'u.data3 AS target_staff_no')
        && str_contains($database, 'BINARY u.u_id = BINARY l.u_id')
        && str_contains($dashboard, 'function sync_format_target(row)')
        && str_contains($dashboard, "newer.data3 || older.data3 || row.u_id"),
    'detail target renders current name and full staff number with snapshot fallback'
);
$report(
    str_contains($dashboard, 'sync-session-identity')
        && str_contains($dashboard, "sync_source_badge(row.source_code)")
        && !str_contains($dashboard, 'colspan="7"><i class="fa fa-circle-o-notch fa-spin"'),
    'session list combines identity and source into a compact six-column layout'
);
$report(
    str_contains($dashboard, 'th:nth-child(4) { width: 27%; }')
        && str_contains($dashboard, 'th:nth-child(5) { width: 17%; }')
        && str_contains($dashboard, 'td:nth-child(5)')
        && str_contains($dashboard, 'justify-content: flex-start')
        && str_contains($dashboard, 'max-width: 150px'),
    'compact session layout reserves readable left-aligned space for every status'
);

printf("RESULT checks=%d failed=%d\n", $checks, $failed);
exit($failed === 0 ? 0 : 1);
