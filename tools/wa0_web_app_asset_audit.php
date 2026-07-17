<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/config.php';

$pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$uploadDir = oneid_public_path('public_img');
$resolvedUploadDir = realpath($uploadDir) ?: $uploadDir;
$environment = strtolower(trim((string) oneid_config('ONEID_ENVIRONMENT', '')));
if (preg_match('/^[a-z][a-z0-9_-]{1,31}$/', $environment) !== 1) {
    throw new RuntimeException('ONEID_ENVIRONMENT is not configured safely.');
}
$scalar = static function (PDO $pdo, string $sql): int {
    return (int) $pdo->query($sql)->fetchColumn();
};

$statement=$pdo->prepare(
    "SELECT s.sp_id,s.sp_name,s.sp_domain,
            COALESCE(NULLIF(a.image_filename,''),s.sp_image) AS sp_image,
            s.sp_image AS legacy_sp_image,a.image_filename AS environment_image,
            s.avail_status
       FROM sp_list s
       LEFT JOIN sp_app_asset a ON a.sp_id=s.sp_id AND a.environment=:environment
      ORDER BY s.sp_id"
);
$statement->execute([':environment'=>$environment]);
$rows=$statement->fetchAll();

$referenced = [];
$missing = [];
$invalidUrl = [];
foreach ($rows as $row) {
    $image = trim((string) ($row['sp_image'] ?? ''));
    if ($image !== '') {
        $referenced[$image] = true;
        if (!is_file($resolvedUploadDir . DIRECTORY_SEPARATOR . $image)) {
            $missing[] = (string) $row['sp_id'] . ':' . $image;
        }
    }

    $url = trim((string) ($row['sp_domain'] ?? ''));
    $parts = $url === '' ? false : parse_url($url);
    if ($parts === false || !isset($parts['scheme'], $parts['host']) || strtolower((string) $parts['scheme']) !== 'https') {
        $invalidUrl[] = (string) $row['sp_id'];
    }
}

$localFiles = [];
if (is_dir($resolvedUploadDir)) {
    $iterator = new FilesystemIterator($resolvedUploadDir, FilesystemIterator::SKIP_DOTS);
    foreach ($iterator as $entry) {
        if ($entry->isFile() && preg_match('/\Aapp_icon_[A-Za-z0-9_-]+\.(?:jpe?g|png|gif|webp)\z/i', $entry->getFilename())) {
            $localFiles[$entry->getFilename()] = true;
        }
    }
}

$orphanFiles = array_values(array_diff(array_keys($localFiles), array_keys($referenced)));
sort($orphanFiles);
sort($missing);
sort($invalidUrl);

$metrics = [
    'database_apps_total' => count($rows),
    'database_apps_active' => $scalar($pdo, "SELECT COUNT(*) FROM sp_list WHERE avail_status=1"),
    'database_apps_inactive' => $scalar($pdo, "SELECT COUNT(*) FROM sp_list WHERE avail_status=0"),
    'database_nonempty_image_rows' => $scalar($pdo, "SELECT COUNT(*) FROM sp_list WHERE TRIM(COALESCE(sp_image,''))<>''"),
    'database_distinct_image_refs' => count($referenced),
    'database_active_empty_image' => $scalar($pdo, "SELECT COUNT(*) FROM sp_list WHERE avail_status=1 AND TRIM(COALESCE(sp_image,''))=''"),
    'database_duplicate_name_groups' => $scalar($pdo, "SELECT COUNT(*) FROM (SELECT LOWER(TRIM(sp_name)) n FROM sp_list GROUP BY LOWER(TRIM(sp_name)) HAVING COUNT(*)>1) d"),
    'database_duplicate_domain_groups' => $scalar($pdo, "SELECT COUNT(*) FROM (SELECT LOWER(TRIM(sp_domain)) d FROM sp_list WHERE TRIM(COALESCE(sp_domain,''))<>'' GROUP BY LOWER(TRIM(sp_domain)) HAVING COUNT(*)>1) x"),
    'database_non_https_or_invalid_urls' => count($invalidUrl),
    'database_sp_app_asset_table_exists' => $scalar($pdo, "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sp_app_asset'"),
    'database_environment_asset_rows_all' => $scalar($pdo, "SELECT COUNT(*) FROM sp_app_asset"),
    'database_environment_asset_rows_current' => (int) (function() use ($pdo,$environment){$s=$pdo->prepare("SELECT COUNT(*) FROM sp_app_asset WHERE environment=:environment");$s->execute([':environment'=>$environment]);return $s->fetchColumn();})(),
    'filesystem_local_icon_files' => count($localFiles),
    'filesystem_missing_referenced_files' => count($missing),
    'filesystem_orphan_candidate_files' => count($orphanFiles),
];

echo "WA0 web-app asset audit (read-only)\n";
echo 'timestamp=' . date(DATE_ATOM) . "\n";
echo 'upload_dir=' . $uploadDir . "\n";
echo 'resolved_upload_dir=' . $resolvedUploadDir . "\n";
echo 'environment=' . $environment . "\n";
foreach ($metrics as $name => $value) {
    printf("METRIC %s=%d\n", $name, $value);
}
echo 'DETAIL missing_referenced_files=' . json_encode($missing, JSON_UNESCAPED_SLASHES) . "\n";
echo 'DETAIL orphan_candidate_files=' . json_encode($orphanFiles, JSON_UNESCAPED_SLASHES) . "\n";
echo 'DETAIL non_https_or_invalid_app_ids=' . json_encode($invalidUrl, JSON_UNESCAPED_SLASHES) . "\n";
