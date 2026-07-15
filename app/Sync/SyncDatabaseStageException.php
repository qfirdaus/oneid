<?php

namespace OneId\App\Sync;

use PDOException;
use RuntimeException;

/** Sanitized database failure metadata. Never carries SQL or bound values. */
final class SyncDatabaseStageException extends RuntimeException
{
    public function __construct(
        public readonly string $stage,
        public readonly string $sqlState,
        public readonly int $driverCode,
        PDOException $previous
    ) {
        parent::__construct('SYNC_DATABASE_WRITE_FAILED', 0, $previous);
    }

    public static function fromPdo(string $stage, PDOException $exception): self
    {
        $safeStage = preg_match('/^[a-z][a-z0-9_]{1,63}$/', $stage) === 1
            ? $stage : 'unknown_database_stage';
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode() ?: 'unknown');
        if (preg_match('/^[A-Z0-9]{5}$/', $sqlState) !== 1) $sqlState = 'unknown';
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);
        return new self($safeStage, $sqlState, $driverCode, $exception);
    }
}
