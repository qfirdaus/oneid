<?php

declare(strict_types=1);

namespace OneId\App\Auth\MyDigitalId;

final class MyDigitalIdVerifiedIdentity
{
    public function __construct(
        public readonly string $subject,
        public readonly string $name,
        public readonly string $nric,
        public readonly string $idToken
    ) {
        if (
            $subject === '' || strlen($subject) > 255
            || trim($name) === '' || strlen($name) > 255
            || preg_match('/^\d{12}$/D', $nric) !== 1
            || substr_count($idToken, '.') !== 2
        ) {
            throw new MyDigitalIdConfigurationException('MYDID_VERIFIED_IDENTITY_INVALID');
        }
    }
}
