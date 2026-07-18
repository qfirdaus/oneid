<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { exit(2); }

$root = dirname(__DIR__);
$docsRoot = $root . '/docs';
$checks = 0;
$failed = 0;
$report = static function (bool $passed, string $label) use (&$checks, &$failed): void {
    $checks++;
    if (!$passed) { $failed++; }
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $label);
};

$legacyPattern = '/\bv?2\.0\.(?:[5-9]|[1-9][0-9]+)\b/i';
$legacyFiles = [];
$legacyContents = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($docsRoot));
foreach ($iterator as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'md') { continue; }
    $relative = substr($file->getPathname(), strlen($root) + 1);
    if (preg_match($legacyPattern, $relative) === 1) { $legacyFiles[] = $relative; }
    if ($relative === 'docs/VERSION_NUMBERING_POLICY.md') { continue; }
    if (preg_match($legacyPattern, (string) file_get_contents($file->getPathname())) === 1) {
        $legacyContents[] = $relative;
    }
}

$report($legacyFiles === [], 'documentation filenames contain no superseded 2.0.5-or-later release numbers');
$report($legacyContents === [], 'documentation content contains no superseded release numbers outside the migration policy');

$expectedReleaseDocs = [
    'docs/RELEASE_V2.1.0_AUDIT_LOG_NEWEST_FIRST.md',
    'docs/RELEASE_V2.1.1_CONTROLLED_PILOT_DAN_UI.md',
    'docs/RELEASE_V2.1.2_SECURITY_DAN_WEB_APPS.md',
    'docs/RELEASE_V2.1.3_UI_AUDIT_DAN_PERANCANGAN.md',
];
$report(
    array_reduce($expectedReleaseDocs, static fn(bool $carry, string $path): bool => $carry && is_file($root . '/' . $path), true),
    'renumbered release documents exist at their canonical paths'
);
$report(
    str_contains((string) file_get_contents($root . '/docs/VERSION_NUMBERING_POLICY.md'), '| 2.0.20 | 2.4.0 |'),
    'migration policy preserves the explicit legacy-to-normalized audit mapping'
);

printf("RESULT checks=%d failed=%d\n", $checks, $failed);
exit($failed === 0 ? 0 : 1);
