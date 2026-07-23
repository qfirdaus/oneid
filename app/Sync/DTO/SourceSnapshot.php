<?php
declare(strict_types=1);
namespace OneId\App\Sync\DTO;

final class SourceSnapshot
{
    /** @param list<array<string,mixed>> $rows */
    public function __construct(
        public readonly string $sourceCode,
        public readonly string $sourceFamily,
        public readonly string $status,
        public readonly array $rows,
        public readonly ?int $baselineRows,
        public readonly int $invalidIdentityRows = 0,
        public readonly bool $required = true
    ) {
        if (preg_match('/\A[A-Z][A-Z0-9_]{2,49}\z/', $sourceCode) !== 1) {
            throw new \InvalidArgumentException('SOURCE_SNAPSHOT_CODE_INVALID');
        }
        if (!in_array($sourceFamily, ['staff', 'student'], true)) {
            throw new \InvalidArgumentException('SOURCE_SNAPSHOT_FAMILY_INVALID');
        }
        if (!in_array($status, ['success', 'connection_failed', 'query_failed'], true)) {
            throw new \InvalidArgumentException('SOURCE_SNAPSHOT_STATUS_INVALID');
        }
        if ($baselineRows !== null && $baselineRows < 0) {
            throw new \InvalidArgumentException('SOURCE_SNAPSHOT_BASELINE_INVALID');
        }
        if ($invalidIdentityRows < 0 || $invalidIdentityRows > count($rows)) {
            throw new \InvalidArgumentException('SOURCE_SNAPSHOT_INVALID_COUNT');
        }
    }
}
