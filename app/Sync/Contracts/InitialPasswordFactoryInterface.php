<?php

namespace OneId\App\Sync\Contracts;

interface InitialPasswordFactoryInterface
{
    public function createHash(): string;
}
