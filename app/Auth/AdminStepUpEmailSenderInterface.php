<?php

declare(strict_types=1);

namespace OneId\App\Auth;

interface AdminStepUpEmailSenderInterface
{
    public function send(string $otp, string $email, string $displayName): bool;
}
