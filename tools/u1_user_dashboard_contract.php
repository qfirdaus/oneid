<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$files = [
    'dashboard' => file_get_contents($root . '/page/dashboard.php'),
    'database' => file_get_contents($root . '/lib/Database.php'),
    'endpoint' => file_get_contents($root . '/lib/q_func.php'),
    'guard' => file_get_contents($root . '/lib/request_security.php'),
    'migration' => file_get_contents($root . '/docs/migrations/U1_USER_APP_FAVOURITES_UP.sql'),
];

$checks = [
    'global application search is rendered' => strpos($files['dashboard'], 'id="user_app_search"') !== false,
    'favourite tab is first-class and icon based' => strpos($files['dashboard'], 'user_app_favourites_tab') !== false
        && strpos($files['dashboard'], 'is-favourite-tab') !== false,
    'favourite button is accessible and stateful' => strpos($files['dashboard'], 'aria-pressed=') !== false
        && strpos($files['dashboard'], 'user_set_app_favourite') !== false,
    'NON SSO receives distinct tab styling' => strpos($files['dashboard'], 'is-non-sso-tab') !== false,
    'SSO and direct access labels are explicit' => strpos($files['dashboard'], "isDirect ? 'Akses' : 'Login'") !== false
        && strpos($files['dashboard'], "isDirect ? 'Akses terus' : 'OneID SSO'") !== false,
    'application summary separates total SSO and non SSO' => strpos($files['dashboard'], 'id="user_app_count"') !== false
        && strpos($files['dashboard'], 'id="user_app_sso_count"') !== false
        && strpos($files['dashboard'], 'id="user_app_non_sso_count"') !== false
        && strpos($files['dashboard'], "String(application.sp_sso_support) === '0'") !== false,
    'favourite endpoint is authenticated user action' => strpos($files['guard'], "'user_set_app_favourite'") !== false,
    'server derives favourite owner from session' => strpos($files['endpoint'], "\$_SESSION['login_user']") !== false
        && strpos($files['endpoint'], "isset( \$_POST['user_set_app_favourite'])") !== false,
    'favourite enable requires effective ACL' => strpos($files['endpoint'], 'userHasEffectiveAppAccess') !== false,
    'preference table does not grant ACL' => strpos($files['migration'], 'CREATE TABLE IF NOT EXISTS user_app_favourite') !== false
        && strpos($files['migration'], 'FOREIGN KEY') === false,
    'dashboard list includes persisted state' => strpos($files['endpoint'], "['is_favourite']") !== false,
    'database supports pre-migration compatibility' => strpos($files['database'], 'supportsUserAppFavourites') !== false,
];

$failed = 0;
foreach ($checks as $label => $passed) {
    echo ($passed ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
    if (!$passed) {
        $failed++;
    }
}

echo 'RESULT checks=' . count($checks) . ' failed=' . $failed . PHP_EOL;
exit($failed === 0 ? 0 : 1);
