<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$officialName = 'Pusat Teknologi Maklumat dan Komunikasi';
$malay = require $root . '/config/locales/ms.php';
$english = require $root . '/config/locales/en.php';
$checks = [];

foreach (['ms' => $malay, 'en' => $english] as $locale => $catalogue) {
    $checks["{$locale} login uses the official organisation name"] =
        ($catalogue['login.contact.department'] ?? '')
        === $officialName . ', Universiti Pertahanan Nasional Malaysia (UPNM)';
    $checks["{$locale} MFA footer uses the official organisation name"] =
        ($catalogue['user_mfa.security.department'] ?? '') === $officialName;
    $checks["{$locale} application failure guidance uses PTMK"] =
        str_contains((string) ($catalogue['dashboard.apps.load_failed_help'] ?? ''), 'PTMK');
}

$activeFiles = [
    'config/locales/ms.php',
    'config/locales/en.php',
    'resources/views/maintenance.php',
    'page/admin_step_up.php',
    'app/Mail/OneIdEmailTemplate.php',
];
$forbidden = [
    '/\bBTMK\b/i',
    '/Bahagian Teknologi Maklumat/i',
    '/Pusat Teknologi Maklumat\s*&(?:amp;)?\s*Komunikasi/i',
    '/Information and Communication Technology Division/i',
    '/Centre for Information Technology\s*&\s*Communication/i',
];
foreach ($activeFiles as $file) {
    $contents = (string) file_get_contents($root . '/' . $file);
    $clean = true;
    foreach ($forbidden as $pattern) {
        if (preg_match($pattern, $contents) === 1) {
            $clean = false;
            break;
        }
    }
    $checks["{$file} contains no superseded organisation identity"] = $clean;
}

$failures = 0;
foreach ($checks as $label => $passed) {
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $label);
    $failures += $passed ? 0 : 1;
}
printf("RESULT checks=%d failures=%d\n", count($checks), $failures);
exit($failures === 0 ? 0 : 1);
