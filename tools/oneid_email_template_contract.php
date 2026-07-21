<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

$root = dirname(__DIR__);
require_once $root . '/app/Mail/OneIdEmailTemplate.php';

use OneId\App\Mail\OneIdEmailTemplate;

$checks = [];
$otp = OneIdEmailTemplate::otp(
    '<script>alert(1)</script>',
    'Account Recovery',
    'OTP KATA LALUAN',
    'Tetapkan semula kata laluan',
    'Gunakan kod berikut.',
    '123456'
);
$test = OneIdEmailTemplate::deliveryTest('OneID Administrator');
$adminSender = (string) file_get_contents($root . '/app/Auth/AdminStepUpPhpMailerSender.php');
$qFunc = (string) file_get_contents($root . '/lib/q_func.php');

$checks['brand_shell'] = str_contains($otp, 'OneID<span style="color:#a71930">@UPNM</span>')
    && str_contains($otp, 'Pusat Teknologi Maklumat &amp; Komunikasi, UPNM')
    && str_contains($otp, 'role="presentation"');
$checks['otp_content'] = str_contains($otp, '123456')
    && str_contains($otp, 'Sah selama 5 minit | Satu kali penggunaan')
    && !str_contains($otp, 'â€¢')
    && str_contains($otp, 'Jangan kongsikan kod ini');
$checks['escaped_user_content'] = !str_contains($otp, '<script>')
    && str_contains($otp, '&lt;script&gt;alert(1)&lt;/script&gt;');
$checks['email_client_safe'] = !preg_match('/<(?:script|iframe|form|video)\b/i', $otp)
    && !preg_match('/(?:src|href)=["\']https?:\/\//i', $otp);
$checks['delivery_test_no_fake_otp'] = str_contains($test, 'Ujian penghantaran berjaya')
    && !str_contains($test, 'Kod pengesahan sekali guna')
    && !str_contains($test, '>TEST<');
$checks['plain_text_fallback'] = str_contains(OneIdEmailTemplate::otpPlainText('Kod OneID', '654321'), '654321')
    && str_contains(OneIdEmailTemplate::deliveryTestPlainText(), 'Tiada tindakan diperlukan');
$checks['utf8_transport'] = substr_count($adminSender.$qFunc, "CharSet = 'UTF-8'")===2
    && substr_count($adminSender.$qFunc, "Encoding = 'base64'")===2;
$invalidRejected = false;
try {
    OneIdEmailTemplate::otp('User', 'Context', 'Badge', 'Title', 'Intro', 'ABC123');
} catch (InvalidArgumentException) {
    $invalidRejected = true;
}
$checks['invalid_otp_rejected'] = $invalidRejected;
$checks['all_active_senders_use_standard'] = str_contains($adminSender, 'OneIdEmailTemplate::otp(')
    && str_contains($qFunc, 'OneIdEmailTemplate::deliveryTest(')
    && str_contains($qFunc, 'OneIdEmailTemplate::otp(')
    && substr_count($adminSender . $qFunc, 'msgHTML(') === 2;

$failed = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));
printf("ONEID_EMAIL_TEMPLATE checks=%d passed=%d\n", count($checks), count($checks) - count($failed));
if ($failed !== []) {
    fwrite(STDERR, 'FAIL ' . implode(',', $failed) . "\n");
    exit(1);
}
echo "PASS ADMIN_2FA_PASSWORD_RECOVERY_TEST_EMAIL_STANDARDIZED\n";
