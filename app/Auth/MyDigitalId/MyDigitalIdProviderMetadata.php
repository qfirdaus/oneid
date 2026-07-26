<?php

declare(strict_types=1);

namespace OneId\App\Auth\MyDigitalId;

final class MyDigitalIdProviderMetadata
{
    /** @param array<string,mixed> $metadata */
    public static function assertCompatible(array $metadata, MyDigitalIdConfig $config): void
    {
        $issuer = $config->issuer;
        $expected = [
            'issuer' => $issuer,
            'authorization_endpoint' => $issuer . '/protocol/openid-connect/auth',
            'token_endpoint' => $issuer . '/protocol/openid-connect/token',
            'userinfo_endpoint' => $issuer . '/protocol/openid-connect/userinfo',
            'jwks_uri' => $issuer . '/protocol/openid-connect/certs',
            'end_session_endpoint' => $issuer . '/protocol/openid-connect/logout',
        ];
        foreach ($expected as $field => $value) {
            if (!isset($metadata[$field]) || !is_string($metadata[$field])) {
                throw new MyDigitalIdConfigurationException('MYDID_METADATA_MISSING_' . strtoupper($field));
            }
            if (!hash_equals($value, $metadata[$field])) {
                throw new MyDigitalIdConfigurationException('MYDID_METADATA_MISMATCH_' . strtoupper($field));
            }
        }
        if (
            !in_array('code', self::stringList($metadata['response_types_supported'] ?? null), true)
            || !in_array(
                'authorization_code',
                self::stringList($metadata['grant_types_supported'] ?? null),
                true
            )
        ) {
            throw new MyDigitalIdConfigurationException('MYDID_AUTHORIZATION_CODE_UNSUPPORTED');
        }
        if (
            !in_array(
                $config->pkceMethod,
                self::stringList($metadata['code_challenge_methods_supported'] ?? null),
                true
            )
        ) {
            throw new MyDigitalIdConfigurationException('MYDID_PKCE_UNSUPPORTED');
        }
    }

    /** @return list<string> */
    private static function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }
        return array_values(array_filter($value, static fn(mixed $item): bool => is_string($item)));
    }
}
