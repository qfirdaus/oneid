<?php

declare(strict_types=1);

namespace OneId\App\LoginBanner;

use Closure;
use finfo;
use Throwable;

final class LoginBannerImagePipeline implements LoginBannerImagePipelineInterface
{
    private const MAX_INPUT_BYTES = 5_242_880;
    private const MAX_OUTPUT_BYTES = 512_000;
    private const MAX_WIDTH = 4096;
    private const MAX_HEIGHT = 2048;
    private const MAX_PIXELS = 16_000_000;
    private const OUTPUT_WIDTH = 1600;
    private const OUTPUT_HEIGHT = 800;
    private const RATIO_TOLERANCE = 0.02;
    private const ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp'];

    /** @var Closure(string):bool */
    private readonly Closure $uploadVerifier;

    public function __construct(?Closure $uploadVerifier = null)
    {
        $this->uploadVerifier = $uploadVerifier ?? static fn(string $path): bool => is_uploaded_file($path);
    }

    /**
     * @param array<string,mixed>|null $file
     * @return array{filename:string,staged_path:string,mime_type:string,width:int,height:int,byte_size:int,sha256_digest:string}
     */
    public function stageUpload(?array $file, string $stagingDirectory): array
    {
        $source = $this->validateUpload($file);
        $this->ensureDirectory($stagingDirectory, 0700, 'LB2_STAGING_DIRECTORY_UNAVAILABLE');
        $stagedPath = rtrim($stagingDirectory, '/\\')
            . DIRECTORY_SEPARATOR . '.pending_login_banner_' . bin2hex(random_bytes(16));
        try {
            $metadata = $this->normalizeToWebp($source, $stagedPath);
            if (!chmod($stagedPath, 0600)) {
                throw new LoginBannerImageException('LB2_STAGED_PERMISSION_FAILED');
            }
            return [
                'filename' => 'login_banner_' . bin2hex(random_bytes(16)) . '.webp',
                'staged_path' => $stagedPath,
                'mime_type' => 'image/webp',
                'width' => self::OUTPUT_WIDTH,
                'height' => self::OUTPUT_HEIGHT,
                'byte_size' => $metadata['byte_size'],
                'sha256_digest' => $metadata['sha256_digest'],
            ];
        } catch (Throwable $exception) {
            $this->removeFile($stagedPath);
            throw $exception;
        }
    }

    /** @param array<string,mixed> $staged */
    public function publish(array $staged, string $publishedDirectory): string
    {
        $filename = (string) ($staged['filename'] ?? '');
        $stagedPath = (string) ($staged['staged_path'] ?? '');
        if (preg_match('/^login_banner_[a-f0-9]{32}\.webp$/D', $filename) !== 1
            || $stagedPath === ''
            || !is_file($stagedPath)
            || is_link($stagedPath)
        ) {
            throw new LoginBannerImageException('LB2_STAGED_ASSET_INVALID');
        }
        $this->assertNormalizedFile($stagedPath, $staged);
        $this->ensureDirectory($publishedDirectory, 0755, 'LB2_PUBLISH_DIRECTORY_UNAVAILABLE');
        $target = rtrim($publishedDirectory, '/\\') . DIRECTORY_SEPARATOR . $filename;
        if (file_exists($target) || is_link($target)) {
            throw new LoginBannerImageException('LB2_IMMUTABLE_TARGET_EXISTS');
        }
        if (!rename($stagedPath, $target)) {
            $this->removeFile($target);
            throw new LoginBannerImageException('LB2_ATOMIC_PUBLISH_FAILED');
        }
        if (!chmod($target, 0644)) {
            $this->removeFile($target);
            throw new LoginBannerImageException('LB2_PUBLISHED_PERMISSION_FAILED');
        }
        return $target;
    }

    /** @param array<string,mixed> $staged */
    public function discardStaged(array $staged, string $stagingDirectory): void
    {
        $path = (string) ($staged['staged_path'] ?? '');
        if ($path !== '') {
            $this->assertCompensationPath(
                $path,
                $stagingDirectory,
                '/^\.pending_login_banner_[a-f0-9]{32}$/D'
            );
            $this->removeFile($path);
        }
    }

