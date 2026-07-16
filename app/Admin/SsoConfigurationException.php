<?php

namespace OneId\App\Admin;

use RuntimeException;

final class SsoConfigurationException extends RuntimeException
{
    public function __construct(
        public readonly string $reason,
        public readonly string $correlationId
    ) {
        parent::__construct($reason);
    }
}
