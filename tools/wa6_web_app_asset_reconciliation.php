<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/config.php';

$environment = strtolower(trim((string) oneid_config('ONEID_ENVIRONMENT', '')));
if (preg_match('/^[a-z][a-z0-9_-]{1,31}$/', $environment) !== 1) {
    throw new RuntimeException('ONEID_ENVIRONMENT is not configured safely.');
}

$uploadDir = realpath(oneid_public_path('public_img')) ?: oneid_public_path('public_img');
$pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$statement = $pdo->prepare(
    "SELECT s.sp_id,s.sp_name,s.avail_status,s.sp_image AS legacy_image,
            a.image_filename AS environment_image,
            COALESCE(NULLIF(a.image_filename,''),NULLIF(s.sp_image,'')) AS effective_image
       FROM sp_list s
       LEFT JOIN sp_app_asset a ON a.sp_id=s.sp_id AND a.environment=:environment
      ORDER BY s.sp_id"
);
$statement->execute([':environment' => $environment]);
$apps = $statement->fetchAll();

$references = [];
$missing = [];
foreach ($apps as $app) {
    $filename = trim((string) ($app['effective_image'] ?? ''));
    if ($filename === '') {
        continue;
    }
    $references[$filename][] = [
        'app_id' => (string) $app['sp_id'],
        'active' => (int) $app['avail_status'] === 1,
        'source' => trim((string) ($app['environment_image'] ?? '')) !== '' ? 'environment' : 'legacy_fallback',
    ];
    if (!is_file($uploadDir . DIRECTORY_SEPARATOR . basename($filename))) {
        $missing[] = ['filename' => $filename, 'references' => $references[$filename]];
    }
}

$files = [];
if (is_dir($uploadDir)) {
    foreach (new FilesystemIterator($uploadDir, FilesystemIterator::SKIP_DOTS) as $entry) {
        if (!$entry->isFile() || preg_match('/\Aapp_icon_[A-Za-z0-9_-]+\.(?:jpe?g|png|gif|webp)\z/i', $entry->getFilename()) !== 1) {
            continue;
        }
        $files[$entry->getFilename()] = [
            'filename' => $entry->getFilename(),
            'bytes' => $entry->getSize(),
            'modified_at' => date(DATE_ATOM, $entry->getMTime()),
            'sha256' => hash_file('sha256', $entry->getPathname()),
        ];
    }
}

$orphanCandidates = [];
foreach ($files as $filename => $manifest) {
    if (!isset($references[$filename])) {
        $orphanCandidates[] = $manifest + ['classification' => 'unreferenced_candidate'];
    }
}

usort($missing, static fn(array $a, array $b): int => $a['filename'] <=> $b['filename']);
usort($orphanCandidates, static fn(array $a, array $b): int => $a['filename'] <=> $b['filename']);

$report = [
    'contract' => 'WA6_RECONCILIATION_READ_ONLY_V1',
    'generated_at' => date(DATE_ATOM),
    'environment' => $environment,
    'upload_dir' => $uploadDir,
    'summary' => [
        'database_apps' => count($apps),
        'effective_referenced_files' => count($references),
        'filesystem_icon_files' => count($files),
        'missing_references' => count($missing),
        'orphan_candidates' => count($orphanCandidates),
    ],
    'missing_references' => $missing,
    'orphan_candidates' => $orphanCandidates,
    'safety' => [
        'filesystem_mutation' => false,
        'database_mutation' => false,
        'deletion_authorized' => false,
        'next_gate' => 'owner approval of exact environment-specific manifest',
    ],
];

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
