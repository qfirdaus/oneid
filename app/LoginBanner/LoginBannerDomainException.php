<?php

declare(strict_types=1);

namespace OneId\App\LoginBanner;

use RuntimeException;

final class LoginBannerDomainException extends RuntimeException
{
    public function __construct(
        public readonly string $reason,
        public readonly string $correlationId = '',
        ?\Throwable $previous = null
    ) {
        parent::__construct($reason, 0, $previous);
    }
}
