<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/LoginBanner/LoginBannerImageException.php';
require_once dirname(__DIR__, 2) . '/app/LoginBanner/LoginBannerImagePipelineInterface.php';
require_once dirname(__DIR__, 2) . '/app/LoginBanner/LoginBannerImagePipeline.php';

use OneId\App\LoginBanner\LoginBannerImageException;
use OneId\App\LoginBanner\LoginBannerImagePipeline;

$checks = 0;
$failures = 0;
$report = static function (bool $passed, string $description) use (&$checks, &$failures): void {
    $checks++;
    echo ($passed ? 'PASS ' : 'FAIL ') . $description . PHP_EOL;
    if (!$passed) {
        $failures++;
    }
};
$expectCode = static function (callable $operation, string $code): bool {
    try {
        $operation();
    } catch (LoginBannerImageException $exception) {
        return $exception->getMessage() === $code;
    }
    return false;
};
$removeTree = static function (string $directory): void {
    if (!is_dir($directory)) {
        return;
    }
    foreach (new FilesystemIterator($directory, FilesystemIterator::SKIP_DOTS) as $entry) {
        if ($entry->isFile() && !$entry->isLink()) {
            unlink($entry->getPathname());
        } elseif ($entry->isDir() && !$entry->isLink()) {
            foreach (new FilesystemIterator($entry->getPathname(), FilesystemIterator::SKIP_DOTS) as $child) {
                if ($child->isFile() && !$child->isLink()) {
                    unlink($child->getPathname());
                }
            }
            rmdir($entry->getPathname());
        }
    }
    rmdir($directory);
};
$createPng = static function (string $path, int $width, int $height): void {
    $image = imagecreatetruecolor($width, $height);
    if ($image === false) {
        throw new RuntimeException('Unable to create test image');
    }
    for ($x = 0; $x < $width; $x += 100) {
        $color = imagecolorallocate($image, ($x / 10) % 255, 120, 190);
        imagefilledrectangle($image, $x, 0, min($width - 1, $x + 99), $height - 1, $color);
    }
    imagepng($image, $path, 6);
    imagedestroy($image);
};

$root = sys_get_temp_dir() . '/oneid-lb2-' . bin2hex(random_bytes(8));
$sourceDirectory = $root . '/source';
$stagingDirectory = $root . '/private-staging';
$publishedDirectory = $root . '/published';
mkdir($sourceDirectory, 0700, true);
$pipeline = new LoginBannerImagePipeline(static fn(string $path): bool => is_file($path));

try {
    $validSource = $sourceDirectory . '/valid.png';
    $createPng($validSource, 2000, 1000);
    $validUpload = [
        'error' => UPLOAD_ERR_OK,
        'tmp_name' => $validSource,
        'size' => filesize($validSource),
        'name' => '../../untrusted-name.php.png',
        'type' => 'application/octet-stream',
    ];
    $staged = $pipeline->stageUpload($validUpload, $stagingDirectory);
    $stagedInfo = getimagesize($staged['staged_path']);
    $report(
        preg_match('/^login_banner_[a-f0-9]{32}\.webp$/D', $staged['filename']) === 1
        && is_file($staged['staged_path'])
        && (string) ($stagedInfo['mime'] ?? '') === 'image/webp',
        'valid upload is decoded and staged with an immutable WebP name'
    );
    $report(
        (int) ($stagedInfo[0] ?? 0) === 1600
        && (int) ($stagedInfo[1] ?? 0) === 800
        && $staged['byte_size'] === filesize($staged['staged_path'])
        && $staged['byte_size'] <= 512000,
        'normalized output is exact 1600x800 and within the approved byte limit'
    );
    $report(
        preg_match('/^[a-f0-9]{64}$/D', $staged['sha256_digest']) === 1
        && hash_file('sha256', $staged['staged_path']) === $staged['sha256_digest'],
        'staged metadata contains the exact SHA-256 digest'
    );

    $stagedPath = $staged['staged_path'];
    $published = $pipeline->publish($staged, $publishedDirectory);
    $report(
        !file_exists($stagedPath)
        && is_file($published)
        && basename($published) === $staged['filename'],
        'publish atomically moves the staged file without overwrite'
    );

    $second = $pipeline->stageUpload($validUpload, $stagingDirectory);
    $second['filename'] = basename($published);
    $report(
        $expectCode(
            static fn() => $pipeline->publish($second, $publishedDirectory),
            'LB2_IMMUTABLE_TARGET_EXISTS'
        ) && is_file($second['staged_path']) && is_file($published),
        'immutable collision rejects publish and preserves both recoverable files'
    );
    $pipeline->discardStaged($second, $stagingDirectory);
    $report(!file_exists($second['staged_path']), 'staged compensation removes rejected temporary output');

    $third = $pipeline->stageUpload($validUpload, $stagingDirectory);
    $third['sha256_digest'] = str_repeat('0', 64);
    $report(
        $expectCode(
            static fn() => $pipeline->publish($third, $publishedDirectory),
            'LB2_NORMALIZED_ASSET_INVALID'
        ),
        'publish revalidates dimensions bytes and digest before mutation'
    );
    $pipeline->discardStaged($third, $stagingDirectory);

    $wrongRatio = $sourceDirectory . '/wrong-ratio.png';
    $createPng($wrongRatio, 1600, 900);
    $wrongRatioUpload = ['error' => UPLOAD_ERR_OK, 'tmp_name' => $wrongRatio, 'size' => filesize($wrongRatio)];
    $beforeInvalid = is_dir($stagingDirectory)
        ? iterator_count(new FilesystemIterator($stagingDirectory, FilesystemIterator::SKIP_DOTS))
        : 0;
    $report(
        $expectCode(
            static fn() => $pipeline->stageUpload($wrongRatioUpload, $stagingDirectory),
            'LB2_IMAGE_RATIO_INVALID'
        ),
        'non-2:1 source is rejected before normalization'
    );
    $afterInvalid = is_dir($stagingDirectory)
        ? iterator_count(new FilesystemIterator($stagingDirectory, FilesystemIterator::SKIP_DOTS))
        : 0;
    $report($beforeInvalid === $afterInvalid, 'validation failure leaves no staged orphan');

    $sizeMismatch = $validUpload;
    $sizeMismatch['size'] = (int) $validUpload['size'] + 1;
    $report(
        $expectCode(
            static fn() => $pipeline->stageUpload($sizeMismatch, $stagingDirectory),
            'LB2_UPLOAD_SIZE_INVALID'
        ),
        'reported and actual size mismatch is rejected'
    );

    $textFile = $sourceDirectory . '/fake.png';
    file_put_contents($textFile, 'not an image');
    $fakeUpload = ['error' => UPLOAD_ERR_OK, 'tmp_name' => $textFile, 'size' => filesize($textFile)];
    $report(
        $expectCode(
            static fn() => $pipeline->stageUpload($fakeUpload, $stagingDirectory),
            'LB2_IMAGE_TYPE_INVALID'
        ),
        'extension and client MIME cannot bypass content inspection'
    );

    $report(
        $expectCode(
            static fn() => $pipeline->discardPublished($validSource, $publishedDirectory),
            'LB2_COMPENSATION_TARGET_INVALID'
        ) && is_file($validSource),
        'compensation rejects paths outside the approved asset directory'
    );
    $pipeline->discardPublished($published, $publishedDirectory);
    $report(!file_exists($published), 'published compensation removes only the exact supplied asset path');
} finally {
    $removeTree($root);
}

echo "RESULT checks={$checks} failures={$failures}\n";
exit($failures === 0 ? 0 : 1);
