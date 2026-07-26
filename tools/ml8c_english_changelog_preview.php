<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Documentation/Ml8cContentPreview.php';

use OneId\App\Documentation\Ml8cContentPreview;

$root = dirname(__DIR__);
$path = $root . '/storage/generated/ml8c_release_english_draft.json';
$blocking = [];
$draft = is_file($path)
    ? json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR)
    : [];
$canonical = (new Ml8cContentPreview($root))->canonicalReleaseContent();
$releaseCount = count($draft['releases'] ?? []);
$itemCount = 0;
$empty = 0;
$notApproved = 0;
$sourceMismatch = 0;
$htmlMismatch = 0;
$codeTokenMismatch = 0;
$identities = [];

foreach ($draft['releases'] ?? [] as $releaseIndex => $release) {
    $expectedRelease = $canonical[$releaseIndex] ?? null;
    if (
        $expectedRelease === null
        || ($release['identity'] ?? null) !== $expectedRelease['identity']
        || ($release['version'] ?? null) !== $expectedRelease['version']
        || ($release['date'] ?? null) !== $expectedRelease['date']
    ) {
        $sourceMismatch++;
    }
    foreach ($release['items'] ?? [] as $itemIndex => $item) {
        $itemCount++;
        $identity = (string) ($item['identity'] ?? '');
        $identities[] = $identity;
        $bm = (string) ($item['bm'] ?? '');
        $en = (string) ($item['en_draft'] ?? '');
        if ($expectedRelease === null || $bm !== ($expectedRelease['changes'][$itemIndex] ?? null)) {
            $sourceMismatch++;
        }
        if (trim($en) === '') {
            $empty++;
        }
        if (($item['review_status'] ?? null) !== 'APPROVED') {
            $notApproved++;
        }
        preg_match_all('~</?[a-z][^>]*>~i', $bm, $bmTags);
        preg_match_all('~</?[a-z][^>]*>~i', $en, $enTags);
        if ($bmTags[0] !== $enTags[0]) {
            $htmlMismatch++;
        }
        preg_match_all('~<code>(.*?)</code>~s', $bm, $bmCode);
        preg_match_all('~<code>(.*?)</code>~s', $en, $enCode);
        if ($bmCode[1] !== $enCode[1]) {
            $codeTokenMismatch++;
        }
    }
}

$digestPayload = $draft;
$storedDigest = (string) ($digestPayload['manifest_digest'] ?? '');
unset($digestPayload['manifest_digest']);
$computedDigest = hash(
    'sha256',
    json_encode($digestPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
);
if ($releaseCount !== 39 || $itemCount !== 234) {
    $blocking[] = 'ML8C_ENGLISH_DRAFT_COUNT_MISMATCH';
}
if (count($identities) !== count(array_unique($identities))) {
    $blocking[] = 'ML8C_ENGLISH_DRAFT_DUPLICATE_IDENTITY';
}
if ($sourceMismatch !== 0) {
    $blocking[] = 'ML8C_ENGLISH_DRAFT_SOURCE_MISMATCH';
}
if ($empty !== 0) {
    $blocking[] = 'ML8C_ENGLISH_DRAFT_EMPTY_ITEM';
}
if ($notApproved !== 0) {
    $blocking[] = 'ML8C_ENGLISH_DRAFT_APPROVAL_BOUNDARY';
}
if ($htmlMismatch !== 0 || $codeTokenMismatch !== 0) {
    $blocking[] = 'ML8C_ENGLISH_DRAFT_INVARIANT_MISMATCH';
}
if ($storedDigest === '' || !hash_equals($storedDigest, $computedDigest)) {
    $blocking[] = 'ML8C_ENGLISH_DRAFT_DIGEST_MISMATCH';
}

$result = [
    'status' => $blocking === [] ? 1 : 0,
    'code' => $blocking === [] ? 'BILINGUAL_CHANGELOG_APPROVED' : 'BILINGUAL_CHANGELOG_BLOCKED',
    'mode' => 'approved_bilingual_changelog_validation',
    'can_apply' => true,
    'can_activate' => true,
    'automatic_approval' => false,
    'review_status' => 'APPROVED',
    'release_count' => $releaseCount,
    'item_count' => $itemCount,
    'empty_items' => $empty,
    'source_mismatches' => $sourceMismatch,
    'html_mismatches' => $htmlMismatch,
    'code_token_mismatches' => $codeTokenMismatch,
    'duplicate_identities' => count($identities) - count(array_unique($identities)),
    'blocking_codes' => $blocking,
    'manifest_digest' => $storedDigest,
    'mutation_statements' => 0,
];
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
exit($result['status'] === 1 ? 0 : 1);
