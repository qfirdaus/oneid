<?php

namespace OneId\App\User;

use RuntimeException;

final class UserSecurityActionException extends RuntimeException
{
    public function __construct(
        public readonly string $reason,
        public readonly string $correlationId
    ) {
        parent::__construct($reason);
    }
}
