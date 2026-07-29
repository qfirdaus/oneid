<?php

declare(strict_types=1);

namespace OneId\App\Auth\UserMfa;

interface UserMfaEmailSenderInterface
{
    public function send(string $otp, string $email, string $displayName, string $locale): bool;
}
