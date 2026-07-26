<?php

declare(strict_types=1);

namespace OneId\App\Auth\MyDigitalId;

use RuntimeException;

final class MyDigitalIdConfigurationException extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct('MyDigital ID configuration is invalid.');
    }
}
