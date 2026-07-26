<?php

declare(strict_types=1);

namespace OneId\App\Auth\MyDigitalId;

use RuntimeException;

final class MyDigitalIdPersistenceException extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct('MyDigital ID persistence operation was rejected.');
    }
}
