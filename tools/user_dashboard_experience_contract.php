<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$page = (string) file_get_contents($root . '/page/dashboard.php');
$api = (string) file_get_contents($root . '/lib/q_func.php');
$guard = (string) file_get_contents($root . '/lib/request_security.php');
$database = (string) file_get_contents($root . '/lib/Database.php');
$recentMigration = (string) file_get_contents($root . '/docs/migrations/20261009_user_app_recent_up.sql');
$service = (string) file_get_contents($root . '/app/Monitoring/UserDownstreamHealthService.php');
$en = require $root . '/config/locales/en.php';
$ms = require $root . '/config/locales/ms.php';

$checks = [
    'recently used list is capped and intersects the current ACL-rendered directory' => str_contains($page, 'slice(0,6)') && str_contains($database, 'ORDER BY R.last_used_at DESC,R.sp_id DESC LIMIT 6') && str_contains($database, 'acl_blacklist'),
    'recently used is an icon-triggered context menu rather than a category tab' => str_contains($page, 'id="user_app_recent_trigger"') && str_contains($page, 'aria-controls="user_app_recent"') && str_contains($page, '.user-app-recent { position:absolute') && !str_contains($page, 'href="#user_app_recent_tab"'),
    'recent context menu supports explicit, outside-click and Escape dismissal' => str_contains($page, 'data-recent-close') && str_contains($page, "closest('#user_app_recent, #user_app_recent_trigger')") && str_contains($page, "event.key === 'Escape'"),
    'recent context menu clears server history and has no inner list scroll' => str_contains($page, 'data-recent-clear') && str_contains($page, "data:{user_clear_app_recent:''}") && str_contains($api, 'clearUserAppRecent') && !str_contains($page, '.user-app-recent__grid { display:grid; grid-template-columns:1fr; gap:6px; max-height'),
    'recent history is durable per account and renders its server timestamp' => str_contains($recentMigration, 'PRIMARY KEY (u_id, sp_id)') && str_contains($database, 'rememberUserAppRecent') && str_contains($page, 'application.recently_used_at') && str_contains($page, 'userAppRecentTimestamp') && !str_contains($page, 'oneid.recent-apps.v1'),
    'application loading uses five stable skeleton rows' => str_contains($page, '$skeletonIndex < 5') && str_contains($page, 'user-app-skeleton__row') && str_contains($page, 'oneid-skeleton-shimmer'),
    'skeleton animation respects reduced motion' => str_contains($page, '@media (prefers-reduced-motion: reduce)') && str_contains($page, '.user-app-skeleton__action::after { animation:none; }'),
    'smart search covers name function compact name and acronym' => str_contains($page, 'function userAppSearchFields') && str_contains($page, 'fields.description.indexOf(query)') && str_contains($page, 'fields.compact.indexOf(compactQuery)') && str_contains($page, 'fields.acronym.indexOf(compactQuery)'),
    'smart search provides ranked accessible suggestions' => str_contains($page, 'function userAppSearchScore') && str_contains($page, 'role="listbox"') && str_contains($page, 'role="option"') && str_contains($page, "event.key === 'ArrowDown'"),
    'application search persists only within browser session' => str_contains($page, "sessionStorage.setItem(userAppSearchStorageKey") && str_contains($page, "sessionStorage.getItem(userAppSearchStorageKey") && !str_contains($page, 'localStorage.setItem(userAppSearchStorageKey'),
    'empty search offers useful application choices' => str_contains($page, 'function userAppNoResults') && str_contains($page, 'dashboardI18n.noResultsHelp') && str_contains($page, 'user-app-search-empty__choices'),
    'recent item does not inherit the large primary access-button treatment' => str_contains($page, 'data-recent-open') && !str_contains($page, 'class="user-app-recent__item user-app-open"'),
    'recent app is recorded only after server allows access' => str_contains($api, "(int)(\$result['status']??0)===1") && str_contains($api, 'rememberUserAppRecent') && str_contains($page, 'rememberUserApp(sp_id, response.recently_used_at)'),
    'recent endpoints are authenticated and clear is CSRF guarded' => str_contains($guard, "'user_clear_app_recent'") && str_contains($api, "\$_SESSION['login_user']") && str_contains($api, 'supportsUserAppRecent'),
    'downstream status endpoint is authenticated and CSRF guarded' => str_contains($guard, "'user_downstream_status'"),
    'endpoint derives URLs from effective user ACL' => str_contains($api, 'check_specific_sp_allowed($operation, $id)') && !str_contains($page, 'sp_domain'),
    'health probes are capped and cached' => str_contains($service, 'array_slice($applications, 0, 12)') && str_contains($service, 'cacheTtlSeconds = 120'),
    'health probe accepts only HTTP URLs and verifies TLS' => str_contains($service, "['http', 'https']") && str_contains($service, 'CURLOPT_SSL_VERIFYPEER => true'),
    'downstream status includes a visible last-checked time' => str_contains($service, "'checked_at' => gmdate(DATE_ATOM)") && str_contains($page, 'dashboardI18n.statusChecked') && str_contains($page, 'user-app-health') && str_contains($page, '<em>'),
    'maintenance and unavailable applications block access' => str_contains($page, "state === 'maintenance' || state === 'unavailable'") && str_contains($page, 'disabled aria-disabled="true"') && str_contains($page, "if (this.disabled || \$(this).attr('aria-disabled') === 'true') return;"),
    'visible downstream checks continue safely in capped batches' => str_contains($page, 'window.setTimeout(refreshVisibleDownstreamStatus, 0)') && str_contains($service, 'array_slice($applications, 0, 12)'),
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
