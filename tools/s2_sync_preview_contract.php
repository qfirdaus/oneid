<?php

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(2);
}

$root = dirname(__DIR__);
$checks = 0;
$failed = 0;
$report = static function (bool $ok, string $label, string $detail = '') use (&$checks, &$failed): void {
    $checks++;
    if (!$ok) $failed++;
    printf("%s %-64s%s\n", $ok ? 'PASS' : 'FAIL', $label, $detail === '' ? '' : ' ' . $detail);
};

$files = [
    'app/Sync/DTO/SyncPlan.php',
    'app/Sync/ExternalRowNormalizer.php',
    'app/Sync/SyncPlanner.php',
    'app/Sync/SyncPreviewService.php',
    'lib/external_data_source_API.php',
    'lib/skp_api.php',
    'lib/request_security.php',
    'lib/q_func.php',
    'admin/dashboard.php',
    'tests/characterization/s2_sync_preview_zero_mutation.php',
];
$source = [];
foreach ($files as $file) {
    $path = $root . '/' . $file;
    $source[$file] = is_file($path) ? (string) file_get_contents($path) : '';
    $output = [];
    $code = 1;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1', $output, $code);
    $report($source[$file] !== '' && $code === 0, 'source and PHP lint: ' . $file);
}

$planner = $source['app/Sync/SyncPlanner.php'];
$service = $source['app/Sync/SyncPreviewService.php'];
$guard = $source['lib/request_security.php'];
$qFunc = $source['lib/q_func.php'];
$dashboard = $source['admin/dashboard.php'];
$externalSource = $source['lib/external_data_source_API.php'];
$compatibilityExternalSource = $source['lib/skp_api.php'];

$report(str_contains($guard, "'admin_preview_sync_user'"), 'preview action is admin-authorized');
$report(str_contains($planner, 'protectedManualUsers') && str_contains($planner, 'discardedProtectedCollisions'), 'planner enforces S1 provenance protection');
$report(str_contains($service, 'activeUsers()') && str_contains($service, 'inactiveUserIds()'), 'preview service reads required snapshots');
$report(!str_contains($service, 'begin()') && !str_contains($service, 'createHeader(') && !str_contains($service, 'commit()'), 'preview service contains no mutation call');
$report(str_contains($service, "'can_apply' => false") && str_contains($service, "'mode' => 'preview'"), 'preview response cannot authorize apply');
$report(str_contains($service, 'array_slice($plan->safeProjection()'), 'response uses redacted action projection');
$report(str_contains($qFunc, "if(isset( \$_POST['admin_preview_sync_user']))"), 'q_func exposes preview controller');
$report(str_contains($qFunc, "getenv('ONEID_SYNC_APPLY_ENABLED')") && str_contains($qFunc, "'SYNC_APPLY_DISABLED'"), 'legacy apply defaults to feature-gated disabled');
$report(str_contains($externalSource, "throw new RuntimeException('ODBC_EXTENSION_UNAVAILABLE')") && str_contains($externalSource, "throw new RuntimeException('EXTERNAL_STAFF_CONNECTION_FAILED')") && !str_contains(substr($externalSource, 0, strpos($externalSource, 'function EXTERNAL_DATA_SOURCE_GET_SPECIFIC_USER')), 'exit;'), 'preview source fails with catchable diagnostic codes');
$report(substr_count($externalSource, 'FROM ehrmdb.dbo.SSO_Staf_Aktif') === 2 && !str_contains($externalSource, 'FROM stafdb'), 'active integration uses ehrmdb staff view only');
$report(substr_count($compatibilityExternalSource, 'FROM ehrmdb.dbo.SSO_Staf_Aktif') === 2 && !str_contains($compatibilityExternalSource, 'FROM stafdb'), 'compatibility integration uses ehrmdb staff view only');
$normalizer = $source['app/Sync/ExternalRowNormalizer.php'];
$report(str_contains($normalizer, "'idpekerja' => 'data2'") && str_contains($normalizer, "'no_matrik' => 'data4'") && str_contains($normalizer, "'jenis' => 'ext_data_source_category'"), 'pure normalizer maps FreeTDS source labels');
$report(substr_count($externalSource, 'ExternalRowNormalizer::normalize($myRow)') === 4, 'active integration normalizes every ODBC row');
$report(substr_count($compatibilityExternalSource, 'ExternalRowNormalizer::normalize($myRow)') === 4, 'compatibility integration normalizes every ODBC row');
$report(str_contains($qFunc, "'UNEXPECTED_PREVIEW_ERROR'") && str_contains($qFunc, 'code=%s'), 'preview logs allowlisted diagnostic code only');
$report(str_contains($dashboard, "data: {admin_preview_sync_user:''}"), 'dashboard posts preview action');
$report(!str_contains($dashboard, "data: {admin_add_sync_user:''}"), 'dashboard no longer posts mutating action');
$report(str_contains($dashboard, 'S2 is preview-only. There is no Apply action'), 'dashboard explains preview-only boundary');

$output = [];
$code = 1;
exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/tests/characterization/s2_sync_preview_zero_mutation.php') . ' 2>&1', $output, $code);
$detail = implode(' | ', array_slice($output, -2));
$report($code === 0 && in_array('RESULT checks=18 failed=0', $output, true), 'zero-mutation fixture passes', $detail);

printf("RESULT checks=%d failed=%d\n", $checks, $failed);
exit($failed === 0 ? 0 : 1);
