<?php

namespace OneId\Tests\Support\Sync;

use Closure;
use OneId\App\Sync\Contracts\InitialPasswordFactoryInterface;

final class CallableInitialPasswordFactoryAdapter implements InitialPasswordFactoryInterface
{
    private Closure $createHash;

    public function __construct(callable $createHash)
    {
        $this->createHash = Closure::fromCallable($createHash);
    }

    public function createHash(): string
    {
        return ($this->createHash)();
    }
}
