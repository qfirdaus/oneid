<?php

declare(strict_types=1);

namespace OneId\App\Auth\MyDigitalId;

interface MyDigitalIdLocalLoginFinalizerInterface
{
    /** @param array<string,mixed> $user */
    public function finalize(array $user, string $deviceInfo): void;
}
