<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Documentation/ApprovedReleaseCatalogue.php';

use OneId\App\Documentation\ApprovedReleaseCatalogue;

$root = dirname(__DIR__, 2);
$catalogue = new ApprovedReleaseCatalogue($root);
$preview = $catalogue->preview();
$bm = $catalogue->forLocale('ms');
$en = $catalogue->forLocale('en');
$checks = 0;
$failed = 0;
$report = static function (bool $ok, string $label) use (&$checks, &$failed): void {
    $checks++;
    echo ($ok ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
    if (!$ok) {
        $failed++;
    }
};
$report(
    $preview['release_count'] === 59
    && $preview['bm_item_count'] === 438
    && $preview['en_item_count'] === 438,
    'approved repository exposes exact BM and English parity'
);
$report(
    array_column($bm['releases'], 'identity') === array_column($en['releases'], 'identity')
    && array_column($bm['releases'], 'version') === array_column($en['releases'], 'version')
    && array_column($bm['releases'], 'date') === array_column($en['releases'], 'date'),
    'release identity version and date remain invariant'
);
$report(
    $en['effective_locale'] === 'en'
    && $en['fallback_used'] === false
    && $bm['effective_locale'] === 'ms',
    'approved English catalogue resolves without silent fallback'
);
$report(
    $preview['approved_manifest_digest']
        === '3f1408e5bb9d6b94e46a87cd08ef2028115bdb8c64425252a7901cf12b35ba1c'
    && $preview['evidence_reference'] === 'ONEID-V2104-CHANGELOG-20260905-01',
    'catalogue is bound to exact owner approval'
);
$report(
    $preview['activation_authorized'] === true
    && $preview['live_wiring'] === true
    && $preview['can_publish_english_manual'] === false
    && $preview['automatic_approval'] === false
    && $preview['mutation_statements'] === 0,
    'repository is locally activated but remains zero mutation'
);
$admin = (string) file_get_contents($root . '/admin/dashboard.php');
$report(
    str_contains($admin, 'ApprovedReleaseCatalogue')
    && str_contains($admin, 'const canonicalReleaseNotes =')
    && str_contains($admin, 'const approvedReleaseNotes =')
    && str_contains($admin, 'release_catalogue_fallback_notice'),
    'Administrator release UI uses approved content with canonical BM fallback'
);

echo "RESULT checks={$checks} failed={$failed}\n";
exit($failed === 0 ? 0 : 1);
