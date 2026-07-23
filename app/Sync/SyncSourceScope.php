<?php
declare(strict_types=1);
namespace OneId\App\Sync;

use OneId\App\Sync\Contracts\ExternalUserSourceInterface;
use OneId\App\Sync\Odl\StaffSource;
use OneId\App\Sync\Odl\UgStudentSource;

final class SyncSourceScope
{
    /** @param list<int> $categoryIds */
    private function __construct(
        public readonly string $sourceCode,
        public readonly ExternalUserSourceInterface $source,
        public readonly array $categoryIds,
        public readonly int $baselineRows,
        public readonly bool $provenanceEnforced
    ) {}

    public static function fromCode(string $sourceCode): self
    {
        [$source, $categories, $baselineKey] = match ($sourceCode) {
            StaffSource::SOURCE_CODE => [
                new StaffSource(),
                [2, 3],
                'ONEID_ODL_SHADOW_STAFF_BASELINE_ROWS',
            ],
            UgStudentSource::SOURCE_CODE => [
                new UgStudentSource(),
                [10, 11, 12],
                'ONEID_ODL_SHADOW_UG_BASELINE_ROWS',
            ],
            default => throw new \RuntimeException('SYNC_SOURCE_INVALID'),
        };
        $rawBaseline = (string) \oneid_config($baselineKey, '');
        if (preg_match('/^[1-9][0-9]*$/', $rawBaseline) !== 1) {
            throw new \RuntimeException('SYNC_SOURCE_BASELINE_INVALID');
        }
        $staffProvenance = (string) \oneid_config(
            'ONEID_SYNC_STAFF_PROVENANCE_ENABLED',
            'false'
        );
        if (!in_array($staffProvenance, ['true', 'false'], true)) {
            throw new \RuntimeException('SYNC_STAFF_PROVENANCE_FLAG_INVALID');
        }
        return new self(
            $sourceCode,
            $source,
            $categories,
            (int) $rawBaseline,
            $sourceCode === UgStudentSource::SOURCE_CODE
                || ($sourceCode === StaffSource::SOURCE_CODE
                    && $staffProvenance === 'true')
        );
    }
}
