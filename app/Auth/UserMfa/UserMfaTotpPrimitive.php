<?php

declare(strict_types=1);

namespace OneId\App\Auth\UserMfa;

use OneId\App\Auth\Totp;
use OneId\App\Auth\TotpSecretCipher;
use RuntimeException;

final class UserMfaTotpPrimitive
{
    /** @var callable(): int */
    private $clock;

    public function __construct(
        private readonly TotpSecretCipher $cipher,
        ?callable $clock = null
    ) {
        $this->clock = $clock ?? static fn(): int => time();
    }

    /**
     * @return array{
     *   secret:string,
     *   provisioning_uri:string,
     *   encrypted_secret:string,
     *   secret_nonce:string,
     *   key_version:string
     * }
     */
    public function enroll(string $issuer, string $account): array
    {
        $secret = Totp::generateSecret();
        $encrypted = $this->cipher->encrypt($secret);
        return [
            'secret' => $secret,
            'provisioning_uri' => Totp::provisioningUri($issuer, $account, $secret),
            'encrypted_secret' => $encrypted['ciphertext'],
            'secret_nonce' => $encrypted['nonce'],
            'key_version' => $encrypted['key_version'],
        ];
    }

    public function matchEncrypted(
        string $ciphertext,
        string $nonce,
        string $keyVersion,
        string $submittedCode,
        ?int $lastUsedStep
    ): int {
        $secret = $this->cipher->decrypt($ciphertext, $nonce, $keyVersion);
        try {
            $step = Totp::matchTimeStep(
                $secret,
                $submittedCode,
                ($this->clock)(),
                1,
                $lastUsedStep
            );
        } finally {
            unset($secret);
        }
        if ($step === null) {
            throw new RuntimeException('USER_MFA_TOTP_INVALID_OR_REPLAYED');
        }
        return $step;
    }
}
