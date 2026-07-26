<?php

declare(strict_types=1);

namespace OneId\App\Auth\MyDigitalId;

final class MyDigitalIdAuthorizationTransaction
{
    public function __construct(
        public readonly string $state,
        public readonly string $nonce,
        public readonly string $codeVerifier,
        public readonly int $createdAt,
        public readonly string $returnPath
    ) {
        if (
            preg_match('/^[a-f0-9]{64}$/D', $state) !== 1
            || preg_match('/^[a-f0-9]{64}$/D', $nonce) !== 1
            || preg_match('/^[A-Za-z0-9_-]{43,128}$/D', $codeVerifier) !== 1
            || $createdAt < 1
            || $returnPath !== '/page/dashboard'
        ) {
            throw new MyDigitalIdConfigurationException('MYDID_AUTH_TRANSACTION_INVALID');
        }
    }

    /**
     * @return array{state:string,nonce:string,code_verifier:string,created_at:int,return_path:string}
     */
    public function toSessionValue(): array
    {
        return [
            'state' => $this->state,
            'nonce' => $this->nonce,
            'code_verifier' => $this->codeVerifier,
            'created_at' => $this->createdAt,
            'return_path' => $this->returnPath,
        ];
    }

    public function codeChallenge(): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $this->codeVerifier, true)), '+/', '-_'), '=');
    }
}
