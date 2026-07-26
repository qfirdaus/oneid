<?php

declare(strict_types=1);

namespace OneId\App\Auth\MyDigitalId;

final class MyDigitalIdConfig
{
    private const EXPECTED_ISSUER = 'https://sso.digital-id.my/realms/upnm';
    private const EXPECTED_SCOPE = 'openid';
    private const EXPECTED_PKCE_METHOD = 'S256';

    private function __construct(
        public readonly bool $enabled,
        public readonly string $issuer,
        public readonly string $clientId,
        public readonly string $clientSecret,
        public readonly string $redirectUri,
        public readonly string $postLogoutRedirectUri,
        public readonly string $scope,
        public readonly int $httpTimeoutSeconds,
        public readonly string $pkceMethod
    ) {
    }

    /**
     * @param null|callable(string,mixed):mixed $configReader
     * @param null|callable(string,bool):string $secretReader
     */
    public static function fromRuntime(
        ?callable $configReader = null,
        ?callable $secretReader = null
    ): self {
        $configReader ??= static fn(string $key, mixed $fallback = null): mixed =>
            \oneid_config($key, $fallback);
        $secretReader ??= static fn(string $key, bool $required = true): string =>
            \oneid_secret($key, $required);

        $enabled = self::strictBoolean(
            $configReader('ONEID_MYDID_ENABLED', 'false'),
            'MYDID_ENABLED_INVALID'
        );
        $issuer = self::exactString(
            $configReader('ONEID_MYDID_ISSUER', ''),
            self::EXPECTED_ISSUER,
            'MYDID_ISSUER_INVALID'
        );
        $clientId = self::boundedIdentifier(
            $configReader('ONEID_MYDID_CLIENT_ID', ''),
            'MYDID_CLIENT_ID_INVALID'
        );
        $redirectUri = self::httpsUri(
            $configReader('ONEID_MYDID_REDIRECT_URI', ''),
            true,
            'MYDID_REDIRECT_URI_INVALID'
        );
        $postLogoutRedirectUri = self::httpsUri(
            $configReader('ONEID_MYDID_POST_LOGOUT_REDIRECT_URI', ''),
            false,
            'MYDID_POST_LOGOUT_REDIRECT_URI_INVALID'
        );
        $scope = self::exactString(
            $configReader('ONEID_MYDID_SCOPE', ''),
            self::EXPECTED_SCOPE,
            'MYDID_SCOPE_INVALID'
        );
        $pkceMethod = self::exactString(
            $configReader('ONEID_MYDID_PKCE_METHOD', ''),
            self::EXPECTED_PKCE_METHOD,
            'MYDID_PKCE_METHOD_INVALID'
        );
        $timeout = filter_var(
            $configReader('ONEID_MYDID_HTTP_TIMEOUT_SECONDS', null),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 3, 'max_range' => 30]]
        );
        if ($timeout === false) {
            throw new MyDigitalIdConfigurationException('MYDID_HTTP_TIMEOUT_INVALID');
        }

        $clientSecret = '';
        if ($enabled) {
            $clientSecret = trim($secretReader('ONEID_MYDID_CLIENT_SECRET', true));
            if (
                strlen($clientSecret) < 20
                || strlen($clientSecret) > 512
                || preg_match('/[\x00-\x20\x7F]/', $clientSecret) === 1
            ) {
                throw new MyDigitalIdConfigurationException('MYDID_CLIENT_SECRET_INVALID');
            }
        }

        return new self(
            $enabled,
            $issuer,
            $clientId,
            $clientSecret,
            $redirectUri,
            $postLogoutRedirectUri,
            $scope,
            (int) $timeout,
            $pkceMethod
        );
    }

    private static function strictBoolean(mixed $value, string $reason): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (!is_string($value) && !is_int($value)) {
            throw new MyDigitalIdConfigurationException($reason);
        }
        return match (strtolower(trim((string) $value))) {
            'true', '1' => true,
            'false', '0' => false,
            default => throw new MyDigitalIdConfigurationException($reason),
        };
    }

    private static function exactString(mixed $value, string $expected, string $reason): string
    {
        if (!is_string($value) || !hash_equals($expected, trim($value))) {
            throw new MyDigitalIdConfigurationException($reason);
        }
        return $expected;
    }

    private static function boundedIdentifier(mixed $value, string $reason): string
    {
        if (!is_string($value)) {
            throw new MyDigitalIdConfigurationException($reason);
        }
        $normalized = trim($value);
        if (
            strlen($normalized) < 3
            || strlen($normalized) > 128
            || preg_match('/^[A-Za-z0-9._-]+$/D', $normalized) !== 1
        ) {
            throw new MyDigitalIdConfigurationException($reason);
        }
        return $normalized;
    }

    private static function httpsUri(mixed $value, bool $callback, string $reason): string
    {
        if (!is_string($value)) {
            throw new MyDigitalIdConfigurationException($reason);
        }
        $uri = trim($value);
        $parts = parse_url($uri);
        if (
            !is_array($parts)
            || ($parts['scheme'] ?? '') !== 'https'
            || ($parts['host'] ?? '') !== 'oneid-uat.upnm.edu.my'
            || array_intersect(['user', 'pass', 'query', 'fragment'], array_keys($parts)) !== []
            || isset($parts['port'])
        ) {
            throw new MyDigitalIdConfigurationException($reason);
        }
        $path = $parts['path'] ?? '/';
        if ($callback && $path !== '/auth/mydigitalid/callback.php') {
            throw new MyDigitalIdConfigurationException($reason);
        }
        if (!$callback && $path !== '/') {
            throw new MyDigitalIdConfigurationException($reason);
        }
        return $uri;
    }
}
