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

foreach ([
    'app/Metadata/MetadataContentInventory.php',
    'tools/ml7a_content_completion_preview.php',
    'tests/characterization/ml7a_content_completion.php',
    'lib/q_func.php',
    'lib/request_security.php',
    'admin/dashboard.php',
] as $relative) {
    exec('php -l ' . escapeshellarg($root . '/' . $relative) . ' 2>&1', $output, $status);
    $report($status === 0, "source and lint {$relative}");
}

$inventory = file_get_contents($root . '/app/Metadata/MetadataContentInventory.php') ?: '';
$endpoint = file_get_contents($root . '/lib/q_func.php') ?: '';
$security = file_get_contents($root . '/lib/request_security.php') ?: '';
$admin = file_get_contents($root . '/admin/dashboard.php') ?: '';

$report(
    str_contains($inventory, "'automatic_approval' => false")
    && str_contains($inventory, "'can_apply' => false")
    && str_contains($inventory, "'mutation_statements' => 0"),
    'inventory and draft Preview is hard-coded zero mutation'
);
$report(
    str_contains($inventory, 'PENDING_OWNER_REVIEW')
    && str_contains($inventory, 'ML7A_OWNER_REVIEW_INCOMPLETE')
    && str_contains($inventory, 'APPROVED_EXISTING'),
    'owner review decision is explicit and fail-closed'
);
$report(
    str_contains($inventory, 'duplicate_identity_count')
    && str_contains($inventory, 'duplicate_content')
    && str_contains($inventory, 'source_digest'),
    'identity, duplicate content and stale-source evidence is present'
);
$report(
    !str_contains($inventory, 'INSERT INTO')
    && !str_contains($inventory, 'UPDATE sp_')
    && !str_contains($inventory, 'DELETE FROM'),
    'content inventory cannot write metadata or translations'
);
$report(
    !str_contains($security, "'admin_metadata_content_preview'")
    && !str_contains($endpoint, 'admin_metadata_content_preview')
    && !str_contains($admin, 'metadata_content_preview_rows'),
    'retired Administrator content review UI and endpoint leave no web surface'
);
$report(
    !str_contains($admin, 'admin_apply_metadata_content')
    && !str_contains($endpoint, 'admin_apply_metadata_content'),
    'bulk content Apply endpoint does not exist'
);

passthru('php ' . escapeshellarg($root . '/tests/characterization/ml7a_content_completion.php'), $status);
$report($status === 0, 'ML7A characterization passes');

echo "RESULT checks={$checks} failed={$failed}\n";
exit($failed === 0 ? 0 : 1);
