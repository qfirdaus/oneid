<?php

declare(strict_types=1);

namespace OneId\App\Auth\MyDigitalId;

use Jumbojett\OpenIDConnectClient;

final class MyDigitalIdProtocolClient
{
    public function __construct(private readonly MyDigitalIdConfig $config)
    {
    }

    public function build(): OpenIDConnectClient
    {
        if (!$this->config->enabled) {
            throw new MyDigitalIdConfigurationException('MYDID_DISABLED');
        }

        $issuer = $this->config->issuer;
        $client = new OpenIDConnectClient(
            $issuer,
            $this->config->clientId,
            $this->config->clientSecret,
            $issuer
        );
        $client->providerConfigParam([
            'issuer' => $issuer,
            'authorization_endpoint' => $issuer . '/protocol/openid-connect/auth',
            'token_endpoint' => $issuer . '/protocol/openid-connect/token',
            'userinfo_endpoint' => $issuer . '/protocol/openid-connect/userinfo',
            'jwks_uri' => $issuer . '/protocol/openid-connect/certs',
            'end_session_endpoint' => $issuer . '/protocol/openid-connect/logout',
        ]);
        $client->setRedirectURL($this->config->redirectUri);
        $client->addScope([$this->config->scope]);
        $client->setResponseTypes(['code']);
        $client->setCodeChallengeMethod($this->config->pkceMethod);
        $client->setTokenEndpointAuthMethodsSupported(['client_secret_basic']);
        $client->setTimeout($this->config->httpTimeoutSeconds);
        $client->setVerifyPeer(true);
        $client->setVerifyHost(true);
        $client->setHttpUpgradeInsecureRequests(false);
        $client->setIssuerValidator(
            static fn(string $actualIssuer): bool => hash_equals($issuer, $actualIssuer)
        );

        return $client;
    }
}
