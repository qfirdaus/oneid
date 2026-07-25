<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Documentation/Ml8cContentPreview.php';

use OneId\App\Documentation\Ml8cContentPreview;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(2);
}

$root = dirname(__DIR__);
$output = $root . '/storage/generated/ml8c_release_english_draft.json';
$previewer = new Ml8cContentPreview($root);
$releases = $previewer->canonicalReleaseContent();
$draft = [
    'schema' => 'oneid.ml8c.release-draft.v1',
    'authorization' => 'ONEID-ML8C-LOCAL-20260725-01',
    'source_locale' => 'ms',
    'target_locale' => 'en',
    'approval_status' => 'REVIEW_REQUIRED',
    'automatic_approval' => false,
    'releases' => [],
];

$translate = static function (string $text): string {
    $query = http_build_query([
        'client' => 'gtx',
        'sl' => 'ms',
        'tl' => 'en',
        'dt' => 't',
        'q' => $text,
    ]);
    $curl = curl_init('https://translate.googleapis.com/translate_a/single?' . $query);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT => 'OneID-ML8C-Draft/1.0',
    ]);
    $response = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $error = curl_error($curl);
    curl_close($curl);
    if (!is_string($response) || $status !== 200) {
        throw new RuntimeException('ML8C_DRAFT_TRANSLATION_FAILED: ' . $status . ' ' . $error);
    }
    $decoded = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
    $segments = $decoded[0] ?? [];
    $translated = '';
    foreach ($segments as $segment) {
        if (isset($segment[0]) && is_string($segment[0])) {
            $translated .= $segment[0];
        }
    }
    if (trim($translated) === '') {
        throw new RuntimeException('ML8C_DRAFT_TRANSLATION_EMPTY');
    }
    return $translated;
};

$total = 0;
foreach ($releases as $release) {
    $items = [];
    foreach ($release['changes'] as $position => $bm) {
        $identity = sprintf('%s:item:%03d', $release['identity'], $position + 1);
        $items[] = [
            'identity' => $identity,
            'bm' => $bm,
            'en_draft' => $translate($bm),
            'review_status' => 'REVIEW_REQUIRED',
        ];
        $total++;
        fwrite(STDERR, "\rDrafted {$total}/217");
        usleep(75000);
    }
    $draft['releases'][] = [
        'identity' => $release['identity'],
        'version' => $release['version'],
        'date' => $release['date'],
        'items' => $items,
    ];
}
$draft['release_count'] = count($draft['releases']);
$draft['item_count'] = $total;
$digestPayload = $draft;
unset($digestPayload['manifest_digest']);
$draft['manifest_digest'] = hash(
    'sha256',
    json_encode($digestPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
);

if (!is_dir(dirname($output)) && !mkdir(dirname($output), 0770, true) && !is_dir(dirname($output))) {
    throw new RuntimeException('ML8C_DRAFT_OUTPUT_DIRECTORY_FAILED');
}
$json = json_encode(
    $draft,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
) . PHP_EOL;
if (file_put_contents($output, $json, LOCK_EX) !== strlen($json)) {
    throw new RuntimeException('ML8C_DRAFT_OUTPUT_WRITE_FAILED');
}
fwrite(STDERR, PHP_EOL);
echo json_encode([
    'output' => $output,
    'release_count' => $draft['release_count'],
    'item_count' => $draft['item_count'],
    'review_status' => $draft['approval_status'],
    'manifest_digest' => $draft['manifest_digest'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
