<?php

declare(strict_types=1);

namespace OneId\App\Auth;

use RuntimeException;

final class AdminStepUpException extends RuntimeException
{
    public function __construct(
        public readonly string $reason,
        public readonly string $correlationId
    ) {
        parent::__construct($reason);
    }
}
