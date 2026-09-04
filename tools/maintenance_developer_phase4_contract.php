<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

$root = dirname(__DIR__);
$guard = (string) file_get_contents($root . '/lib/request_security.php');
$api = (string) file_get_contents($root . '/lib/q_func.php');
$dashboard = (string) file_get_contents($root . '/admin/dashboard.php');
$endpoint = (string) file_get_contents($root . '/app/Maintenance/MaintenanceDeveloperAccessAdminEndpoint.php');
$service = (string) file_get_contents($root . '/app/Maintenance/MaintenanceDeveloperAccessService.php');
$repository = (string) file_get_contents($root . '/app/Maintenance/PdoMaintenanceDeveloperAccessRepository.php');
$returnContext = (string) file_get_contents($root . '/app/Auth/AdminStepUpReturnContext.php');
$javascript = (string) file_get_contents($root . '/public/dist/js/oneid-maintenance-developer-admin.js');
$css = (string) file_get_contents($root . '/public/dist/css/oneid-maintenance-developer-admin.css');
$layoutJavascript = (string) file_get_contents($root . '/public/dist/js/oneid-maintenance-developer-layout.js');
$layoutCss = (string) file_get_contents($root . '/public/dist/css/oneid-maintenance-developer-layout.css');
$ms = (string) file_get_contents($root . '/config/locales/ms.php');
$en = (string) file_get_contents($root . '/config/locales/en.php');

$checks = [];
$actions = [
    'admin_search_maintenance_developer_candidates',
    'admin_list_maintenance_developer_access',
    'admin_grant_maintenance_developer_access',
    'admin_revoke_maintenance_developer_access',
];
$checks['all four actions are recognized admin actions'] = array_reduce(
    $actions,
    static fn(bool $ok, string $action): bool => $ok && str_contains($guard, "'{$action}'"),
    true
);
$checks['grant and revoke require exact security configuration step up'] =
    substr_count($guard, "'admin_grant_maintenance_developer_access'") >= 2
    && substr_count($guard, "'admin_revoke_maintenance_developer_access'") >= 2
    && str_contains($guard, "? 'SECURITY_CONFIGURATION_CHANGE'");
$checks['endpoint uses server supplied admin authorization signal'] =
    str_contains($endpoint, '$admin,')
    && str_contains($endpoint, '$ip,')
    && str_contains($endpoint, 'true')
    && !str_contains($endpoint, "post['admin_step_up_authorized']");
$checks['typed confirmations bind mutations to exact subject'] =
    str_contains($endpoint, "'GRANT MAINTENANCE ACCESS ' . \$user")
    && str_contains($endpoint, "'REVOKE MAINTENANCE ACCESS ' . (string) \$grantId");
$checks['admin API returns safe typed errors without exception text'] =
    str_contains($api, 'MaintenanceDeveloperAccessException')
    && str_contains($api, "'MAINTENANCE_ACCESS_UNAVAILABLE'")
    && !str_contains($api, "\$exception->getMessage(),'message'=>'Maintenance developer");
$checks['candidate search and list use parameterized bounded queries'] =
    str_contains($repository, 'LIMIT 10')
    && str_contains($repository, 'LIMIT 100')
    && str_contains($repository, ':query_id')
    && str_contains($repository, ':query_reference');
$checks['admin presentation uses staff number and does not render canonical identity'] =
    str_contains($repository, 'u.data3 staff_number')
    && str_contains($javascript, 'function publicIdentity(row)')
    && str_contains($javascript, 'identity.staff')
    && !str_contains($javascript, "esc(u.u_id)+' · '")
    && !str_contains($javascript, "esc(g.u_id)+')'");
$checks['service returns effective scheduled active and expired states'] =
    str_contains($service, "? 'SCHEDULED'")
    && str_contains($service, "? 'EXPIRED' : 'ACTIVE'");
