<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$index = (string) file_get_contents($root . '/index.php');
$challenge = (string) file_get_contents($root . '/page/user_mfa_challenge.php');
$admin = (string) file_get_contents($root . '/page/admin_step_up.php');
$security = (string) file_get_contents($root . '/page/user_mfa_security.php');

$checks = [
    'password recovery OTP Enter submits the existing button' => str_contains($index, 'submitButton.click()'),
    'user login e-mail OTP and TOTP bind Enter' => str_contains($challenge, "bindCodeEnter(document.getElementById('emailCode'),verifyEmailButton)")
        && str_contains($challenge, "bindCodeEnter(document.getElementById('totpCode'),verifyTotpButton)"),
    'admin e-mail OTP TOTP and enrollment bind Enter' => str_contains($admin, 'bindCodeEnter(emailCode,emailVerify)')
        && str_contains($admin, 'bindCodeEnter(totpCode,totpVerify)')
        && str_contains($admin, "bindCodeEnter(confirmCode,document.getElementById('confirm'))"),
    'user enrollment revoke and recovery bind Enter' => str_contains($security, "bindCodeEnter(document.getElementById('confirmCode'),document.getElementById('confirmEnrollment'))")
        && str_contains($security, "bindCodeEnter(document.getElementById('revokeCode'),document.getElementById('revokeAuthenticator'))")
        && str_contains($security, "bindCodeEnter(document.getElementById('recoveryOtp'),document.getElementById('verifyRecoveryOtp'))"),
    'keyboard binding blocks composition repeat and duplicate disabled action' => substr_count($challenge . $admin . $security, "event.isComposing||event.repeat") === 3
        && substr_count($challenge . $admin . $security, 'if(!button.disabled)button.click()') === 3,
    'destructive actions keep their confirmation dialog' => str_contains($security, 'revoke_confirm')
        && str_contains($security, 'showCancelButton:true'),
];

$failures = 0;
foreach ($checks as $label => $passed) {
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $label);
    $failures += $passed ? 0 : 1;
}
printf("RESULT checks=%d failures=%d database_mutations=0\n", count($checks), $failures);
exit($failures === 0 ? 0 : 1);
