<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$tool = (string) file_get_contents($root . '/tools/wa6_web_app_asset_quarantine.php');
$ignore = (string) file_get_contents($root . '/.gitignore');
$checks = [
    'CLI only' => str_contains($tool, "PHP_SAPI !== 'cli'"),
    'default mode is dry-run' => str_contains($tool, 'if (!$apply)'),
    'minimum age is thirty days' => str_contains($tool, "['age_days'] >= 30"),
    'only globally unreferenced candidates qualify' => str_contains($tool, "unreferenced_all_database_candidate"),
    'apply revalidates size and SHA-256' => str_contains($tool, 'filesize($source)') && str_contains($tool, "hash_file('sha256', \$source)"),
    'move refuses destination overwrite' => str_contains($tool, 'file_exists($destination)'),
    'failed batch rolls moved files back' => str_contains($tool, 'array_reverse($moved)') && str_contains($tool, "'rolled_back'"),
    'manifest is private and atomic' => str_contains($tool, 'chmod($temporary, 0600)') && str_contains($tool, 'rename($temporary, $path)'),
    'restore verifies hash and refuses overwrite' => str_contains($tool, 'Quarantined file is missing or hash changed') && str_contains($tool, 'Unable to restore without overwriting'),
    'quarantine runtime is ignored by Git' => str_contains($ignore, '/storage/quarantine/'),
    'tool contains no asset deletion operation' => !str_contains($tool, 'unlink($source)') && !str_contains($tool, 'unlink($destination)') && !preg_match('/\brmdir\s*\(/', $tool),
];

$failed = 0;
foreach ($checks as $label => $passed) {
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $label);
    if (!$passed) $failed++;
}
printf("RESULT %d/%d\n", count($checks) - $failed, count($checks));
exit($failed === 0 ? 0 : 1);