$checks['dashboard provides localized management tab and form'] =
    str_contains($dashboard, 'id="configuration_maintenance_developer"')
    && str_contains($dashboard, "oneid_translate('admin.maintenance_developer.title')")
    && str_contains($ms, "'admin.maintenance_developer.title'")
    && str_contains($en, "'admin.maintenance_developer.title'");
$checks['UI sends CSRF and escapes dynamic content'] =
    str_contains($javascript, "'X-CSRF-Token':csrf")
    && str_contains($javascript, 'function esc(v)')
    && str_contains($dashboard, 'data-csrf="<?=htmlspecialchars(oneid_csrf_token()');
$checks['UI has responsive dedicated styling'] =
    str_contains($dashboard, 'oneid-maintenance-developer-admin.css')
    && (str_contains($css, '@media(max-width:900px)') || str_contains($css, '@media(max-width:700px)'));
$checks['review uses professional OneID modal while retaining protected grant payload'] =
    str_contains($layoutJavascript, 'oneid-md-review-modal')
    && str_contains($layoutJavascript, "event.stopImmediatePropagation()")
    && str_contains($layoutJavascript, "'X-CSRF-Token':\$root.data('csrf')")
    && str_contains($layoutJavascript, "confirmation:'GRANT MAINTENANCE ACCESS '+data.user")
    && str_contains($layoutCss, '.oneid-md-review-header')
    && str_contains($layoutCss, '.oneid-md-stepup-note');
$checks['grant draft survives admin step up and expires from session storage'] =
    str_contains($layoutJavascript, "draftKey='oneid_maintenance_developer_grant_draft_v1'")
    && str_contains($layoutJavascript, 'window.sessionStorage.setItem')
    && str_contains($layoutJavascript, '>600000')
    && str_contains($layoutJavascript, "returnContext==='configuration_maintenance_developer'")
    && str_contains($layoutJavascript, 'restoreDraft(draft)')
    && str_contains($layoutJavascript, 'clearDraft();$modal.modal');
$checks['grant dates are localized and metadata stays on one readable line'] =
    str_contains($javascript, 'Intl.DateTimeFormat')
    && str_contains($javascript, "timeZone:'Asia/Kuala_Lumpur'")
    && str_contains($javascript, 'readableDate(g.valid_from,true)')
    && str_contains($layoutJavascript, 'oneidMaintenanceDeveloperReadableDate')
    && str_contains($layoutCss, 'white-space:nowrap');
$checks['revoke uses structured SweetAlert without browser prompts and survives step up'] =
    !str_contains($javascript, 'window.prompt(')
    && str_contains($layoutJavascript, 'oneid-md-revoke-panel')
    && str_contains($layoutJavascript, 'showInputError')
    && str_contains($layoutJavascript, "confirmation:'REVOKE MAINTENANCE ACCESS '+data.id")
    && str_contains($layoutJavascript, 'saveRevokeDraft')
    && str_contains($layoutJavascript, 'loadRevokeDraft')
    && str_contains($layoutCss, '.oneid-md-revoke-warning');
$checks['step up return context restores developer access tab'] =
    str_contains($returnContext, "'configuration_maintenance_developer'")
    && str_contains($dashboard, 'return=configuration_maintenance_developer');
$checks['developer access tab remains in the general configuration group'] =
    str_contains($dashboard,
        "target==='#configuration_maintenance'||target==='#configuration_maintenance_developer'){return 'general';}");
$checks['phase 4 boundary and later controlled progression are recorded'] =
    str_contains((string) file_get_contents($root . '/docs/MD4_ENDPOINT_DAN_UI_ADMIN_AKSES_DEVELOPER.md'),
        'Fasa 4 diluluskan melalui arahan memulakan Fasa 5');

$failed = 0;
foreach ($checks as $label => $passed) {
    echo ($passed ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
    $failed += $passed ? 0 : 1;
}
printf('RESULT checks=%d failed=%d' . PHP_EOL, count($checks), $failed);
exit($failed === 0 ? 0 : 1);
