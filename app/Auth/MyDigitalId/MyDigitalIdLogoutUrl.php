<?php

declare(strict_types=1);

namespace OneId\App\Auth\MyDigitalId;

final class MyDigitalIdLogoutUrl
{
    public function __construct(private readonly MyDigitalIdConfig $config)
    {
    }

    public function build(string $idToken): string
    {
        if (
            !$this->config->enabled
            || strlen($idToken) > 8192
            || substr_count($idToken, '.') !== 2
            || preg_match('/^[A-Za-z0-9._-]+$/D', $idToken) !== 1
        ) {
            throw new MyDigitalIdConfigurationException('MYDID_LOGOUT_TOKEN_INVALID');
        }
        return $this->config->issuer . '/protocol/openid-connect/logout?'
            . http_build_query([
                'id_token_hint' => $idToken,
                'post_logout_redirect_uri' => $this->config->postLogoutRedirectUri,
            ], '', '&', PHP_QUERY_RFC3986);
    }
}
