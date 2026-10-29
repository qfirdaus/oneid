<?php
declare(strict_types=1);
namespace OneId\App\Sync\Apel;

final class ApelPilotConfig
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
            self::value('ONEID_APEL_PILOT_PREVIEW_ENABLED', 'false'),
            self::value('ONEID_APEL_PILOT_APPLY_ENABLED', 'false'),
            self::value('ONEID_APEL_PILOT_IDENTITY_DIGESTS', '')
        );
    }

    public static function fromValues(string $preview, string $apply, string $digests): self
    {
        foreach ([$preview, $apply] as $flag) {
            if (!in_array($flag, ['true', 'false'], true)) {
                throw new \RuntimeException('APEL_PILOT_FLAG_INVALID');
            }
        }
        if ($apply === 'true' && $preview !== 'true') {
            throw new \RuntimeException('APEL_PILOT_FLAG_COMBINATION_INVALID');
        }
        $allowlist = $digests === '' ? [] : array_values(array_unique(array_filter(
            array_map('trim', explode(',', strtolower($digests)))
        )));
        foreach ($allowlist as $digest) {
            if (preg_match('/^[a-f0-9]{64}$/', $digest) !== 1) {
                throw new \RuntimeException('APEL_PILOT_ALLOWLIST_INVALID');
            }
        }
        if ($preview === 'true' && count($allowlist) !== 3) {
            throw new \RuntimeException('APEL_PILOT_SCOPE_MUST_EQUAL_THREE');
        }
        return new self($preview === 'true', $apply === 'true', $allowlist);
    }

    private static function value(string $key, string $default): string
    {
        try { return trim((string) \oneid_secret($key)); }
        catch (\Throwable) { return $default; }
    }
}
