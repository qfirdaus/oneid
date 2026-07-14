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
        "%s %-64s%s\n",
        $passed ? 'PASS' : 'FAIL',
        $label,
        $detail === '' ? '' : ' ' . $detail
    );
};

$firstPartyRoots = [
    $projectRoot . '/index.php',
    $projectRoot . '/admin',
    $projectRoot . '/page',
];
$appAssetReferences = [];
foreach ($firstPartyRoots as $root) {
    if (is_file($root)) {
        $files = [$root];
    } else {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $item) {
            if ($item->isFile() && $item->getExtension() === 'php') {
                $files[] = $item->getPathname();
            }
        }
    }

    foreach ($files as $file) {
        $contents = file_get_contents($file);
        if (is_string($contents) && str_contains($contents, 'app-assets')) {
            $appAssetReferences[] = substr($file, strlen($projectRoot) + 1);
        }
    }
}
$report(
    $appAssetReferences === [],
    'first-party PHP contains no missing /app-assets reference',
    implode(',', $appAssetReferences)
);
$report(!is_dir($projectRoot . '/public/app-assets'), 'fake app-assets compatibility tree was not created');

$userList = file_get_contents($projectRoot . '/admin/user_list.php');
$userList = is_string($userList) ? $userList : '';
$report(!str_contains($userList, 'tableToCSV'), 'inactive tableToCSV dependency removed');
$report(!str_contains($userList, '<script'), 'admin user list needs no JavaScript asset');
$report(str_contains($userList, 'oneid_require_admin_page();'), 'admin user list role guard remains');
$report(
    str_contains($userList, 'admin_get_specific_category_user_listing'),
    'admin user list data caller remains'
);

$adminDashboard = file_get_contents($projectRoot . '/admin/dashboard.php');
$adminDashboard = is_string($adminDashboard) ? $adminDashboard : '';
$report(
    str_contains($adminDashboard, 'window.open("./user_list.php?category_id="'),
    'admin user list URL has no trailing slash after PHP entry point'
);
$report(
    !str_contains($adminDashboard, 'user_list.php/?'),
    'broken user_list PATH_INFO route is absent'
);
$report(
    str_contains($adminDashboard, 'encodeURIComponent(cat_name)'),
    'admin user list category name is URL encoded'
);

$requiredAssets = [
    'public/assetsM/css/custom.css',
    'public/assetsM/css/main.min.css',
    'public/assetsM/js/jquery.min.js',
    'public/assetsM/js/main.js',
    'public/dist/css/style.css',
    'public/dist/js/init.js',
    'public/dist/js/widgets-data.js',
    'public/img/logo_oneid.png',
    'public/img/logoupnm_2.png',
    'public/public_docs/MANUAL_SALAM.pdf',
    'public/videos/video1.mp4',
    'public/vendors/bower_components/jquery/dist/jquery.min.js',
    'public/vendors/bower_components/bootstrap/dist/js/bootstrap.min.js',
    'public/vendors/bower_components/sweetalert/dist/sweetalert.min.js',
    'public/vendors/bower_components/jquery-toast-plugin/dist/jquery.toast.min.js',
    'public/vendors/vectormap/jquery-jvectormap-2.0.2.css',
    'public/vendors/typeahead.js',
];
foreach ($requiredAssets as $asset) {
    $report(is_file($projectRoot . '/' . $asset), 'required public asset exists: ' . $asset);
}

$metadataFiles = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($projectRoot . '/public', FilesystemIterator::SKIP_DOTS)
);
foreach ($iterator as $item) {
    if ($item->isFile() && in_array($item->getFilename(), ['Thumbs.db', '.DS_Store'], true)) {
        $metadataFiles[] = substr($item->getPathname(), strlen($projectRoot) + 1);
    }
}
$report(
    $metadataFiles === [],
    'public tree contains no OS metadata files',
    implode(',', $metadataFiles)
);

printf("RESULT checks=%d failed=%d\n", $checks, $failed);
exit($failed === 0 ? 0 : 1);
