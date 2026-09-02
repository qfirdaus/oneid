<?php

declare(strict_types=1);

namespace OneId\App\Sync;

use OneId\App\Sync\Odl\OdlStudentSource;
use OneId\App\Sync\Odl\StaffSource;
use OneId\App\Sync\Odl\UgStudentSource;
use RuntimeException;

final class SyncCronConfig
{
    public const SOURCE_CODES = [
        StaffSource::SOURCE_CODE,
        UgStudentSource::SOURCE_CODE,
        OdlStudentSource::SOURCE_CODE,
    ];

    /** @param list<string> $sources @param array<string,array<string,int>> $limits */
    private function __construct(
        public readonly bool $enabled,
        public readonly bool $dryRun,
        public readonly array $sources,
        private readonly array $limits,
        private readonly int $maxDeactivate,
        public readonly string $serviceIdentity,
        public readonly bool $allowAllSafeChanges
    ) {}

    public static function fromEnvironment(): self
    {
        $read = static fn(string $key, string $default): string =>
            (string) \oneid_config($key, $default);
        $sources = $read(
            'ONEID_SYNC_CRON_SOURCES',
            implode(',', self::SOURCE_CODES)
        );
        $limits = [];
        $defaults = [
            StaffSource::SOURCE_CODE => [50, 250, 20, 300],
            UgStudentSource::SOURCE_CODE => [50, 250, 20, 300],
            OdlStudentSource::SOURCE_CODE => [20, 100, 10, 120],
        ];
        foreach ($defaults as $source => [$new, $update, $reactivate, $total]) {
            $limits[$source] = [
                'New' => $read("ONEID_SYNC_CRON_MAX_NEW_{$source}", (string) $new),
                'Update' => $read("ONEID_SYNC_CRON_MAX_UPDATE_{$source}", (string) $update),
                'Reactivate' => $read("ONEID_SYNC_CRON_MAX_REACTIVATE_{$source}", (string) $reactivate),
                'Total' => $read("ONEID_SYNC_CRON_MAX_TOTAL_{$source}", (string) $total),
            ];
        }
        return self::fromValues(
            $read('ONEID_SYNC_CRON_ENABLED', 'false'),
            $read('ONEID_SYNC_CRON_DRY_RUN', 'true'),
            $sources,
            $read('ONEID_SYNC_CRON_MAX_DEACTIVATE', '0'),
            $limits,
            $read('ONEID_SYNC_CRON_SERVICE_IDENTITY', 'ONEID-CRON'),
            $read('ONEID_SYNC_CRON_ALLOW_ALL_SAFE_CHANGES', 'false')
        );
    }

    /** @param array<string,array<string,string>> $limits */
    public static function fromValues(
        string $enabled,
        string $dryRun,
        string $sourceList,
        string $maxDeactivate,
        array $limits,
        string $serviceIdentity = 'ONEID-CRON',
        string $allowAllSafeChanges = 'false'
    ): self {
        foreach ([$enabled, $dryRun, $allowAllSafeChanges] as $flag) {
            if (!in_array($flag, ['true', 'false'], true)) {
                throw new RuntimeException('SYNC_CRON_FLAG_INVALID');
            }
        }
        if (preg_match('/^(?:0|[1-9][0-9]{0,4})$/', $maxDeactivate) !== 1) {
            throw new RuntimeException('SYNC_CRON_DEACTIVATE_LIMIT_INVALID');
        }
        if ($sourceList === '' || preg_match('/^[A-Z0-9_]+(?:,[A-Z0-9_]+)*$/', $sourceList) !== 1) {
            throw new RuntimeException('SYNC_CRON_SOURCES_INVALID');
        }
        $sources = explode(',', $sourceList);
        if (count($sources) !== count(array_unique($sources))
            || array_diff($sources, self::SOURCE_CODES) !== []
        ) {
            throw new RuntimeException('SYNC_CRON_SOURCES_INVALID');
        }
        $identity = trim($serviceIdentity);
        // ext_data_temp_header.triggered_by is a legacy short identity field.
        if ($identity === '' || strlen($identity) > 20
            || preg_match('/^[A-Za-z0-9 _.-]+$/', $identity) !== 1
        ) {
            throw new RuntimeException('SYNC_CRON_IDENTITY_INVALID');
        }
        $normalized = [];
        foreach (self::SOURCE_CODES as $source) {
            foreach (['New', 'Update', 'Reactivate', 'Total'] as $action) {
                $value = $limits[$source][$action] ?? null;
                if (!is_string($value) || preg_match('/^(?:0|[1-9][0-9]{0,4})$/', $value) !== 1) {
                    throw new RuntimeException('SYNC_CRON_THRESHOLD_INVALID');
                }
                $normalized[$source][$action] = (int) $value;
            }
            if ($normalized[$source]['Total'] < max(
                $normalized[$source]['New'],
                $normalized[$source]['Update'],
                $normalized[$source]['Reactivate']
            )) {
                throw new RuntimeException('SYNC_CRON_THRESHOLD_INVALID');
            }
        }
        return new self(
            $enabled === 'true',
            $dryRun === 'true',
            $sources,
            $normalized,
            (int) $maxDeactivate,
            $identity,
            $allowAllSafeChanges === 'true'
        );
    }

    /** @param array{New:int,Update:int,Deactivate:int,Reactivate:int} $counts */
    public function blockingCode(string $source, array $counts): ?string
    {
        if (!in_array($source, $this->sources, true) || !isset($this->limits[$source])) {
            return 'SYNC_CRON_SOURCE_NOT_ENABLED';
        }
        foreach (['New', 'Update', 'Deactivate', 'Reactivate'] as $action) {
            if (!isset($counts[$action]) || !is_int($counts[$action]) || $counts[$action] < 0) {
                throw new RuntimeException('SYNC_CRON_COUNTS_INVALID');
            }
        }
        if ($this->allowAllSafeChanges) return null;
        if ($counts['Deactivate'] > $this->maxDeactivate) {
            return 'SYNC_CRON_DEACTIVATION_LIMIT_EXCEEDED';
        }
        foreach (['New', 'Update', 'Reactivate'] as $action) {
            if ($counts[$action] > $this->limits[$source][$action]) {
                return 'SYNC_CRON_' . strtoupper($action) . '_LIMIT_EXCEEDED';
            }
        }
        return array_sum($counts) > $this->limits[$source]['Total']
            ? 'SYNC_CRON_TOTAL_LIMIT_EXCEEDED'
            : null;
    }
}
