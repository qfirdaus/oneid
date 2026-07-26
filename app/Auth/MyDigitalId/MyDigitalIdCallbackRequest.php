<?php

declare(strict_types=1);

namespace OneId\App\Auth\MyDigitalId;

final class MyDigitalIdCallbackRequest
{
    private const ALLOWED_KEYS = [
        'code', 'state', 'session_state', 'iss',
        'error', 'error_description', 'error_uri',
    ];

    public function __construct(
        public readonly string $code,
        public readonly string $state
    ) {
    }

    /**
     * @param array<string,mixed> $query
     */
    public static function fromHttp(string $method, array $query): self
    {
        if ($method !== 'GET') {
            throw new MyDigitalIdConfigurationException('MYDID_CALLBACK_METHOD_INVALID');
        }
        if (array_diff(array_keys($query), self::ALLOWED_KEYS) !== []) {
            throw new MyDigitalIdConfigurationException('MYDID_CALLBACK_PARAMETER_INVALID');
        }
        if (isset($query['error'])) {
            throw new MyDigitalIdConfigurationException('MYDID_PROVIDER_REJECTED');
        }

        $code = is_string($query['code'] ?? null) ? $query['code'] : '';
        $state = is_string($query['state'] ?? null) ? $query['state'] : '';
        $issuer = $query['iss'] ?? null;
        if (
            preg_match('/^[A-Za-z0-9._~-]{1,4096}$/D', $code) !== 1
            || preg_match('/^[a-f0-9]{64}$/D', $state) !== 1
            || (
                $issuer !== null
                && (
                    !is_string($issuer)
                    || !hash_equals('https://sso.digital-id.my/realms/upnm', $issuer)
                )
            )
        ) {
            throw new MyDigitalIdConfigurationException('MYDID_CALLBACK_PARAMETER_INVALID');
        }

        return new self($code, $state);
    }
}
