<?php

declare(strict_types=1);

namespace OneId\App\Auth;

use InvalidArgumentException;

final class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function generateSecret(int $bytes = 20): string
    {
        if ($bytes < 16 || $bytes > 64) {
            throw new InvalidArgumentException('F7_TOTP_SECRET_LENGTH_INVALID');
        }
        return self::base32Encode(random_bytes($bytes));
    }

    public static function codeAt(string $secret, int $timestamp, int $digits = 6, int $period = 30): string
    {
        if ($timestamp < 0 || !in_array($digits, [6, 8], true) || $period < 15 || $period > 120) {
            throw new InvalidArgumentException('F7_TOTP_PARAMETERS_INVALID');
        }
        return self::codeForStep($secret, intdiv($timestamp, $period), $digits);
    }

    public static function matchTimeStep(
        string $secret,
        string $code,
        int $timestamp,
        int $window = 1,
        ?int $lastUsedStep = null,
        int $period = 30
    ): ?int {
        if (preg_match('/\A[0-9]{6}\z/', $code) !== 1 || $window < 0 || $window > 2) {
            return null;
        }
        $current = intdiv($timestamp, $period);
        for ($offset = -$window; $offset <= $window; $offset++) {
            $step = $current + $offset;
            if ($step < 0 || ($lastUsedStep !== null && $step <= $lastUsedStep)) {
                continue;
            }
            if (hash_equals(self::codeForStep($secret, $step, 6), $code)) {
                return $step;
            }
        }
        return null;
    }

    public static function provisioningUri(string $issuer, string $account, string $secret): string
    {
        $issuer = trim($issuer);
        $account = trim($account);
        self::base32Decode($secret);
        if ($issuer === '' || $account === '' || strlen($issuer) > 64 || strlen($account) > 128) {
            throw new InvalidArgumentException('F7_TOTP_PROVISIONING_LABEL_INVALID');
        }
        $label = rawurlencode($issuer . ':' . $account);
        return 'otpauth://totp/' . $label
            . '?secret=' . rawurlencode($secret)
            . '&issuer=' . rawurlencode($issuer)
            . '&algorithm=SHA1&digits=6&period=30';
    }

    public static function base32Encode(string $binary): string
    {
        if ($binary === '') {
            throw new InvalidArgumentException('F7_TOTP_SECRET_INVALID');
        }
        $bitString = '';
        foreach (unpack('C*', $binary) as $byte) {
            $bitString .= str_pad(decbin($byte), 8, '0', STR_PAD_LEFT);
        }
        $output = '';
        foreach (str_split($bitString, 5) as $chunk) {
            $output .= self::ALPHABET[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
        }
        return $output;
    }

    public static function base32Decode(string $encoded): string
    {
        $encoded = strtoupper(rtrim(trim($encoded), '='));
        if ($encoded === '' || preg_match('/\A[A-Z2-7]+\z/', $encoded) !== 1) {
            throw new InvalidArgumentException('F7_TOTP_SECRET_INVALID');
        }
        $bitString = '';
        foreach (str_split($encoded) as $character) {
            $value = strpos(self::ALPHABET, $character);
            if ($value === false) {throw new InvalidArgumentException('F7_TOTP_SECRET_INVALID');}
            $bitString .= str_pad(decbin($value), 5, '0', STR_PAD_LEFT);
        }
        $output = '';
        foreach (str_split($bitString, 8) as $chunk) {
            if (strlen($chunk) === 8) {$output .= chr(bindec($chunk));}
        }
        return $output;
    }

    private static function codeForStep(string $secret, int $step, int $digits): string
    {
        $key = self::base32Decode($secret);
        $counter = pack('N2', ($step >> 32) & 0xffffffff, $step & 0xffffffff);
        $hash = hash_hmac('sha1', $counter, $key, true);
        $offset = ord($hash[19]) & 0x0f;
        $binary = ((ord($hash[$offset]) & 0x7f) << 24)
            | ((ord($hash[$offset + 1]) & 0xff) << 16)
            | ((ord($hash[$offset + 2]) & 0xff) << 8)
            | (ord($hash[$offset + 3]) & 0xff);
        return str_pad((string) ($binary % (10 ** $digits)), $digits, '0', STR_PAD_LEFT);
    }
}