    public function discardPublished(string $publishedPath, string $publishedDirectory): void
    {
        if ($publishedPath !== '') {
            $this->assertCompensationPath(
                $publishedPath,
                $publishedDirectory,
                '/^login_banner_[a-f0-9]{32}\.webp$/D'
            );
            $this->removeFile($publishedPath);
        }
    }

    /** @param array<string,mixed>|null $file */
    private function validateUpload(?array $file): string
    {
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            throw new LoginBannerImageException('LB2_UPLOAD_REQUIRED');
        }
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new LoginBannerImageException('LB2_UPLOAD_TRANSPORT_FAILED');
        }
        $source = (string) ($file['tmp_name'] ?? '');
        $reportedSize = (int) ($file['size'] ?? 0);
        if ($source === '' || !is_file($source) || is_link($source)
            || !($this->uploadVerifier)($source)
        ) {
            throw new LoginBannerImageException('LB2_UPLOAD_SOURCE_INVALID');
        }
        $actualSize = filesize($source);
        if ($actualSize === false || $actualSize < 1 || $reportedSize < 1
            || $actualSize > self::MAX_INPUT_BYTES || $reportedSize > self::MAX_INPUT_BYTES
            || $actualSize !== $reportedSize
        ) {
            throw new LoginBannerImageException('LB2_UPLOAD_SIZE_INVALID');
        }
        if (!class_exists(finfo::class) || !function_exists('getimagesize')) {
            throw new LoginBannerImageException('LB2_IMAGE_INSPECTION_UNAVAILABLE');
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($source);
        $info = @getimagesize($source);
        if (!is_string($mime) || !in_array($mime, self::ALLOWED_MIME, true)
            || !is_array($info) || (string) ($info['mime'] ?? '') !== $mime
        ) {
            throw new LoginBannerImageException('LB2_IMAGE_TYPE_INVALID');
        }
        $width = (int) ($info[0] ?? 0);
        $height = (int) ($info[1] ?? 0);
        if ($width < self::OUTPUT_WIDTH || $height < self::OUTPUT_HEIGHT
            || $width > self::MAX_WIDTH || $height > self::MAX_HEIGHT
            || ($width * $height) > self::MAX_PIXELS
        ) {
            throw new LoginBannerImageException('LB2_IMAGE_DIMENSIONS_INVALID');
        }
        $ratio = $width / $height;
        if (abs($ratio - 2.0) > self::RATIO_TOLERANCE) {
            throw new LoginBannerImageException('LB2_IMAGE_RATIO_INVALID');
        }
        $contents = file_get_contents($source);
        if ($contents === false || $this->isAnimated($contents, $mime)) {
            throw new LoginBannerImageException('LB2_ANIMATED_IMAGE_REJECTED');
        }
        return $source;
    }

    /** @return array{byte_size:int,sha256_digest:string} */
    private function normalizeToWebp(string $sourcePath, string $targetPath): array
    {
        if (!extension_loaded('gd') || !function_exists('imagecreatefromstring')
            || !function_exists('imagewebp')
        ) {
            throw new LoginBannerImageException('LB2_IMAGE_NORMALIZATION_UNAVAILABLE');
        }
        $contents = file_get_contents($sourcePath);
        $source = $contents === false ? false : @imagecreatefromstring($contents);
        if ($source === false) {
            throw new LoginBannerImageException('LB2_IMAGE_DECODE_FAILED');
        }
        try {
            $source = $this->orientJpeg($sourcePath, $source);
            $width = imagesx($source);
            $height = imagesy($source);
            if ($width < 1 || $height < 1 || abs(($width / $height) - 2.0) > self::RATIO_TOLERANCE) {
                throw new LoginBannerImageException('LB2_ORIENTED_RATIO_INVALID');
            }
            $canvas = imagecreatetruecolor(self::OUTPUT_WIDTH, self::OUTPUT_HEIGHT);
            if ($canvas === false) {
                throw new LoginBannerImageException('LB2_CANVAS_CREATE_FAILED');
            }
            try {
                $background = imagecolorallocate($canvas, 255, 255, 255);
                imagefill($canvas, 0, 0, $background);
                if (!imagecopyresampled(
                    $canvas,
                    $source,
                    0,
                    0,
                    0,
                    0,
                    self::OUTPUT_WIDTH,
                    self::OUTPUT_HEIGHT,
                    $width,
                    $height
                )) {
                    throw new LoginBannerImageException('LB2_IMAGE_RESAMPLE_FAILED');
                }
                $written = false;
                foreach ([82, 76, 70, 64, 58, 52] as $quality) {
                    if (!@imagewebp($canvas, $targetPath, $quality)) {
                        continue;
                    }
                    clearstatcache(true, $targetPath);
                    $size = filesize($targetPath);
                    if ($size !== false && $size > 0 && $size <= self::MAX_OUTPUT_BYTES) {
                        $written = true;
                        break;
                    }
                }
                if (!$written) {
                    throw new LoginBannerImageException('LB2_NORMALIZED_SIZE_EXCEEDED');
                }
            } finally {
                imagedestroy($canvas);
            }
        } finally {
            imagedestroy($source);
        }
        $this->assertNormalizedFile($targetPath, []);
        $size = filesize($targetPath);
        $digest = hash_file('sha256', $targetPath);
        if ($size === false || $digest === false) {
            throw new LoginBannerImageException('LB2_NORMALIZED_METADATA_FAILED');
        }
        return ['byte_size' => $size, 'sha256_digest' => $digest];
    }

    private function orientJpeg(string $sourcePath, \GdImage $image): \GdImage
    {
        $info = @getimagesize($sourcePath);
        if (($info['mime'] ?? '') !== 'image/jpeg' || !function_exists('exif_read_data')) {
            return $image;
        }
        $exif = @exif_read_data($sourcePath);
        $orientation = (int) ($exif['Orientation'] ?? 1);
        $degrees = match ($orientation) {3 => 180, 6 => -90, 8 => 90, default => 0};
        if ($degrees === 0) {
            return $image;
        }
        $rotated = imagerotate($image, $degrees, 0);
        if ($rotated === false) {
            throw new LoginBannerImageException('LB2_IMAGE_ORIENTATION_FAILED');
        }
        imagedestroy($image);
        return $rotated;
    }

    /** @param array<string,mixed> $expected */
    private function assertNormalizedFile(string $path, array $expected): void
    {
        $info = @getimagesize($path);
        $size = is_file($path) ? filesize($path) : false;
        $digest = is_file($path) ? hash_file('sha256', $path) : false;
        if (!is_array($info)
            || (string) ($info['mime'] ?? '') !== 'image/webp'
            || (int) ($info[0] ?? 0) !== self::OUTPUT_WIDTH
            || (int) ($info[1] ?? 0) !== self::OUTPUT_HEIGHT
            || $size === false || $size < 1 || $size > self::MAX_OUTPUT_BYTES
            || $digest === false
            || (isset($expected['byte_size']) && (int) $expected['byte_size'] !== $size)
            || (isset($expected['sha256_digest']) && !hash_equals((string) $expected['sha256_digest'], $digest))
        ) {
            throw new LoginBannerImageException('LB2_NORMALIZED_ASSET_INVALID');
        }
    }

    private function isAnimated(string $contents, string $mime): bool
    {
        if ($mime === 'image/webp') {
            return str_contains(substr($contents, 0, 64), 'ANIM');
        }
        if ($mime === 'image/png') {
            return str_contains(substr($contents, 0, 512), 'acTL');
        }
        return false;
    }

    private function ensureDirectory(string $directory, int $mode, string $code): void
    {
        if ($directory === '' || is_link($directory)
            || (!is_dir($directory) && !mkdir($directory, $mode, true))
            || !is_writable($directory)
        ) {
            throw new LoginBannerImageException($code);
        }
    }

    private function removeFile(string $path): void
    {
        if (is_file($path) && !is_link($path) && !unlink($path)) {
            throw new LoginBannerImageException('LB2_COMPENSATION_CLEANUP_FAILED');
        }
    }

    private function assertCompensationPath(string $path, string $directory, string $filenamePattern): void
    {
        $resolvedDirectory = realpath($directory);
        $resolvedParent = realpath(dirname($path));
        if ($resolvedDirectory === false
            || $resolvedParent === false
            || !hash_equals($resolvedDirectory, $resolvedParent)
            || preg_match($filenamePattern, basename($path)) !== 1
            || is_link($path)
        ) {
            throw new LoginBannerImageException('LB2_COMPENSATION_TARGET_INVALID');
        }
    }
}
