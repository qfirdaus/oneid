<?php

$root = dirname(__DIR__);
$dashboard = file_get_contents($root . '/admin/dashboard.php');
$qFunc = file_get_contents($root . '/lib/q_func.php');
$database = file_get_contents($root . '/lib/Database.php');
$requestSecurity = file_get_contents($root . '/lib/request_security.php');
$sessionSecurity = file_get_contents($root . '/lib/session_security.php');
$api = file_get_contents($root . '/api.php');
$configurationService = file_get_contents($root . '/app/Admin/SsoConfigurationService.php');

$failures = 0;
$check = static function (bool $condition, string $description) use (&$failures): void {
    if ($condition) {
        echo "PASS: {$description}\n";
        return;
    }

    $failures++;
    echo "FAIL: {$description}\n";
};

$check(
    $dashboard !== false
        && preg_match_all('/<option value="(?:0\.5|1|2|12|24|48|72|168)">/', $dashboard) === 8,
    'UI exposes the eight characterized token timeout choices'
);
$check(
    $requestSecurity !== false
        && str_contains($requestSecurity, "'admin_get_sso_settings'")
        && str_contains($requestSecurity, "'update_configuration'")
        && str_contains($requestSecurity, 'oneid_require_csrf();')
        && str_contains($requestSecurity, 'Administrator access required'),
    'configuration actions remain protected by CSRF and admin authorization'
);
$check(
    $qFunc !== false
        && str_contains($qFunc, "if(isset( \$_POST['admin_get_sso_settings']))")
        && str_contains($qFunc, "if(isset( \$_POST['update_configuration']))")
        && $configurationService !== false
        && str_contains($configurationService, "'token_timeout'")
        && str_contains($configurationService, "'sso_settings_multi_session'")
        && !str_contains($configurationService, "'sso_settings_OTP_email'"),
    'SSO service is now limited to the two SSO policy settings'
);
$check(
    $database !== false
        && str_contains($database, 'UPDATE sys_config SET token_timeout=:token_timeout')
        && str_contains($database, 'configuration_version=:expected_version'),
    'configuration update is targeted to the singleton row'
);
$check(
    $qFunc !== false
        && str_contains($qFunc, 'if($sys_config_multisession == 0)')
        && str_contains($qFunc, 'update_whole_token_status'),
    'multiple-session policy is enforced during successful login'
);
$check(
    $qFunc !== false
        && str_contains($qFunc, "if(isset( \$_POST['action_forgot_password']))")
        && str_contains($qFunc, '(int)$passwordResetEmailEnabled===1')
        && str_contains($qFunc, 'OTP_EMAIL_Sender'),
    'separate Password Recovery policy controls Forgot Password delivery'
);
$check(
    $sessionSecurity !== false
        && str_contains($sessionSecurity, '> 1800')
        && str_contains($sessionSecurity, '> 28800'),
    'PHP session retains 30-minute idle and 8-hour absolute limits'
);
$check(
    $api !== false
        && str_contains($api, "get_system_config()['token_timeout']")
        && str_contains($api, 'SsoTokenLifetimePolicy::LEGACY_REFRESH'),
    'SSO API retains token lifetime enforcement and legacy refresh buffer'
);

exit($failures === 0 ? 0 : 1);
