<?php

declare(strict_types=1);

namespace OneId\App\Auth;

use RuntimeException;

final class TotpSecretCipher
{
    public function __construct(private readonly TotpKeyring $keyring)
    {
        if (!function_exists('sodium_crypto_secretbox')) {
            throw new RuntimeException('F7_TOTP_SODIUM_UNAVAILABLE');
        }
    }

    /** @return array{ciphertext: string, nonce: string, key_version: string} */
    public function encrypt(string $secret): array
    {
        if ($secret === '' || strlen($secret) > 512) {
            throw new RuntimeException('F7_TOTP_SECRET_INVALID');
        }

        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($secret, $nonce, $this->keyring->activeKey());

        return [
            'ciphertext' => $ciphertext,
            'nonce' => $nonce,
            'key_version' => $this->keyring->activeVersion(),
        ];
    }

    public function decrypt(string $ciphertext, string $nonce, string $keyVersion): string
    {
        if ($ciphertext === '' || strlen($nonce) !== SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new RuntimeException('F7_TOTP_CIPHERTEXT_INVALID');
        }

        $plaintext = sodium_crypto_secretbox_open(
            $ciphertext,
            $nonce,
            $this->keyring->key($keyVersion)
        );
        if ($plaintext === false) {
            throw new RuntimeException('F7_TOTP_DECRYPT_FAILED');
        }
        return $plaintext;
    }
}
