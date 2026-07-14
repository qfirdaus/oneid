<?php

namespace OneId\App\Admin;

use RuntimeException;

final class WebAppManagementException extends RuntimeException
{
    public function __construct(
        public readonly string $reason,
        public readonly string $correlationId,
        public readonly array $context = []
    ) {
        parent::__construct($reason);
    }
}
