<?php
declare(strict_types=1);
namespace OneId\App\Sync\Odl;

final class OdlOperationalConfig
{
    private function __construct(
        public readonly bool $previewEnabled,
        public readonly bool $applyEnabled
    ) {}

    public static function fromPrivateRuntime(): self
    {
        return self::fromValues(
            (string) \oneid_config('ONEID_ODL_OPERATIONAL_PREVIEW_ENABLED', 'false'),
            (string) \oneid_config('ONEID_ODL_OPERATIONAL_APPLY_ENABLED', 'false')
        );
    }

    public static function fromValues(string $preview, string $apply): self
    {
        foreach ([$preview, $apply] as $flag) {
            if (!in_array($flag, ['true', 'false'], true)) {
                throw new \RuntimeException('ODL_OPERATIONAL_FLAG_INVALID');
            }
        }
        if ($apply === 'true' && $preview !== 'true') {
            throw new \RuntimeException('ODL_OPERATIONAL_FLAG_COMBINATION_INVALID');
        }
        return new self($preview === 'true', $apply === 'true');
    }

    public function assertPreviewEnabled(): void
    {
        if (!$this->previewEnabled) {
            throw new \RuntimeException('ODL_OPERATIONAL_PREVIEW_DISABLED');
        }
    }

    public function assertApplyEnabled(): void
    {
        if (!$this->applyEnabled) {
            throw new \RuntimeException('ODL_OPERATIONAL_APPLY_DISABLED');
        }
    }
}
