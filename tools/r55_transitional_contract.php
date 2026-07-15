<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$checks = 0;
$failed = 0;

$check = static function (bool $condition, string $label) use (&$checks, &$failed): void {
    ++$checks;
    if (!$condition) {
        ++$failed;
    }

    printf("%s %-68s\n", $condition ? 'PASS' : 'FAIL', $label);
};

$check(!file_exists($root . '/.htaccess'), 'retired root .htaccess is absent');
$check(!file_exists($root . '/public/.htaccess'), 'unused Apache public-root configuration is absent');
$check(!file_exists($root . '/public/public_img/.htaccess'), 'unused Apache upload configuration is absent');
$nginxTemplate = (string) file_get_contents($root . '/deployment/nginx/oneid-staging.conf');
$check(str_contains($nginxTemplate, 'public_img|videos'), 'Nginx template blocks scripts in asset/upload roots');

$paths = (string) file_get_contents($root . '/bootstrap/paths.php');
$check(!str_contains($paths, 'LEGACY_PUBLIC_PATH'), 'unused LEGACY_PUBLIC_PATH is absent');
$check(str_contains($paths, "define('PROJECT_ROOT'"), 'PROJECT_ROOT remains defined');
$check(str_contains($paths, "define('PUBLIC_PATH'"), 'PUBLIC_PATH remains defined');
$check(str_contains($paths, "define('STORAGE_PATH'"), 'STORAGE_PATH remains defined');
$check(str_contains($paths, "getenv('ONEID_PUBLIC_PATH')"), 'public-path environment override remains');
$check(str_contains($paths, 'function oneid_project_path'), 'project path helper remains');
$check(str_contains($paths, 'function oneid_public_path'), 'public path helper remains');
$check(str_contains($paths, 'function oneid_storage_path'), 'storage path helper remains');

foreach (['config/README.md', 'resources/README.md'] as $placeholderDecision) {
    $check(is_file($root . '/' . $placeholderDecision), 'intentional private boundary retained: ' . $placeholderDecision);
}

foreach (['config', 'resources'] as $privateDirectory) {
    $check(!file_exists($root . '/public/' . $privateDirectory), 'private boundary remains outside public: ' . $privateDirectory);
}

$check(is_file($root . '/docs/R5_5B_TRANSITIONAL_ARTIFACT_DISPOSITION.tsv'), 'transitional disposition register exists');
$check(is_file($root . '/docs/nginx/R5_5B_RETIRE_ONEID_NEXT.md'), 'oneid-next retirement runbook exists');

printf("RESULT checks=%d failed=%d\n", $checks, $failed);
exit($failed === 0 ? 0 : 1);
