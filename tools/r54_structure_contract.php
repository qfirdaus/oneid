<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$checks = 0;
$failed = 0;

$report = static function (bool $passed, string $label, string $detail = '') use (&$checks, &$failed): void {
    $checks++;
    if (!$passed) {
        $failed++;
    }

    printf(
        "%s %-58s%s\n",
        $passed ? 'PASS' : 'FAIL',
        $label,
        $detail === '' ? '' : ' ' . $detail
    );
};

$read = static function (string $relativePath) use ($projectRoot): string {
    $contents = file_get_contents($projectRoot . '/' . $relativePath);
    return is_string($contents) ? $contents : '';
};

$legacyPublicRoots = ['assetsM', 'dist', 'img', 'public_img', 'public_docs', 'videos'];
foreach ($legacyPublicRoots as $directory) {
    $report(
        !is_dir($projectRoot . '/' . $directory),
        'legacy public root remains absent: ' . $directory
    );
}

$requiredPublicRoots = ['assetsM', 'dist', 'img', 'public_img', 'public_docs', 'videos', 'vendors'];
foreach ($requiredPublicRoots as $directory) {
    $report(
        is_dir($projectRoot . '/public/' . $directory),
        'public asset root exists: ' . $directory
    );
}

$gruntfile = $read('Gruntfile.js');
$report(
    str_contains($gruntfile, "'public/dist/css/style.css': 'src/scss/style.scss'"),
    'Grunt CSS output is inside public root'
);
$report(
    !str_contains($gruntfile, "'dist/css/style.css'"),
    'Grunt cannot recreate root dist directory'
);
$report(
    str_contains($gruntfile, "base: 'public'"),
    'Grunt development server is restricted to public root'
);
$report(
    !preg_match('/style-(?:dark|rtl|rtl-dark)\\.scss/', $gruntfile),
    'Grunt has no task referencing missing SCSS variants'
);

$bowerConfig = json_decode($read('.bowerrc'), true);
$report(
    is_array($bowerConfig)
        && ($bowerConfig['directory'] ?? null) === 'public/vendors/bower_components',
    'Bower installation target is inside public/vendors'
);

$package = json_decode($read('package.json'), true);
$report(is_array($package), 'package.json is valid JSON');
$report(($package['private'] ?? false) === true, 'package is marked private');
$report(
    ($package['scripts']['build'] ?? null) === 'grunt build',
    'deterministic npm build command is declared'
);
$report(
    ($package['scripts']['check:structure'] ?? null) === 'php tools/r54_structure_contract.php',
    'npm structure check command is declared'
);

$report(
    is_file($projectRoot . '/docs/migrations/FASA_5_AUTH_HARDENING.sql'),
    'sanitised Fasa 5 migration exists'
);
$report(
    str_contains($read('.gitignore'), '!/docs/migrations/*.sql'),
    'sanitised migration allowlist is declared'
);

$runtimeDependencies = [
    'vendors/device-detector-master/autoload.php',
    'vendors/spyc-master/Spyc.php',
    'public/vendors/bower_components/jquery/dist/jquery.min.js',
    'public/vendors/bower_components/bootstrap/dist/js/bootstrap.min.js',
    'public/vendors/vectormap/jquery-jvectormap-2.0.2.min.js',
    'public/vendors/typeahead.js',
];
foreach ($runtimeDependencies as $dependency) {
    $report(
        is_file($projectRoot . '/' . $dependency),
        'runtime dependency exists: ' . $dependency
    );
}

$publicSymlinks = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($projectRoot . '/public', FilesystemIterator::SKIP_DOTS)
);
foreach ($iterator as $item) {
    if ($item->isLink()) {
        $publicSymlinks[] = $item->getPathname();
    }
}
$report(
    $publicSymlinks === [],
    'public tree contains no symlinks',
    $publicSymlinks === [] ? '' : implode(',', $publicSymlinks)
);

printf("RESULT checks=%d failed=%d\n", $checks, $failed);
exit($failed === 0 ? 0 : 1);
