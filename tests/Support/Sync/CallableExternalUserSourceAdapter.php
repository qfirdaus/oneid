<?php

namespace OneId\Tests\Support\Sync;

use Closure;
use OneId\App\Sync\Contracts\ExternalUserSourceInterface;

final class CallableExternalUserSourceAdapter implements ExternalUserSourceInterface
{
    private Closure $fetch;

    public function __construct(callable $fetch)
    {
        $this->fetch = Closure::fromCallable($fetch);
    }

    public function fetchAll(): array
    {
        return ($this->fetch)();
    }
}
