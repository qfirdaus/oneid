<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Documentation/Ml8cContentPreview.php';

use OneId\App\Documentation\Ml8cContentPreview;

$checks = 0;
$failed = 0;
$report = static function (bool $ok, string $label) use (&$checks, &$failed): void {
    $checks++;
    echo ($ok ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
    if (!$ok) {
        $failed++;
    }
};
$root = dirname(__DIR__, 2);
$previewer = new Ml8cContentPreview($root);
$result = $previewer->preview();
$manifest = $result['manifest'];
$versions = array_column($manifest['releases'], 'version');
$english = $previewer->releasesForLocale('en');

$report(
    $result['status'] === 1 && $result['blocking_codes'] === [],
    'ML8C inventory matches its authorized baseline'
);
$report(
    $manifest['active_release_entries'] === 38
    && $manifest['duplicate_release_identities'] === 0
    && $manifest['unresolved_release_identities'] === 0
    && count(array_unique($versions)) === 38,
    'all 38 active releases have stable unique identities'
);
$report(
    $manifest['release_english_approved'] === 0
    && $manifest['release_english_review_required'] === 38
    && count(array_filter(
        $manifest['releases'],
        static fn (array $release): bool => $release['english_status'] === 'REVIEW_REQUIRED'
    )) === 38,
    'English release drafts cannot be counted as approved content'
);
$report(
    $manifest['official_bm_manuals'] === 1
    && $manifest['approved_english_manuals'] === 0
    && $manifest['english_manual_draft_review_file'] === true
    && $result['manual_fallback_notice'] === Ml8cContentPreview::MANUAL_FALLBACK_NOTICE,
    'BM manual remains authoritative with explicit English fallback'
);
$report(
    count($manifest['policy_documents']) === 2
    && array_reduce(
        $manifest['policy_documents'],
        static fn (bool $carry, array $document): bool => $carry && $document['bilingual_sections_present'],
        true
    ),
    'both required policy documents contain BM and English sections'
);
$report(
    $english['fallback_used'] === true
    && $english['locale'] === 'ms'
    && str_contains((string) $english['notice'], 'under owner review')
    && count($english['releases']) === 38,
    'locale-aware release seam uses explicit fallback before owner approval'
);
$report(
    $result['can_apply'] === false
    && $result['can_publish_english_manual'] === false
    && $result['automatic_translation_approval'] === false
    && $result['mutation_statements'] === 0,
    'Preview exposes no Apply, publication or automatic approval capability'
);
$report(
    preg_match('/\A[a-f0-9]{64}\z/', $result['manifest_digest']) === 1,
    'Preview manifest has a deterministic review digest'
);

echo "RESULT checks={$checks} failed={$failed}\n";
exit($failed === 0 ? 0 : 1);
