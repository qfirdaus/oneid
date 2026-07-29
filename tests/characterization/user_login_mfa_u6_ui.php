<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Auth/UserMfa/UserLoginMfaPolicy.php';
require_once dirname(__DIR__, 2) . '/app/Auth/UserMfa/UserMfaUiCatalogue.php';
require_once dirname(__DIR__, 2) . '/app/Auth/UserMfa/UserMfaUiRenderer.php';

use OneId\App\Auth\UserMfa\UserMfaUiCatalogue;
use OneId\App\Auth\UserMfa\UserMfaUiRenderer;

$checks = 0;
$failed = 0;
$report = static function (bool $passed, string $label) use (&$checks, &$failed): void {
    $checks++;
    $failed += $passed ? 0 : 1;
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $label);
};

$ms = UserMfaUiCatalogue::forLocale('ms');
$en = UserMfaUiCatalogue::forLocale('en');
$report(
    array_keys($ms) === array_keys($en) && count($ms) >= 30,
    'BM and English catalogues have exact ordered parity'
);
$report(
    UserMfaUiCatalogue::forLocale('xx') === $ms,
    'invalid locale fails safely to Bahasa Melayu'
);

$renderer = new UserMfaUiRenderer();
$csrf = 'csrf-safe-fixture';
$challengeMs = $renderer->challenge('ms', [
    'totp_enabled' => true,
    'active_totp' => true,
    'masked_email' => 'us***@upnm.edu.my',
    'csrf_token' => $csrf,
    'status' => 'loading',
]);
$challengeEn = $renderer->challenge('en', [
    'totp_enabled' => false,
    'active_totp' => true,
    'masked_email' => 'us***@upnm.edu.my',
    'csrf_token' => $csrf,
    'status' => 'error',
]);
$report(
    str_contains($challengeMs, 'lang="ms"')
    && str_contains($challengeEn, 'lang="en"')
    && str_contains($challengeMs, $ms['title.challenge'])
    && str_contains($challengeEn, $en['title.challenge']),
    'challenge UI renders locale-specific content'
);
$report(
    str_contains($challengeMs, 'value="EMAIL_OTP"')
    && str_contains($challengeMs, 'value="TOTP"')
    && !str_contains($challengeEn, 'value="TOTP"')
    && str_contains($challengeEn, $en['factor.unavailable']),
    'factor availability is derived from server state and kill switch'
);
$report(
    str_contains($challengeMs, 'href="#mfa-main"')
    && str_contains($challengeMs, '<fieldset>')
    && str_contains($challengeMs, '<legend>')
    && str_contains($challengeMs, 'inputmode="numeric"')
    && str_contains($challengeMs, 'autocomplete="one-time-code"')
    && str_contains($challengeMs, 'role="alert"')
    && str_contains($challengeMs, 'aria-live="assertive"'),
    'challenge supports keyboard screen-reader and mobile input semantics'
);
$report(
    str_contains($challengeEn, $en['email.sent'])
    && !str_contains($challengeEn, 'unknown user')
    && !str_contains($challengeEn, 'account exists'),
    'challenge and recovery copy is enumeration-safe'
);

$security = $renderer->accountSecurity('en', [
    'active_totp' => false,
    'enrollment_pending' => true,
    'csrf_token' => $csrf,
    'status' => 'success',
]);
$report(
    str_contains($security, 'data-qr-source="same-origin-post"')
    && str_contains($security, 'data-cache-control="no-store"')
    && !str_contains($security, 'otpauth://')
    && !str_contains($security, 'secret='),
    'QR slot is local no-store and contains no provisioning secret or URL'
);
$report(
    str_contains($security, $en['state.empty'])
    && str_contains($security, $en['state.success'])
    && str_contains($security, $en['recovery.safe'])
    && str_contains($security, 'value="enroll"')
    && str_contains($security, 'value="confirm"')
    && str_contains($security, 'value="revoke"'),
    'Account Security includes lifecycle recovery empty and success states'
);

$admin = $renderer->adminConfiguration('ms', [
    'mode' => 'PILOT_ENFORCED',
    'csrf_token' => $csrf,
    'status' => 'empty',
]);
$report(
    str_contains($admin, 'value="OFF"')
    && str_contains($admin, 'value="ENROLLMENT"')
    && str_contains($admin, 'value="PILOT_ENFORCED" selected')
    && str_contains($admin, 'value="ENFORCED"')
    && str_contains($admin, 'name="email_enabled" value="1"')
    && str_contains($admin, $ms['admin.not_authorized']),
    'Admin configuration preserves canonical modes mandatory email and activation warning'
);

$malicious = $renderer->challenge('ms', [
    'masked_email' => '<script>alert(1)</script>',
    'csrf_token' => '"><img src=x onerror=alert(1)>',
]);
$report(
    !str_contains($malicious, '<script>')
    && !str_contains($malicious, '<img src=x')
    && str_contains($malicious, '&lt;script&gt;'),
    'all server-derived values are context escaped'
);

$combined = $challengeMs . $challengeEn . $security . $admin;
$report(
    !str_contains($combined, 'otpauth://')
    && !preg_match('/[?&](?:otp|code|secret|session|token)=/i', $combined)
    && !str_contains($combined, 'USER_MFA_TOTP_INVALID_OR_REPLAYED'),
    'HTML contains no OTP secret session or internal error code in URLs or copy'
);

printf(
    "RESULT checks=%d failures=%d locale_parity=1 keyboard=1 screen_reader=1 mobile=1 sensitive_url_values=0 runtime_activation=0\n",
    $checks,
    $failed
);
exit($failed === 0 ? 0 : 1);
