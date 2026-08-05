<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$checks = 0;
$failed = 0;
$report = static function (bool $ok, string $label) use (&$checks, &$failed): void {
    $checks++;
    echo ($ok ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
    if (!$ok) {
        $failed++;
    }
};

$page = file_get_contents($root . '/page/admin_step_up.php') ?: '';
$returnContext = file_get_contents($root . '/app/Auth/AdminStepUpReturnContext.php') ?: '';
$api = file_get_contents($root . '/lib/q_func.php') ?: '';
$requestSecurity = file_get_contents($root . '/lib/request_security.php') ?: '';
$sender = file_get_contents($root . '/app/Auth/AdminStepUpPhpMailerSender.php') ?: '';
$bootstrap = file_get_contents($root . '/app/Auth/Admin2faBootstrapService.php') ?: '';
$ms = require $root . '/config/locales/ms.php';
$en = require $root . '/config/locales/en.php';
$stepupKeys = array_values(array_filter(
    array_keys($ms),
    static fn(string $key): bool => str_starts_with($key, 'stepup.')
));

$report(array_keys($ms) === array_keys($en), 'BM and English catalogue ordered parity');
$report(count($stepupKeys) >= 90, 'Admin Step-Up catalogue covers challenge and factor lifecycle');
$report(
    str_contains($page, "\$h=static fn(string \$key):string=>htmlspecialchars(oneid_translate(\$key)")
    && str_contains($page, "\$h('stepup.page_title')")
    && str_contains($page, 'const stepupText=')
    && str_contains($page, 'technicalError(e'),
    'static and JavaScript presentation use locale catalogue'
);
$report(
    str_contains($page, "'ADMIN_ACCESS'=>oneid_translate('stepup.purpose.admin')")
    && str_contains($page, "'SECURITY_CONFIGURATION_CHANGE'=>oneid_translate('stepup.purpose.security')")
    && str_contains($page, "'ACTIVE_SESSION_REVOCATION'=>oneid_translate('stepup.purpose.session')"),
    'purpose labels are localized without changing canonical purpose codes'
);
$report(
    str_contains($page, "post('admin_step_up_request_email',{purpose})")
    && str_contains($page, "post('admin_step_up_verify_email',{purpose,challenge_id:challengeId.value,code:emailCode.value})")
    && str_contains($page, "post('admin_step_up_verify_totp',{purpose,code:totpCode.value})"),
    'factor verification payload remains canonical'
);
$report(
    str_contains($page, "typed_confirmation:document.getElementById('typed').value")
    && str_contains($bootstrap, "\$typed!=='ENABLE ADMIN 2FA'")
    && str_contains($bootstrap, "'BOOTSTRAP_TYPED_CONFIRMATION_INVALID'"),
    'bootstrap exact confirmation remains canonical'
);
$report(
    str_contains($page, 'AdminStepUpReturnContext::redirectUrl')
    && str_contains($returnContext, "'configuration_admin_2fa'")
    && str_contains($returnContext, "return \$context === '' ? \\APP_URL.'/admin/dashboard'"),
    'return target remains server allowlisted'
);
$report(
    str_contains($api, 'oneid_complete_step_up_rotation')
    && str_contains($requestSecurity, 'admin_step_up_rebind_grant'),
    'grant rotation and purpose enforcement remain unchanged'
);
$report(
    str_contains($sender, "oneid_translate('email.admin.subject')")
    && str_contains($sender, 'oneid_current_locale()'),
    'Step-Up OTP e-mail preserves current locale'
);
$report(
    str_contains($page, "if(code)message+=' ['+code+']'")
    && str_contains($page, "stepupText.technical_reference+': '+reference"),
    'canonical error and correlation identifiers remain visible'
);

echo "RESULT checks={$checks} failed={$failed}" . PHP_EOL;
exit($failed === 0 ? 0 : 1);
