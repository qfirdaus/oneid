<?php
declare(strict_types=1);

namespace OneId\App\LoginBanner;

use Closure;

final class LoginBannerPublicResolver
{
    /** @var Closure(array<string,mixed>,string):bool */
    private readonly Closure $assetValidator;

    public function __construct(
        private readonly LoginBannerPersistenceInterface $persistence,
        private readonly string $environment,
        private readonly string $assetDirectory,
        private readonly string $publicBaseUrl = 'login_banners',
        ?Closure $assetValidator = null
    ) {
        $this->assetValidator = $assetValidator ?? $this->validateAsset(...);
    }

    /** @return list<array{banner_id:int,src:string,alt:string,width:int,height:int}> */
    public function resolve(string $locale, string $effectiveAtUtc): array
    {
        if (!in_array($locale, ['ms', 'en'], true)
            || preg_match('/^[a-z][a-z0-9_-]{1,31}$/D', $this->environment) !== 1
            || preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $effectiveAtUtc) !== 1
            || ($this->persistence->schemaStatus()['available'] ?? false) !== true
        ) {
            return [];
        }
        $resolved = [];
        $seen = [];
        foreach ($this->persistence->publishedForLocale($this->environment, $locale, $effectiveAtUtc) as $row) {
            $id = (int) ($row['banner_id'] ?? 0);
            $filename = (string) ($row['image_filename'] ?? '');
            $alt = trim((string) ($row['alt_text'] ?? ''));
            if ($id < 1 || isset($seen[$id])
                || preg_match('/^login_banner_[a-f0-9]{32}\.webp$/D', $filename) !== 1
                || mb_strlen($alt) < 5 || mb_strlen($alt) > 160
                || !($this->assetValidator)($row, $this->assetDirectory)
            ) {
                continue;
            }
            $seen[$id] = true;
            $resolved[] = [
                'banner_id' => $id,
                'src' => rtrim($this->publicBaseUrl, '/') . '/' . rawurlencode($filename),
                'alt' => $alt,
                'width' => 1600,
                'height' => 800,
            ];
            if (count($resolved) === 5) {
                break;
            }
        }
        return $resolved;
    }

    /** @param array<string,mixed> $row */
    private function validateAsset(array $row, string $directory): bool
    {
        $filename = (string) ($row['image_filename'] ?? '');
        $base = realpath($directory);
        $path = $base === false ? false : realpath($base . DIRECTORY_SEPARATOR . $filename);
        if ($base === false || $path === false || is_link($path) || !is_file($path)
            || !str_starts_with($path, $base . DIRECTORY_SEPARATOR)
            || (int) ($row['image_width'] ?? 0) !== 1600
            || (int) ($row['image_height'] ?? 0) !== 800
            || (int) ($row['byte_size'] ?? 0) < 1
            || (int) ($row['byte_size'] ?? 0) > 512000
            || filesize($path) !== (int) $row['byte_size']
            || preg_match('/^[a-f0-9]{64}$/D', (string) ($row['sha256_digest'] ?? '')) !== 1
        ) {
            return false;
        }
        $actualDigest = hash_file('sha256', $path);
        if (!is_string($actualDigest)
            || !hash_equals((string) $row['sha256_digest'], $actualDigest)
        ) {
            return false;
        }
        $image = @getimagesize($path);
        return is_array($image) && ($image[0] ?? 0) === 1600 && ($image[1] ?? 0) === 800
            && ($image['mime'] ?? '') === 'image/webp';
    }
}
