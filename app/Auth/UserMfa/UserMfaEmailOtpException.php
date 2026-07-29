<?php

declare(strict_types=1);

namespace OneId\App\Auth\UserMfa;

use RuntimeException;

final class UserMfaEmailOtpException extends RuntimeException
{
    public function __construct(
        public readonly string $reason,
        public readonly string $correlationId
    ) {
        parent::__construct($reason);
    }
}
