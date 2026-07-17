<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(2);
}

$options = getopt('', ['apply', 'restore:', 'limit:']);
$apply = array_key_exists('apply', $options);
$restoreBatch = trim((string) ($options['restore'] ?? ''));
$limit = max(0, (int) ($options['limit'] ?? 0));
if ($apply && $restoreBatch !== '') {
    fwrite(STDERR, "Choose either --apply or --restore, not both.\n");
    exit(2);
}

ob_start();
require __DIR__ . '/wa6_web_app_asset_reconciliation.php';
$reportJson = (string) ob_get_clean();
$report = json_decode($reportJson, true, 512, JSON_THROW_ON_ERROR);
$environment = (string) $report['environment'];
$projectRoot = dirname(__DIR__);
$uploadDir = (string) $report['upload_dir'];
$quarantineRoot = $projectRoot . '/storage/quarantine/web_app_assets/' . $environment;

$writeManifest = static function (string $path, array $manifest): void {
    $temporary = $path . '.tmp';
    $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
    if (file_put_contents($temporary, $json, LOCK_EX) === false || !chmod($temporary, 0600) || !rename($temporary, $path)) {
        @unlink($temporary);
        throw new RuntimeException('Unable to write quarantine manifest safely.');
    }
};

if ($restoreBatch !== '') {
    if (preg_match('/^[0-9]{8}T[0-9]{6}-[a-f0-9]{8}$/', $restoreBatch) !== 1) {
        throw new RuntimeException('Invalid restore batch ID.');
    }
    $batchDir = $quarantineRoot . '/' . $restoreBatch;
    $manifestPath = $batchDir . '/manifest.json';
    $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
    if (($manifest['environment'] ?? '') !== $environment || ($manifest['status'] ?? '') !== 'quarantined') {
        throw new RuntimeException('Manifest environment/status is not restorable.');
    }
    $restored = [];
    try {
        foreach ($manifest['files'] as $file) {
            $filename = basename((string) $file['filename']);
            $source = $batchDir . '/' . $filename;
            $destination = $uploadDir . '/' . $filename;
            if (!is_file($source) || hash_file('sha256', $source) !== (string) $file['sha256']) {
                throw new RuntimeException('Quarantined file is missing or hash changed: ' . $filename);
            }
            if (file_exists($destination) || !rename($source, $destination)) {
                throw new RuntimeException('Unable to restore without overwriting: ' . $filename);
            }
            $restored[] = ['source' => $destination, 'destination' => $source];
        }
    } catch (Throwable $exception) {
        foreach (array_reverse($restored) as $move) {
            @rename($move['source'], $move['destination']);
        }
        throw $exception;
    }
    $manifest['status'] = 'restored';
    $manifest['restored_at'] = date(DATE_ATOM);
    $writeManifest($manifestPath, $manifest);
    printf("RESTORED batch=%s files=%d environment=%s\n", $restoreBatch, count($restored), $environment);
    exit(0);
}

$candidates = array_values(array_filter(
    $report['orphan_candidates'],
    static fn(array $candidate): bool =>
        $candidate['classification'] === 'unreferenced_all_database_candidate'
        && (int) $candidate['age_days'] >= 30
));
if ($limit > 0) {
    $candidates = array_slice($candidates, 0, $limit);
}

printf(
    "%s environment=%s grace_days=30 candidates=%d filesystem_mutation=%s\n",
    $apply ? 'APPLY' : 'DRY-RUN',
    $environment,
    count($candidates),
    $apply ? 'true' : 'false'
);
foreach ($candidates as $candidate) {
    printf(
        "%s age_days=%d bytes=%d sha256=%s\n",
        $candidate['filename'],
        $candidate['age_days'],
        $candidate['bytes'],
        $candidate['sha256']
    );
}
if (!$apply) {
    exit(0);
}
if ($candidates === []) {
    fwrite(STDERR, "No approved candidates remain after revalidation.\n");
    exit(3);
}

$batchId = date('Ymd\THis') . '-' . bin2hex(random_bytes(4));
$batchDir = $quarantineRoot . '/' . $batchId;
if (!is_dir($batchDir) && (!mkdir($batchDir, 0700, true) || !chmod($batchDir, 0700))) {
    throw new RuntimeException('Unable to create private quarantine batch.');
}
$manifestPath = $batchDir . '/manifest.json';
$manifest = [
    'contract' => 'WA6_QUARANTINE_MANIFEST_V1',
    'batch_id' => $batchId,
    'environment' => $environment,
    'grace_days' => 30,
    'status' => 'planned',
    'created_at' => date(DATE_ATOM),
    'source_dir' => $uploadDir,
    'files' => $candidates,
];
$writeManifest($manifestPath, $manifest);

$moved = [];
try {
    foreach ($candidates as $candidate) {
        $filename = basename((string) $candidate['filename']);
        $source = $uploadDir . '/' . $filename;
        $destination = $batchDir . '/' . $filename;
        if (!is_file($source)
            || filesize($source) !== (int) $candidate['bytes']
            || hash_file('sha256', $source) !== (string) $candidate['sha256']
            || file_exists($destination)
            || !rename($source, $destination)) {
            throw new RuntimeException('Candidate changed or move failed: ' . $filename);
        }
        chmod($destination, 0600);
        $moved[] = ['source' => $source, 'destination' => $destination];
    }
} catch (Throwable $exception) {
    foreach (array_reverse($moved) as $move) {
        @rename($move['destination'], $move['source']);
    }
    $manifest['status'] = 'rolled_back';
    $manifest['failure'] = $exception->getMessage();
    $writeManifest($manifestPath, $manifest);
    throw $exception;
}

$manifest['status'] = 'quarantined';
$manifest['quarantined_at'] = date(DATE_ATOM);
$writeManifest($manifestPath, $manifest);
printf("QUARANTINED batch=%s files=%d environment=%s\n", $batchId, count($moved), $environment);
