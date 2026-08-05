<?php

namespace OneId\App\Admin;

use RuntimeException;

final class ActiveSessionRevocationException extends RuntimeException
{
    public function __construct(string $code, public readonly string $correlationId = '')
    {
        parent::__construct($code);
    }
}
