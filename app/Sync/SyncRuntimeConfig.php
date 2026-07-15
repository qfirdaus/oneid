<?php

namespace OneId\App\Sync;

use RuntimeException;

/** Pure, strict S4 runtime flag decision. No caller uses it until S4 wiring. */
final class SyncRuntimeConfig
{
    private function __construct(
        public readonly bool $applyEnabled,
        public readonly string $engine
    ) {
    }

    public static function fromEnvironment(): self
    {
        $apply = function_exists('oneid_config')
            ? oneid_config('ONEID_SYNC_APPLY_ENABLED')
            : getenv('ONEID_SYNC_APPLY_ENABLED');
        $engine = function_exists('oneid_config')
            ? oneid_config('ONEID_SYNC_ENGINE')
            : getenv('ONEID_SYNC_ENGINE');

        return self::fromValues(
            $apply === false ? null : $apply,
            $engine === false ? null : $engine
        );
    }

    public static function fromValues(?string $apply, ?string $engine): self
    {
        $applyValue = $apply === null || $apply === '' ? 'false' : $apply;
        $engineValue = $engine === null || $engine === '' ? 'disabled' : $engine;

        if (!in_array($applyValue, ['false', 'true'], true)) {
            throw new RuntimeException('SYNC_APPLY_FLAG_INVALID');
        }
        if (!in_array($engineValue, ['disabled', 'safe'], true)) {
            throw new RuntimeException('SYNC_ENGINE_INVALID');
        }

        $validDisabled = $applyValue === 'false' && $engineValue === 'disabled';
        $validSafe = $applyValue === 'true' && $engineValue === 'safe';
        if (!$validDisabled && !$validSafe) {
            throw new RuntimeException('SYNC_FLAG_COMBINATION_INVALID');
        }

        return new self($applyValue === 'true', $engineValue);
    }

    public function canApply(): bool
    {
        return $this->applyEnabled && $this->engine === 'safe';
    }
}
