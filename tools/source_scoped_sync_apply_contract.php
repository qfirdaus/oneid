<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(2);

$root = dirname(__DIR__);
$q = (string) file_get_contents($root . '/lib/q_func.php');
$ui = (string) file_get_contents($root . '/admin/dashboard.php');
$factory = (string) file_get_contents($root . '/app/Sync/SyncEngineFactory.php');
$scope = (string) file_get_contents($root . '/app/Sync/SyncSourceScope.php');
$adapter = (string) file_get_contents(
    $root . '/app/Sync/Adapters/SourceScopedSyncPersistenceAdapter.php'
);
$checks = [
    'UI routes Staff and UG to guarded preview and Apply flow' =>
        str_contains($ui, "pick_preview_sync_user('STAFF_HR')")
        && str_contains($ui, "pick_preview_sync_user('STUDENT_UG')")
        && str_contains($ui, 'sync_source_code:sourceCode')
        && str_contains($ui, 'sync_source_code: sourceCode'),
    'Summary and ODL remain on read-only shadow preview' =>
        str_contains($ui, "preview_external_sync_view('SUMMARY')")
        && str_contains($ui, "preview_external_sync_view('STUDENT_ODL_PG')"),
    'preview and every Apply endpoint require a source scope' =>
        substr_count($q, 'SyncSourceScope::fromCode(') === 4
        && substr_count($q, '$syncSourceCode') >= 9,
    'factory rebuilds approved plan using the selected source' =>
        str_contains($factory, 'buildSafeOrchestrator(null, $sourceCode)')
        && str_contains($factory, 'buildSafeOrchestrator($selector, $sourceCode)')
        && str_contains($factory, 'SyncSourceScope::fromCode($sourceCode)'),
    'only Staff and UG sources are accepted' =>
        str_contains($scope, 'StaffSource::SOURCE_CODE')
        && str_contains($scope, 'UgStudentSource::SOURCE_CODE')
        && str_contains($scope, "SYNC_SOURCE_INVALID"),
    'planner reads are category and provenance scoped before deactivation decisions' =>
        str_contains($adapter, 'in_array(')
        && str_contains($adapter, "\$user['u_category']")
        && str_contains($adapter, '$activeSourceUserIds')
        && str_contains($adapter, '$this->inner->activeUsers()'),
];
$failed = 0;
foreach ($checks as $label => $passed) {
    $failed += $passed ? 0 : 1;
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $label);
}
$output = [];
$code = 1;
exec(
    escapeshellarg(PHP_BINARY) . ' '
    . escapeshellarg($root . '/tests/characterization/source_scoped_sync_persistence.php')
    . ' 2>&1',
    $output,
    $code
);
$passed = $code === 0 && in_array('RESULT checks=5 failed=0', $output, true);
$failed += $passed ? 0 : 1;
printf("%s source-scoped persistence characterization\n", $passed ? 'PASS' : 'FAIL');
printf("RESULT checks=%d failed=%d\n", count($checks) + 1, $failed);
exit($failed === 0 ? 0 : 1);
