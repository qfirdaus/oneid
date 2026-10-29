<?php
declare(strict_types=1);

namespace OneId\App\Sync\Apel;

final class ApelShadowPreviewConfig
{
    private function __construct(
        public readonly bool $enabled,
        public readonly int $baselineRows
    ) {}

    public static function fromPrivateRuntime(): self
    {
        return self::fromValues(
            self::secret('ONEID_APEL_SHADOW_PREVIEW_ENABLED', 'false'),
            self::secret('ONEID_APEL_SHADOW_BASELINE_ROWS', '')
        );
    }

    public static function fromValues(string $enabled, string $baselineRows): self
    {
        if (!in_array($enabled, ['false', 'true'], true)) {
            throw new \RuntimeException('APEL_SHADOW_PREVIEW_FLAG_INVALID');
        }
        if ($enabled === 'false') {
            return new self(false, 0);
        }
        if (preg_match('/\A[1-9][0-9]*\z/', $baselineRows) !== 1) {
            throw new \RuntimeException('APEL_SHADOW_BASELINE_INVALID');
        }
        return new self(true, (int) $baselineRows);
    }

    private static function secret(string $key, string $default): string
    {
        try {
            return trim((string) \oneid_secret($key));
        } catch (\Throwable) {
            return $default;
        }
    }
}
