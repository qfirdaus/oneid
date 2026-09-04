<?php

declare(strict_types=1);

namespace OneId\App\Maintenance;

use RuntimeException;

final class MaintenanceDeveloperAccessException extends RuntimeException
{
    public function __construct(
        public readonly string $reason,
        public readonly string $correlationId = ''
    ) {
        parent::__construct($reason);
    }
}
