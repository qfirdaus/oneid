<?php

declare(strict_types=1);

namespace OneId\App\Auth\UserMfa;

interface UserMfaAuditWriterInterface
{
    public function write(
        string $event,
        string $targetUserId,
        string $actorUserId,
        string $outcome,
        string $reason,
        string $reference,
        string $correlationId,
        string $ipAddress
    ): int;
}
