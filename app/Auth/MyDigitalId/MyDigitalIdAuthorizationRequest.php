<?php

declare(strict_types=1);

namespace OneId\App\Auth\MyDigitalId;

final class MyDigitalIdAuthorizationRequest
{
    public function __construct(private readonly MyDigitalIdConfig $config)
    {
    }

    public function url(MyDigitalIdAuthorizationTransaction $transaction): string
    {
        if (!$this->config->enabled) {
            throw new MyDigitalIdConfigurationException('MYDID_DISABLED');
        }

        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => $this->config->clientId,
            'redirect_uri' => $this->config->redirectUri,
            'scope' => $this->config->scope,
            'state' => $transaction->state,
            'nonce' => $transaction->nonce,
            'code_challenge' => $transaction->codeChallenge(),
            'code_challenge_method' => $this->config->pkceMethod,
        ], '', '&', PHP_QUERY_RFC3986);

        return $this->config->issuer . '/protocol/openid-connect/auth?' . $query;
    }
}
