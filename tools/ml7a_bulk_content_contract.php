<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$checks = 0;
$failed = 0;
$report = static function (bool $ok, string $label) use (&$checks, &$failed): void {
    $checks++;
    echo ($ok ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
    if (!$ok) {
        $failed++;
    }
};

$files = [
    'app/Metadata/MetadataBulkContentPlanner.php',
    'tools/ml7a_bulk_content_preview.php',
    'tests/characterization/ml7a_bulk_content_preview.php',
    'docs/migrations/20260725_ml7a_content_review_decision_up.sql',
    'docs/migrations/20260725_ml7a_content_review_decision_down.sql',
];
foreach ($files as $file) {
    $path = $root . '/' . $file;
    $ok = is_file($path);
    if ($ok && str_ends_with($file, '.php')) {
        exec('php -l ' . escapeshellarg($path), $output, $status);
        $ok = $status === 0;
    }
    $report($ok, 'source and lint ' . $file);
}

$planner = file_get_contents($root . '/app/Metadata/MetadataBulkContentPlanner.php');
$endpoint = file_get_contents($root . '/lib/q_func.php');
$security = file_get_contents($root . '/lib/request_security.php');
$migrationUp = file_get_contents($root . '/docs/migrations/20260725_ml7a_content_review_decision_up.sql');
$migrationDown = file_get_contents($root . '/docs/migrations/20260725_ml7a_content_review_decision_down.sql');

$report(
    str_contains($planner, MetadataDigestPlaceholder::VALUE)
    && str_contains($planner, "'can_apply' => false")
    && str_contains($planner, "'mutation_statements' => 0"),
    'planner is frozen to approved digest and zero-mutation Preview'
);
$report(
    str_contains($planner, "'original_metadata_updates' => 0")
    && str_contains($planner, "'quarantine_translation_inserts' => 0"),
    'original and quarantine mutations are prohibited'
);
$report(
    str_contains($migrationUp, 'CREATE TABLE metadata_content_review')
    && str_contains($migrationUp, 'UNIQUE KEY uq_metadata_content_review_entity')
    && trim($migrationDown) === 'DROP TABLE metadata_content_review;',
    'review-decision migration is additive and reversible'
);
$report(
    !str_contains($security, "'admin_metadata_bulk_content_preview'")
    && !str_contains($endpoint, 'admin_metadata_bulk_content_preview'),
    'retired Administrator bulk Preview leaves no web endpoint'
);
$report(
    !str_contains($endpoint, 'admin_apply_metadata_bulk_content')
    && !str_contains($planner, 'INSERT INTO')
    && !str_contains($planner, 'UPDATE sp_list')
    && !str_contains($planner, 'UPDATE sp_group'),
    'live Bulk Apply and metadata mutation endpoints do not exist'
);

passthru('php ' . escapeshellarg($root . '/tests/characterization/ml7a_bulk_content_preview.php'), $status);
$report($status === 0, 'ML7A bulk Preview characterization passes');

echo "RESULT checks={$checks} failed={$failed}\n";
exit($failed === 0 ? 0 : 1);

final class MetadataDigestPlaceholder
{
    public const VALUE = '6c4524393cd86fdab4beaa76e88feb63f24e6691b191457e044408e3446eb444';
}
