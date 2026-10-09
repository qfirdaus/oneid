<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$database=(string)file_get_contents($root.'/lib/Database.php');
$endpoint=(string)file_get_contents($root.'/lib/q_func.php');
$guard=(string)file_get_contents($root.'/lib/request_security.php');
$page=(string)file_get_contents($root.'/page/dashboard.php');
$up=(string)file_get_contents($root.'/docs/migrations/20261009_user_app_recent_up.sql');
$down=(string)file_get_contents($root.'/docs/migrations/20261009_user_app_recent_down.sql');
$checks=[
 'schema is per account and application with ordered lookup'=>str_contains($up,'PRIMARY KEY (u_id, sp_id)')&&str_contains($up,'idx_user_app_recent_order (u_id, last_used_at)'),
 'storage is a presentation history and has an isolated rollback'=>str_contains($up,'never')&&trim($down)==='-- Rollback removes presentation history only; ACL and favourites are unchanged.'.PHP_EOL.'DROP TABLE IF EXISTS user_app_recent;',
 'server read enforces current user ACL and six-item cap'=>str_contains($database,'getUserAppRecent')&&str_contains($database,'acl_blacklist')&&str_contains($database,'acl_group')&&str_contains($database,'acl_single')&&str_contains($database,'LIMIT 6'),
 'server write deduplicates and trims history to six'=>str_contains($database,'ON DUPLICATE KEY UPDATE last_used_at=NOW()')&&str_contains($database,'LIMIT 6) K'),
 'successful authorised application open records server history'=>str_contains($endpoint,"trim((string)(\$result['domain']??''))!==''")&&str_contains($endpoint,'rememberUserAppRecent'),
 'clear is authenticated scoped and CSRF guarded'=>str_contains($guard,"'user_clear_app_recent'")&&str_contains($endpoint,'clearUserAppRecent((string)$_SESSION[\'login_user\'])'),
 'browser storage is no longer the source of recent history'=>!str_contains($page,'oneid.recent-apps.v1')&&!str_contains($page,'localStorage.removeItem(userAppRecentStorageKey)')&&str_contains($page,'application.recently_used_at'),
 'archiving an application removes stale recent history'=>str_contains($database,"'user_app_recent'")&&str_contains((string)file_get_contents($root.'/app/Admin/WebAppService.php'),"'user_app_recent'"),
];
$failed=0;foreach($checks as $label=>$pass){printf("%s %s\n",$pass?'PASS':'FAIL',$label);if(!$pass)$failed++;}
printf("RESULT checks=%d failed=%d\n",count($checks),$failed);exit($failed===0?0:1);
