<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$dashboard = file_get_contents($root . '/admin/dashboard.php');
$handler = file_get_contents($root . '/lib/q_func.php');

if ($dashboard === false || $handler === false) {
    fwrite(STDERR, "FAIL unable to read WA1 sources\n");
    exit(1);
}

$checks = [
    'add icon label no longer claims unenforced 65x65' => str_contains($dashboard, 'App Icon (optional)'),
    'edit icon label explains optional replacement' => str_contains($dashboard, 'Replace App Icon (optional)'),
    'accepted MIME types are disclosed to browser' => substr_count($dashboard, 'accept="image/jpeg,image/png,image/gif,image/webp"') >= 2,
    'SSO checkbox explains direct-link semantics' => substr_count($dashboard, 'Direct link only (this app does not support OneID SSO)') >= 2,
    'operational outcome uses SweetAlert' => str_contains($dashboard, 'function showAppOperationalAlert') && str_contains($dashboard, "swal(title, message + '\\nCode: '"),
    'legacy in-modal feedback panels are absent' => !str_contains($dashboard, 'add_app_operational_feedback') && !str_contains($dashboard, 'edit_app_operational_feedback'),
    'add submit has double-submit guard' => str_contains($dashboard, 'addAppSubmitting') && str_contains($dashboard, "setAppFormSubmitting('#btn_add_app_submit'"),
    'edit submit has double-submit guard' => str_contains($dashboard, 'editAppSubmitting') && str_contains($dashboard, "setAppFormSubmitting('#btn_edit_app_submit'"),
    'add and edit confirmation use SweetAlert' => str_contains($dashboard, "title: 'Add App'") && str_contains($dashboard, "title: 'Save App Changes'") && !str_contains($dashboard, 'window.confirm('),
    'transport errors produce visible feedback' => substr_count($dashboard, 'WA1_REQUEST_FAILED') >= 1 && substr_count($dashboard, 'request failed before a valid result was received') >= 2,
    'client preflight and HTTP 413 use SweetAlert codes' => str_contains($dashboard, 'WA3_CLIENT_ICON_TYPE_REJECTED') && str_contains($dashboard, 'WA3_CLIENT_ICON_SIZE_REJECTED') && str_contains($dashboard, 'WA3_UPLOAD_REQUEST_TOO_LARGE'),
    'responses include code and correlation reference' => substr_count($handler, "'correlation_id'=>\$exception->correlationId") >= 2 && str_contains($handler, 'WebAppService($operation)'),
    'icon rejection and atomic success are explicitly represented' => str_contains((string) file_get_contents($root . '/app/Admin/WebAppService.php'), 'WA3_ICON_REJECTED') && str_contains((string) file_get_contents($root . '/app/Admin/WebAppService.php'), 'WA4_APP_CREATED_ENVIRONMENT_ASSET') && substr_count($dashboard, "response.icon_status === 'rejected'") >= 2,
];

$passed = 0;
foreach ($checks as $label => $ok) {
    echo ($ok ? 'PASS' : 'FAIL') . ' ' . $label . "\n";
    $passed += $ok ? 1 : 0;
}

printf("RESULT %d/%d\n", $passed, count($checks));
exit($passed === count($checks) ? 0 : 1);
