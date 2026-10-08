<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$page = (string) file_get_contents($root . '/page/dashboard.php');
$api = (string) file_get_contents($root . '/lib/q_func.php');
$guard = (string) file_get_contents($root . '/lib/request_security.php');
$service = (string) file_get_contents($root . '/app/Monitoring/UserDownstreamHealthService.php');
$en = require $root . '/config/locales/en.php';
$ms = require $root . '/config/locales/ms.php';

$checks = [
    'recently used list is capped and intersects the current ACL-rendered directory' => str_contains($page, "slice(0, 6)") && str_contains($page, "allApplications.find"),
    'recently used is an icon-triggered context menu rather than a category tab' => str_contains($page, 'id="user_app_recent_trigger"') && str_contains($page, 'aria-controls="user_app_recent"') && str_contains($page, '.user-app-recent { position:absolute') && !str_contains($page, 'href="#user_app_recent_tab"'),
    'recent context menu supports explicit, outside-click and Escape dismissal' => str_contains($page, 'data-recent-close') && str_contains($page, "closest('#user_app_recent, #user_app_recent_trigger')") && str_contains($page, "event.key === 'Escape'"),
    'recent context menu clears only its own browser history and has no inner list scroll' => str_contains($page, 'data-recent-clear') && str_contains($page, 'localStorage.removeItem(userAppRecentStorageKey)') && !str_contains($page, '.user-app-recent__grid { display:grid; grid-template-columns:1fr; gap:6px; max-height'),
    'recent history stores and renders a local last-used timestamp' => str_contains($page, 'entries.unshift({id:id,at:Date.now()})') && str_contains($page, 'userAppRecentTimestamp') && str_contains($page, 'dashboardI18n.lastUsed'),
    'recent item does not inherit the large primary access-button treatment' => str_contains($page, 'data-recent-open') && !str_contains($page, 'class="user-app-recent__item user-app-open"'),
    'recent app is recorded only after server allows access' => str_contains($page, 'rememberUserApp(sp_id)') && strpos($page, 'rememberUserApp(sp_id)') > strpos($page, 'Number(response.status) === 1'),
    'downstream status endpoint is authenticated and CSRF guarded' => str_contains($guard, "'user_downstream_status'"),
    'endpoint derives URLs from effective user ACL' => str_contains($api, 'check_specific_sp_allowed($operation, $id)') && !str_contains($page, 'sp_domain'),
    'health probes are capped and cached' => str_contains($service, 'array_slice($applications, 0, 12)') && str_contains($service, 'cacheTtlSeconds = 120'),
    'health probe accepts only HTTP URLs and verifies TLS' => str_contains($service, "['http', 'https']") && str_contains($service, 'CURLOPT_SSL_VERIFYPEER => true'),
    'browser receives no downstream URL' => str_contains($api, "'applications' => \$service->check(\$allowed)") && !str_contains($service, "'url' =>"),
    'desktop and mobile labels are bilingual' => ($en['dashboard.apps.recent'] ?? '') === 'Recently Used' && ($ms['dashboard.apps.recent'] ?? '') === 'Baru Digunakan',
];

$failed = 0;
foreach ($checks as $label => $passed) {
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $label);
    if (!$passed) $failed++;
}
printf("RESULT checks=%d failed=%d\n", count($checks), $failed);
exit($failed === 0 ? 0 : 1);
