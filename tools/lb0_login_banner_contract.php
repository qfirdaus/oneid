<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$index = (string) file_get_contents($root . '/index.php');
$plan = (string) file_get_contents($root . '/docs/AUDIT_DAN_PELAN_IMPLEMENTASI_PENGURUSAN_BANNER_LOGIN.md');
$baseline = (string) file_get_contents($root . '/docs/LB0_BASELINE_DAN_CONTRACT_PENGURUSAN_BANNER_LOGIN.md');
$decisions = (string) file_get_contents($root . '/docs/LB0_LOGIN_BANNER_DECISION_REGISTER.tsv');
$callers = (string) file_get_contents($root . '/docs/LB0_LOGIN_BANNER_CALLER_MAP.tsv');

$checks = 0;
$failures = 0;
$report = static function (bool $passed, string $description) use (&$checks, &$failures): void {
    $checks++;
    echo ($passed ? 'PASS ' : 'FAIL ') . $description . PHP_EOL;
    if (!$passed) {
        $failures++;
    }
};

$report(
    str_contains($index, 'assetsM/images/banner6.png')
    && str_contains($index, 'assetsM/images/banner7.png')
    && str_contains($index, "\$bannerIndex === 0 ? ' active' : ''"),
    'two static fallback banners retain deterministic first-item activation'
);

$validImages = true;
for ($number = 3; $number <= 7; $number++) {
    $path = $root . '/public/assetsM/images/banner' . $number . '.png';
    $info = is_file($path) ? @getimagesize($path) : false;
    $validImages = $validImages
        && is_array($info)
        && (int) ($info[0] ?? 0) === 3780
        && (int) ($info[1] ?? 0) === 1890
        && (string) ($info['mime'] ?? '') === 'image/png';
}
$report($validImages, 'five tracked baseline banners are readable 2:1 PNG images');

$decisionLines = preg_split('/\R/', trim($decisions)) ?: [];
$confirmedDecisions = count(array_filter(
    array_slice($decisionLines, 1),
    static fn(string $line): bool => str_contains($line, "\tCONFIRMED\t")
));
$report(
    count($decisionLines) === 13 && $confirmedDecisions === 12,
    'all twelve owner decisions are confirmed'
);

$callerLines = preg_split('/\R/', trim($callers)) ?: [];
$report(
    count($callerLines) === 25
    && str_contains($callers, 'LB0-C01')
    && str_contains($callers, 'LB0-C24'),
    'caller map covers twenty-four deterministic banner surfaces'
);

$report(
    str_contains($plan, 'Pilihan C — Metadata database dan aset persistent khusus environment')
    && str_contains($plan, 'LB0 -> LB1 -> LB2 -> LB3 -> LB4 -> LB5 -> LB6 ->')
    && str_contains($plan, 'LB0-LB8 TOOLING LOCAL PASS / DATABASE UNCHANGED / STAGING')
    && str_contains($plan, 'ACTIVATION NO-GO UNTIL BACKUP, RESTORE, UAT AND OWNER EVIDENCE PASS.'),
    'canonical plan records architecture sequence and authorization boundary'
);

$report(
    str_contains($baseline, 'MUTATION runtime/schema: Tiada') === false
    && str_contains($baseline, 'ZERO MUTATION')
    && str_contains($baseline, 'Static banner6/banner7 kekal fallback')
    && str_contains($baseline, 'tidak cross-fallback'),
    'LB0 baseline locks zero-mutation static and environment fallback contracts'
);

$requestSecurity = (string) file_get_contents($root . '/lib/request_security.php');
$adminDashboard = (string) file_get_contents($root . '/admin/dashboard.php');
$report(
    str_contains($index, 'PdoLoginBannerPersistence')
    && !str_contains($adminDashboard, 'PdoLoginBannerPersistence')
    && str_contains($requestSecurity, 'admin_login_banner_list'),
    'LB6 reader is isolated while admin UI remains free of direct persistence access'
);

$report(
    str_contains($index, 'assetsM/images/banner6.png')
    && str_contains($index, 'assetsM/images/banner7.png'),
    'static banner fallback remains available after later dormant work'
);

echo "RESULT checks={$checks} failures={$failures}\n";
exit($failures === 0 ? 0 : 1);
