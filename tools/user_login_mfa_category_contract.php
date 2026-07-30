<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

$root = dirname(__DIR__);
$checks = [
    'schema defaults STAFF and STUDENT to enabled without changing current enforcement' => [
        'docs/migrations/20260730_user_login_mfa_category_policy_up.sql',
        ["('STAFF',1,1)", "('STUDENT',1,1)", 'user_login_mfa_category_policy_history'],
    ],
    'classification uses active authoritative staff and student provenance' => [
        'app/Auth/UserMfa/PdoUserMfaPolicyReader.php',
        ['source_active=1', "source_family IN ('staff','student')", 'return true;'],
    ],
    'primary login decision applies category policy to pilot and enforced modes' => [
        'app/Auth/UserMfa/UserMfaPrimaryAuthDecision.php',
        ['categoryEnforced', 'pilotEligible'],
    ],
    'pending login coordinator preserves compatibility and enforces category scope' => [
        'app/Auth/UserMfa/UserMfaPendingLoginCoordinator.php',
        ['bool $categoryEnforced = true', '$categoryEnforced'],
    ],
    'admin mutation is versioned transactional and audit atomic' => [
        'app/Admin/UserMfaCategoryPolicyService.php',
        ['FOR UPDATE', 'configuration_version', 'beginTransaction', 'user_login_mfa_category_policy_history', 'INSERT INTO syslog', 'rollBack'],
    ],
    'admin mutation requires typed confirmation and security configuration Step-Up' => [
        'lib/request_security.php',
        ['admin_update_user_mfa_category_policy', 'SECURITY_CONFIGURATION_CHANGE'],
    ],
    'category UI has independent CSRF loader timeout guides and Step-Up resume' => [
        'public/assetsM/js/user-2fa-category-policy.js',
        ['X-CSRF-Token', 'AbortController', '15000', 'fillUser2faCategoryReference', 'fillUser2faCategoryConfirmation', 'sessionStorage', 'STEP_UP_REQUIRED'],
    ],
    'bilingual category controls and approval gates are documented' => [
        'docs/USER_LOGIN_MFA_ADMIN_CONTROL_AUDIT_DAN_PELAKSANAAN.md',
        ['Prioriti 2', 'STAFF', 'STUDENT', 'Prioriti 3'],
    ],
];

$failures = 0;
foreach ($checks as $label => [$relative, $needles]) {
    $content = file_get_contents($root . '/' . $relative);
    $pass = is_string($content);
    foreach ($needles as $needle) {
        $pass = $pass && str_contains((string) $content, $needle);
    }
    echo ($pass ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
    $failures += $pass ? 0 : 1;
}

foreach (['config/locales/ms.php', 'config/locales/en.php'] as $locale) {
    $content = (string) file_get_contents($root . '/' . $locale);
    $pass = str_contains($content, 'admin.configuration.user_2fa_category_title')
        && str_contains($content, 'admin.configuration.user_2fa_category_warning');
    echo ($pass ? 'PASS ' : 'FAIL ') . basename($locale) . ' category labels' . PHP_EOL;
    $failures += $pass ? 0 : 1;
}

printf(
    "RESULT checks=%d failures=%d database_mutations=0\n",
    count($checks) + 2,
    $failures
);
exit($failures === 0 ? 0 : 1);
