<?php
declare(strict_types=1);
namespace OneId\App\Sync\Odl;

final class OdlShadowPreviewConfig
{
    private function __construct(
        public readonly bool $enabled,
        public readonly int $staffBaselineRows,
        public readonly int $odlBaselineRows,
        public readonly int $ugBaselineRows
    ) {}

    public static function fromPrivateRuntime(): self
    {
        $enabled = self::secret('ONEID_ODL_SHADOW_PREVIEW_ENABLED', 'false');
        $staff = self::secret('ONEID_ODL_SHADOW_STAFF_BASELINE_ROWS', '');
        $odl = self::secret('ONEID_ODL_SHADOW_ODL_BASELINE_ROWS', '');
        $ug = self::secret('ONEID_ODL_SHADOW_UG_BASELINE_ROWS', '');
        return self::fromValues($enabled, $staff, $odl, $ug);
    }

    public static function fromValues(
        string $enabled,
        string $staff,
        string $odl,
        string $ug
    ): self
    {
        if (!in_array($enabled, ['false', 'true'], true)) {
            throw new \RuntimeException('ODL_SHADOW_PREVIEW_FLAG_INVALID');
        }
        if ($enabled === 'false') {
            return new self(false, 0, 0, 0);
        }
        if (preg_match('/\A[1-9][0-9]*\z/', $staff) !== 1
            || preg_match('/\A[1-9][0-9]*\z/', $odl) !== 1
            || preg_match('/\A[1-9][0-9]*\z/', $ug) !== 1
        ) {
            throw new \RuntimeException('ODL_SHADOW_BASELINE_INVALID');
        }
        return new self(true, (int) $staff, (int) $odl, (int) $ug);
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
