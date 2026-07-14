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

foreach (['app', 'bootstrap', 'config', 'docs', 'public', 'resources', 'storage', 'tests', 'tools'] as $directory) {
    $check(is_dir($root . '/' . $directory), 'private/project boundary exists: ' . $directory);
}

foreach (['assetsM', 'dist', 'img', 'public_docs', 'public_img', 'videos'] as $legacyPublicRoot) {
    $check(!file_exists($root . '/' . $legacyPublicRoot), 'legacy public asset root remains absent: ' . $legacyPublicRoot);
}

foreach (['assetsM', 'dist', 'img', 'public_docs', 'public_img', 'vendors', 'videos'] as $publicDirectory) {
    $check(is_dir($root . '/public/' . $publicDirectory), 'public runtime directory exists: ' . $publicDirectory);
}

$boundaryPairs = [
    ['admin', 'public/admin'],
    ['page', 'public/page'],
    ['lib', 'public/lib'],
    ['vendors', 'public/vendors'],
];

foreach ($boundaryPairs as [$privatePath, $publicPath]) {
    $check(
        is_dir($root . '/' . $privatePath)
        && is_dir($root . '/' . $publicPath)
        && !is_link($root . '/' . $privatePath)
        && !is_link($root . '/' . $publicPath),
        'intentional private/public boundary is physical: ' . $privatePath
    );
}

$wrappers = [
    'public/index.php',
    'public/api.php',
    'public/idms.php',
    'public/skp_api.php',
    'public/admin/dashboard.php',
    'public/admin/logout.php',
    'public/admin/user_list.php',
    'public/page/dashboard.php',
    'public/page/logout.php',
    'public/lib/q_func.php',
    'public/lib/sso_IDP_index.php',
    'public/lib/sso_IDP_sub.php',
];

foreach ($wrappers as $wrapper) {
    $check(is_file($root . '/' . $wrapper) && !is_link($root . '/' . $wrapper), 'public wrapper is a physical file: ' . $wrapper);
}

foreach (['app', 'bootstrap', 'config', 'docs', 'resources', 'storage', 'tests', 'tools'] as $privateDirectory) {
    $check(!file_exists($root . '/public/' . $privateDirectory), 'private directory is absent below public: ' . $privateDirectory);
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);
$activeSymlinks = [];
$worldWritable = [];
foreach ($iterator as $path => $info) {
    $normalized = str_replace('\\', '/', $path);
    if (str_contains($normalized, '/.git/') || str_contains($normalized, '/storage/quarantine/')) {
        continue;
    }

    if ($info->isLink()) {
        $activeSymlinks[] = $path;
    }

    if (($info->getPerms() & 0002) !== 0) {
        $worldWritable[] = $path;
    }
}

$check($activeSymlinks === [], 'active project tree contains zero symlinks');
$check($worldWritable === [], 'active project tree contains zero world-writable paths');

$adminTop = $root . '/admin/const/top.php';
$pageTop = $root . '/page/const/top.php';
$check(is_file($adminTop) && is_file($pageTop), 'role-specific top templates both exist');
$check(hash_file('sha256', $adminTop) !== hash_file('sha256', $pageTop), 'role-specific top templates are not duplicate content');

$check(is_file($root . '/src/scss/style.scss'), 'project src is active SCSS build source');
$check(is_file($root . '/lib/src/PHPMailer.php'), 'lib/src is active PHPMailer runtime source');
$check(is_file($root . '/vendors/device-detector-master/autoload.php'), 'private vendors contains runtime PHP dependency');
$check(is_file($root . '/public/vendors/bower_components/jquery/dist/jquery.min.js'), 'public vendors contains browser dependency');

$gitignore = (string) file_get_contents($root . '/.gitignore');
$check(str_contains($gitignore, '*.sql'), 'database dumps are Git-ignored');
$check(str_contains($gitignore, 'lib/config.php'), 'runtime-local configuration is Git-ignored');
$check(str_contains($gitignore, '/storage/quarantine/*'), 'quarantine payload is Git-ignored');
$check(str_contains($gitignore, '/public/public_img/*'), 'runtime uploads are Git-ignored');

$ssoDump = $root . '/sso_db.sql';
$check(!is_file($ssoDump) || ((fileperms($ssoDump) & 0007) === 0), 'local database dump is not accessible to other users');

$readme = (string) file_get_contents($root . '/README.md');
$check(str_contains($readme, 'document root') && str_contains($readme, 'public/'), 'README declares public-only web boundary');

printf("RESULT checks=%d failed=%d\n", $checks, $failed);
exit($failed === 0 ? 0 : 1);
