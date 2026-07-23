<?php
declare(strict_types=1);
namespace OneId\App\Sync\Odl;

final class OdlPilotConfig
{
    /** @param list<string> $allowedIdentityDigests */
    private function __construct(
        public readonly bool $previewEnabled,
        public readonly bool $applyEnabled,
        public readonly array $allowedIdentityDigests
    ) {}

    public static function fromPrivateRuntime(): self
    {
        return self::fromValues(
            (string) \oneid_config('ONEID_ODL_PILOT_PREVIEW_ENABLED', 'false'),
            (string) \oneid_config('ONEID_ODL_PILOT_APPLY_ENABLED', 'false'),
            (string) \oneid_secret('ONEID_ODL_PILOT_IDENTITY_DIGESTS', false)
        );
    }

    public static function fromValues(
        string $previewEnabled,
        string $applyEnabled,
        string $identityDigests
    ): self {
        foreach ([$previewEnabled, $applyEnabled] as $flag) {
            if (!in_array($flag, ['true', 'false'], true)) {
                throw new \RuntimeException('ODL_PILOT_FLAG_INVALID');
            }
        }
        if ($applyEnabled === 'true' && $previewEnabled !== 'true') {
            throw new \RuntimeException('ODL_PILOT_FLAG_COMBINATION_INVALID');
        }
        $digests = $identityDigests === ''
            ? []
            : array_values(array_unique(array_filter(array_map(
                'trim',
                explode(',', strtolower($identityDigests))
            ))));
        foreach ($digests as $digest) {
            if (preg_match('/^[a-f0-9]{64}$/', $digest) !== 1) {
                throw new \RuntimeException('ODL_PILOT_ALLOWLIST_INVALID');
            }
        }
        if ($previewEnabled === 'true' && count($digests) !== 3) {
            throw new \RuntimeException('ODL_PILOT_SCOPE_MUST_EQUAL_THREE');
        }
        return new self(
            $previewEnabled === 'true',
            $applyEnabled === 'true',
            $digests
        );
    }
}
