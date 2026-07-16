<?php

$root = dirname(__DIR__);
$dashboard = file_get_contents($root . '/admin/dashboard.php');

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
        && str_contains($dashboard, 'Authentication &amp; SSO Token Policy')
        && str_contains($dashboard, 'Password Recovery dikawal secara berasingan'),
    'page title and scope accurately describe the policy editor'
);
$check(
    str_contains($dashboard, 'SSO token lifetime')
        && str_contains($dashboard, '30 minit tanpa aktiviti atau maksimum 8 jam'),
    'SSO token lifetime is distinguished from the PHP session policy'
);
$check(
    str_contains($dashboard, 'Allow multiple active SSO tokens')
        && str_contains($dashboard, 'login pengguna yang berikutnya'),
    'multiple-token copy explains current next-login enforcement'
);
$check(
    str_contains($dashboard, 'Send password-reset OTP by email')
        && str_contains($dashboard, 'bukan login MFA atau Admin Step-Up 2FA'),
    'password-reset delivery is not represented as SSO MFA'
);
$check(
    str_contains($dashboard, 'ssoConfigChangeSummary')
        && str_contains($dashboard, "title:'Confirm policy and impact'")
        && str_contains($dashboard, "confirmButtonText:'Save policy'"),
    'save flow presents a change summary and explicit confirmation'
);
$check(
    str_contains($dashboard, 'ssoConfigSaving')
        && str_contains($dashboard, "if (ssoConfigSaving || !ssoConfigOriginal)")
        && str_contains($dashboard, "$('#sso_config_save_button').prop('disabled'"),
    'loading state and double-submit guard are present'
);
$check(
    str_contains($dashboard, "swal('No changes'")
        && str_contains($dashboard, "swal('Policy saved'")
        && str_contains($dashboard, "swal('Policy not saved'")
        && str_contains($dashboard, 'SC2_RESPONSE_INVALID'),
    'saved, unchanged, request failure and rejected or invalid response are distinct'
);
$check(
    str_contains($dashboard, "Grace period: 15 minutes")
        && str_contains($dashboard, "response && response.code ? response.code"),
    'UI shows grace-period impact and does not treat blocked enforcement as success'
);

exit($failures === 0 ? 0 : 1);
