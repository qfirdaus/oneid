<?php

namespace OneId\App\Sync\Contracts;

interface ExternalUserSourceInterface
{
    /** @return list<array<string, mixed>> */
    public function fetchAll(): array;
}
