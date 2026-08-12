<?php

declare(strict_types=1);

namespace OneId\App\Admin;

use OneId\App\Auth\TotpKeyring;
use RuntimeException;

final class SiteApiCodeCipher
{
    public function __construct(private readonly TotpKeyring $keyring)
    {
        if(!function_exists('sodium_crypto_secretbox'))throw new RuntimeException('SITE_API_CODE_SODIUM_UNAVAILABLE');
    }

    public function encrypt(string $code): array
    {
        $nonce=random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return ['ciphertext'=>sodium_crypto_secretbox($code,$nonce,$this->key($this->keyring->activeVersion())),'nonce'=>$nonce,'key_version'=>$this->keyring->activeVersion()];
    }

    public function decrypt(string $ciphertext,string $nonce,string $version): string
    {
        if($ciphertext===''||strlen($nonce)!==SODIUM_CRYPTO_SECRETBOX_NONCEBYTES)throw new RuntimeException('SITE_API_CODE_CIPHERTEXT_INVALID');
        $code=sodium_crypto_secretbox_open($ciphertext,$nonce,$this->key($version));
        if($code===false)throw new RuntimeException('SITE_API_CODE_DECRYPT_FAILED');
        return $code;
    }

    private function key(string $version): string
    {
        return sodium_crypto_generichash('oneid-site-api-code-v1',$this->keyring->key($version),SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }
}
