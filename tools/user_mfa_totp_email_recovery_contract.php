<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$migration = (string) file_get_contents($root . '/docs/migrations/20260731_user_mfa_totp_email_recovery_up.sql');
$service = (string) file_get_contents($root . '/app/Auth/UserMfa/UserMfaTotpEmailRecoveryService.php');
$sender = (string) file_get_contents($root . '/app/Auth/UserMfa/UserMfaRecoveryEmailSender.php');
$api = (string) file_get_contents($root . '/lib/q_func.php');
$guard = (string) file_get_contents($root . '/lib/request_security.php');
$boundary = (string) file_get_contents($root . '/app/Auth/UserMfa/UserMfaHttpBoundary.php');
$page = (string) file_get_contents($root . '/page/user_mfa_security.php');
$en = require $root . '/config/locales/en.php';
$ms = require $root . '/config/locales/ms.php';

$checks = [];
$checks['purpose_isolated_schema'] = str_contains($migration, "purpose='TOTP_RECOVERY'")
    && str_contains($migration, 'session_binding_hash')
    && str_contains($migration, 'browser_digest');
$checks['password_required'] = str_contains($service, 'oneid_password_verify')
    && str_contains($service, 'USER_MFA_RECOVERY_PASSWORD_INVALID');
$checks['otp_hashed_one_use'] = str_contains($service, 'UserMfaOtp::hash')
    && str_contains($service, 'UserMfaOtp::verify')
    && str_contains($service, 'consumed_at=NOW(6)')
    && str_contains($service, "otp_hash=\\'\\'");
$checks['bounded_delivery'] = str_contains($service, 'TTL_SECONDS = 300')
    && str_contains($service, 'MAX_ATTEMPTS = 5')
    && str_contains($service, 'COOLDOWN_SECONDS = 60')
    && str_contains($service, 'HOURLY_USER_LIMIT = 10');
$checks['password_bruteforce_limited'] = str_contains($service, 'INTERVAL 15 MINUTE')
    && str_contains($service, 'RECOVERY_PASSWORD_INVALID%')
    && str_contains($service, '>= 5');
$checks['atomic_revoke'] = str_contains($service, "factor_status='REVOKED'")
    && str_contains($service, "preferred_factor='EMAIL_OTP'")
    && str_contains($service, "transaction_status='REVOKED'")
    && str_contains($service, 'UPDATE token_tbl SET status=0')
    && str_contains($service, 'beginTransaction()')
    && str_contains($service, 'commit()');
$checks['audit_and_notice'] = str_contains($service, 'EMAIL_OTP_SELF_RECOVERY')
    && str_contains($service, 'INSERT INTO syslog')
    && str_contains($sender, 'sendRevokedNotice');
$checks['routes_guarded'] = str_contains($guard, "'user_mfa_totp_recovery_email_request'")
    && str_contains($guard, "'user_mfa_totp_recovery_email_verify'")
    && str_contains($boundary, "'user_mfa_totp_recovery_email_request'")
    && str_contains($api, 'UserMfaTotpEmailRecoveryService');
$checks['two_method_ui'] = str_contains($page, "post('user_mfa_totp_revoke'")
    && str_contains($page, "post('user_mfa_totp_recovery_email_request'")
    && str_contains($page, "post('user_mfa_totp_recovery_email_verify'")
    && str_contains($page, 'autocomplete="current-password"');
$checks['bilingual'] = isset(
    $en['user_mfa.security.revoke_with_email'],
    $ms['user_mfa.security.revoke_with_email'],
    $en['email.user_mfa_recovery.subject'],
    $ms['email.user_mfa_recovery.subject'],
    $en['email.user_mfa_revoked.notice'],
    $ms['email.user_mfa_revoked.notice']
);

$failed = array_keys(array_filter($checks, static fn(bool $value): bool => !$value));
printf(
    "USER_MFA_TOTP_EMAIL_RECOVERY checks=%d passed=%d\n",
    count($checks),
    count($checks) - count($failed)
);
if ($failed !== []) {
    fwrite(STDERR, 'FAIL ' . implode(',', $failed) . PHP_EOL);
    exit(1);
}
echo "PASS PASSWORD_PURPOSE_BOUND_OTP_ATOMIC_REVOKE_NOTICE\n";
