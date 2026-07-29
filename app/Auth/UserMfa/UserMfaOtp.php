<?php

declare(strict_types=1);

namespace OneId\App\Auth\UserMfa;

use RuntimeException;

final class UserMfaOtp
{
    public static function generate(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    public static function hash(string $otp): string
    {
        self::assertFormat($otp);
        $hash = password_hash($otp, PASSWORD_ARGON2ID);
        if (!is_string($hash)) {
            throw new RuntimeException('USER_MFA_OTP_HASH_FAILED');
        }
        return $hash;
    }

    public static function verify(string $submittedOtp, string $storedHash): bool
    {
        if (preg_match('/\A[0-9]{6}\z/', $submittedOtp) !== 1
            || $storedHash === ''
            || strlen($storedHash) > 255
        ) {
            return false;
        }
        return password_verify($submittedOtp, $storedHash);
    }

    private static function assertFormat(string $otp): void
    {
        if (preg_match('/\A[0-9]{6}\z/', $otp) !== 1) {
            throw new RuntimeException('USER_MFA_OTP_FORMAT_INVALID');
        }
    }
}
