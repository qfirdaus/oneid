<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$source = require $root . '/config/content/release_changelog_plain.php';
if (!is_array($source) || $source === []) {
    throw new RuntimeException('Release changelog source is empty.');
}

$seen = [];
$releases = [];
foreach ($source as $releaseIndex => $release) {
    $version = (string) ($release['version'] ?? '');
    $date = (string) ($release['date'] ?? '');
    $bm = $release['bm'] ?? null;
    $en = $release['en'] ?? null;
    if (
        preg_match('/^\d+\.\d+\.\d+$/D', $version) !== 1
        || preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date) !== 1
        || isset($seen[$version])
        || !is_array($bm)
        || !is_array($en)
        || $bm === []
        || count($bm) !== count($en)
    ) {
        throw new RuntimeException('Invalid release entry at index ' . $releaseIndex);
    }
    $seen[$version] = true;
    $items = [];
    foreach ($bm as $itemIndex => $bmText) {
        $enText = $en[$itemIndex] ?? null;
        if (!is_string($bmText) || trim($bmText) === '' || !is_string($enText) || trim($enText) === '') {
            throw new RuntimeException('Invalid release item for ' . $version);
        }
        $items[] = [
            'identity' => sprintf('release:%s:item:%03d', $version, $itemIndex + 1),
            'bm' => trim($bmText),
            'en_draft' => trim($enText),
            'review_status' => 'APPROVED',
        ];
    }
    $releases[] = [
        'identity' => 'release:' . $version,
        'version' => $version,
        'date' => $date,
        'items' => $items,
    ];
}

$payload = [
    'schema' => 'oneid.release-catalogue.v2',
    'authorization' => 'ONEID-V284-CHANGELOG-20260809-01',
    'source_locale' => 'ms',
    'target_locale' => 'en',
    'approval_status' => 'APPROVED',
    'automatic_approval' => false,
    'releases' => $releases,
];
$payload['manifest_digest'] = hash(
    'sha256',
    json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
);

$json = json_encode(
    $payload,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
) . PHP_EOL;
$path = $root . '/storage/generated/ml8c_release_english_draft.json';
if (file_put_contents($path, $json, LOCK_EX) === false) {
    throw new RuntimeException('Unable to write release catalogue.');
}

printf(
    "BUILT releases=%d items=%d digest=%s\n",
    count($releases),
    array_sum(array_map(static fn (array $release): int => count($release['items']), $releases)),
    $payload['manifest_digest']
);
