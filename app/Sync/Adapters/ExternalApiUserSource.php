<?php

namespace OneId\App\Sync\Adapters;

use OneId\App\Sync\Contracts\ExternalUserSourceInterface;

final class ExternalApiUserSource implements ExternalUserSourceInterface
{
    public function fetchAll(): array
    {
        return \EXTERNAL_DATA_SOURCE_GET_ALL_USER();
    }
}
