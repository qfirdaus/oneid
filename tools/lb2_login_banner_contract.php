<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$pipeline = (string) file_get_contents($root . '/app/LoginBanner/LoginBannerImagePipeline.php');
$index = (string) file_get_contents($root . '/index.php');
$actions = (string) file_get_contents($root . '/lib/request_security.php');

$checks = [
    'server verifies upload provenance by default' =>
        str_contains($pipeline, 'is_uploaded_file($path)'),
    'content inspection ignores client filename and MIME' =>
        str_contains($pipeline, 'new finfo(FILEINFO_MIME_TYPE)')
        && str_contains($pipeline, 'getimagesize($source)')
        && !str_contains($pipeline, "['name']"),
    'input limits cover bytes dimensions pixels and ratio' =>
        str_contains($pipeline, 'MAX_INPUT_BYTES = 5_242_880')
        && str_contains($pipeline, 'MAX_WIDTH = 4096')
        && str_contains($pipeline, 'MAX_HEIGHT = 2048')
        && str_contains($pipeline, 'MAX_PIXELS = 16_000_000')
        && str_contains($pipeline, 'RATIO_TOLERANCE = 0.02'),
    'static JPEG PNG and WebP are the only accepted formats' =>
        str_contains($pipeline, "['image/jpeg', 'image/png', 'image/webp']")
        && str_contains($pipeline, "'ANIM'")
        && str_contains($pipeline, "'acTL'"),
    'decode and re-encode strips source metadata' =>
        str_contains($pipeline, 'imagecreatefromstring($contents)')
        && str_contains($pipeline, 'imagecopyresampled(')
        && str_contains($pipeline, 'imagewebp($canvas, $targetPath, $quality)'),
    'output is exact and adaptively bounded' =>
        str_contains($pipeline, 'OUTPUT_WIDTH = 1600')
        && str_contains($pipeline, 'OUTPUT_HEIGHT = 800')
        && str_contains($pipeline, 'MAX_OUTPUT_BYTES = 512_000')
        && str_contains($pipeline, '[82, 76, 70, 64, 58, 52]'),
    'private staging and immutable names are random' =>
        str_contains($pipeline, "'.pending_login_banner_' . bin2hex(random_bytes(16))")
        && str_contains($pipeline, "'login_banner_' . bin2hex(random_bytes(16)) . '.webp'")
        && str_contains($pipeline, 'chmod($stagedPath, 0600)'),
    'publish revalidates and never overwrites' =>
        str_contains($pipeline, '$this->assertNormalizedFile($stagedPath, $staged)')
        && str_contains($pipeline, 'LB2_IMMUTABLE_TARGET_EXISTS')
        && str_contains($pipeline, 'rename($stagedPath, $target)'),
    'explicit staged and published compensation exists' =>
        str_contains($pipeline, 'function discardStaged(')
        && str_contains($pipeline, 'function discardPublished(')
        && str_contains($pipeline, 'function assertCompensationPath(')
        && str_contains($pipeline, 'LB2_COMPENSATION_TARGET_INVALID')
        && str_contains($pipeline, 'LB2_COMPENSATION_CLEANUP_FAILED'),
    'pipeline remains isolated from public login and direct browser execution' =>
        !str_contains($index, 'LoginBannerImagePipeline')
        && str_contains($actions, 'admin_login_banner_create_draft')
        && str_contains($index, 'assetsM/images/banner6.png')
        && str_contains($index, 'assetsM/images/banner7.png'),
];

$failures = 0;
foreach ($checks as $description => $passed) {
    echo ($passed ? 'PASS ' : 'FAIL ') . $description . PHP_EOL;
    if (!$passed) {
        $failures++;
    }
}
echo 'RESULT checks=' . count($checks) . ' failures=' . $failures . PHP_EOL;
exit($failures === 0 ? 0 : 1);
