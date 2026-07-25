<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/lib/locale.php';
require_once dirname(__DIR__, 2) . '/app/Mail/OneIdEmailTemplate.php';

$checks = 0;
$failed = 0;
$report = static function (bool $condition, string $label) use (&$checks, &$failed): void {
    $checks++;
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
    if (!$condition) {
        $failed++;
    }
};

$ms = require dirname(__DIR__, 2) . '/config/locales/ms.php';
$en = require dirname(__DIR__, 2) . '/config/locales/en.php';
$report(array_keys($ms) === array_keys($en), 'BM and English catalogue keys have exact ordered parity');
$report(count($ms) >= 60, 'Pilot catalogue contains expected coverage');

$otp = '123456';
$msEmail = \OneId\App\Mail\OneIdEmailTemplate::otp(
    'Pengguna Ujian',
    $ms['email.recovery.context'],
    $ms['email.recovery.badge'],
    $ms['email.recovery.headline'],
    $ms['email.recovery.intro'],
    $otp,
    null,
    'ms'
);
$enEmail = \OneId\App\Mail\OneIdEmailTemplate::otp(
    'Test User',
    $en['email.recovery.context'],
    $en['email.recovery.badge'],
    $en['email.recovery.headline'],
    $en['email.recovery.intro'],
    $otp,
    null,
    'en'
);
$report(str_contains($msEmail, '<html lang="ms">') && str_contains($msEmail, 'Tetapkan semula'), 'BM recovery email uses BM language contract');
$report(str_contains($enEmail, '<html lang="en">') && str_contains($enEmail, 'Reset your password'), 'English recovery email uses English language contract');
$report(substr_count($msEmail, $otp) === 1 && substr_count($enEmail, $otp) === 1, 'OTP remains canonical and appears once in each email');
$report(!str_contains($enEmail, 'Jangan kongsikan') && str_contains($enEmail, 'Do not share'), 'English security warning does not leak BM copy');
$report(
    \OneId\App\Mail\OneIdEmailTemplate::otpPlainText('Reset your password', $otp, 'en')
        !== \OneId\App\Mail\OneIdEmailTemplate::otpPlainText('Tetapkan semula kata laluan', $otp, 'ms'),
    'plain-text email follows selected locale'
);

echo "RESULT checks={$checks} failed={$failed}\n";
exit($failed === 0 ? 0 : 1);
