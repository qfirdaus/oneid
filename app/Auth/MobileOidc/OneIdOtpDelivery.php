<?php
declare(strict_types=1);
namespace OneId\App\Auth\MobileOidc;

use OneId\App\Auth\UserMfa\UserMfaEmailSenderInterface;

/** Reuses OneID's configured server-side sender, supplied by the dormant composition root. */
final class OneIdOtpDelivery implements OtpDelivery
{
    public function __construct(private readonly UserMfaEmailSenderInterface $sender, private readonly string $locale = 'ms') {}
    public function send(string $destination, string $displayName, string $otp): bool
    { return $this->sender->send($otp, $destination, $displayName, in_array($this->locale, ['ms', 'en'], true) ? $this->locale : 'ms'); }
}
