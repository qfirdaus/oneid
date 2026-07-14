<?php

namespace OneId\App\Sync\Adapters;

use OneId\App\Sync\Contracts\InitialPasswordFactoryInterface;

final class SecureInitialPasswordFactory implements InitialPasswordFactoryInterface
{
    public function createHash(): string
    {
        return \oneid_password_hash(bin2hex(random_bytes(32)));
    }
}
