<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$candidate = require $root . '/config/content/ml9a_v263_release_candidate.php';
$currentPath = $root . '/storage/generated/ml8c_release_english_draft.json';
$current = json_decode((string) file_get_contents($currentPath), true, 512, JSON_THROW_ON_ERROR);

if (
    ($candidate['version'] ?? null) !== '2.6.3'
    || ($candidate['date'] ?? null) !== '2026-07-26'
    || ($candidate['status'] ?? null) !== 'REVIEW_REQUIRED'
    || ($candidate['automatic_approval'] ?? true) !== false
    || count($candidate['bm'] ?? []) !== 12
    || count($candidate['en'] ?? []) !== 12
) {
    throw new RuntimeException('ML9A_RELEASE_CANDIDATE_INVALID');
}

$identity = 'release:' . $candidate['version'];
$items = [];
foreach ($candidate['bm'] as $position => $bm) {
    $items[] = [
        'identity' => sprintf('%s:item:%03d', $identity, $position + 1),
        'bm' => $bm,
        'en_draft' => $candidate['en'][$position],
        'review_status' => 'REVIEW_REQUIRED',
    ];
}

$projected = $current;
array_unshift($projected['releases'], [
    'identity' => $identity,
    'version' => $candidate['version'],
    'date' => $candidate['date'],
    'items' => $items,
]);
$projected['release_count'] = count($projected['releases']);
$projected['item_count'] = array_sum(array_map(
    static fn(array $release): int => count($release['items'] ?? []),
    $projected['releases']
));
unset($projected['manifest_digest']);
$projectedDigest = hash(
    'sha256',
    json_encode($projected, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
);

$candidateDigest = hash(
    'sha256',
    json_encode($candidate, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
);

echo json_encode([
    'mode' => 'ml9a_release_candidate_preview',
    'can_apply' => false,
    'automatic_approval' => false,
    'candidate_version' => $candidate['version'],
    'candidate_date' => $candidate['date'],
    'bm_items' => count($candidate['bm']),
    'en_items' => count($candidate['en']),
    'candidate_content_digest' => $candidateDigest,
    'current_release_count' => (int) $current['release_count'],
    'current_item_count' => (int) $current['item_count'],
    'projected_release_count' => $projected['release_count'],
    'projected_item_count' => $projected['item_count'],
    'projected_approved_catalogue_digest' => $projectedDigest,
    'blocking_codes' => [],
    'mutation_statements' => 0,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
