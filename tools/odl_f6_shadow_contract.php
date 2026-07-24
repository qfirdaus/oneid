<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(2);
$root = dirname(__DIR__);
$service = (string) file_get_contents($root . '/app/Sync/Odl/OdlShadowPreviewService.php');
$endpoint = (string) file_get_contents($root . '/lib/q_func.php');
$security = (string) file_get_contents($root . '/lib/request_security.php');
$ui = (string) file_get_contents($root . '/admin/dashboard.php');
$runner = (string) file_get_contents($root . '/tools/odl_f6_shadow_preview.php');
$checks = 0; $failed = 0;
$report = static function (bool $ok, string $label) use (&$checks, &$failed): void {
    $checks++; $failed += $ok ? 0 : 1;
    printf("%s %s\n", $ok ? 'PASS' : 'FAIL', $label);
};
$report(str_contains($service, "'mode'] = 'odl_shadow_preview'") && str_contains($service, '$plan->safeProjection()'), 'service returns safe shadow projection');
$report(!str_contains($service, 'SyncApprovalService') && !str_contains($service, 'ApprovedSyncCoordinator'), 'service has no approval or Apply dependency');
$report(str_contains($endpoint, "isset(\$_POST['admin_preview_odl_shadow'])") && str_contains($endpoint, 'OdlShadowPreviewService'), 'admin endpoint wired separately');
$report(
    str_contains($security, "'admin_preview_odl_shadow'")
        && str_contains($security, "'admin_preview_sync_user'"),
    'request guard allows shadow preview as an admin action'
);
$report(str_contains($endpoint, "'can_apply' => false") && str_contains($endpoint, "'mutation_statements' => 0"), 'endpoint failure remains zero mutation');
$report(
    str_contains($ui, 'Sinkronisasi Pelajar ODL')
        && str_contains($ui, 'Sinkronisasi Pelajar Prasiswazah')
        && str_contains($ui, 'Sinkronisasi Pengguna Staf')
        && str_contains($ui, 'Ringkasan Sinkronisasi Pengguna')
        && str_contains($ui, "pick_preview_sync_user('STAFF_HR')")
        && str_contains($ui, "pick_preview_sync_user('STUDENT_UG')")
        && str_contains($ui, "pick_preview_sync_user('STUDENT_ODL_PG')")
        && str_contains($ui, 'Sedang menyediakan ringkasan sinkronisasi...')
        && str_contains($ui, 'Sedang menyemak data Staf...')
        && str_contains($ui, 'Sedang menyemak data Pelajar Prasiswazah...')
        && str_contains($ui, 'Sedang menyemak data Pelajar ODL...')
        && str_contains($ui, 'Ringkasan ini untuk semakan sahaja. Tiada perubahan data boleh dilaksanakan dari paparan ini.')
        && str_contains($ui, 'refresh_external_sync_notifications()')
        && str_contains($ui, 'response.sync_action_counts || {}')
        && str_contains($ui, 'Perubahan memerlukan semakan:')
        && str_contains($ui, 'external-action-notice')
        && str_contains($ui, 'fa-exclamation-triangle')
        && str_contains($ui, 'modal_odl_shadow_preview')
        && str_contains($ui, 'Maklumat teknikal dan rujukan audit')
        && substr_count($ui, 'oneid-return-add-user-options') === 7
        && str_contains($ui, "on('hidden.bs.modal'")
        && str_contains($ui, "$('#modal_open_add_user_option').modal('show')")
        && str_contains($ui, "data: {admin_preview_odl_shadow:''}"),
    'summary stays read-only while Staff UG and ODL use guarded sync flow'
);
$report(!preg_match('/\b(?:INSERT|UPDATE|DELETE|REPLACE|ALTER|DROP|TRUNCATE)\b/i', $runner), 'CLI runner contains no mutation SQL');
$report(str_contains($runner, 'OdlShadowPreviewService') && str_contains($runner, 'can_apply=false'), 'CLI uses same service and fail-safe output');
$output = []; $code = 1;
exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/tests/characterization/odl_f6_shadow_preview.php') . ' 2>&1', $output, $code);
$report($code === 0 && in_array('RESULT checks=14 failed=0', $output, true), 'shadow characterization passes');
printf("RESULT checks=%d failed=%d\n", $checks, $failed);
exit($failed === 0 ? 0 : 1);
