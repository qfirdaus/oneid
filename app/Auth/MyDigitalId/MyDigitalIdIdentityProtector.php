<?php

declare(strict_types=1);

namespace OneId\App\Auth\MyDigitalId;

final class MyDigitalIdIdentityProtector
{
    private const PROVIDER_CODE = 'mydigitalid';

    private function __construct(
        private readonly string $key,
        public readonly string $keyId
    ) {
    }

    public static function fromBase64(string $encodedKey, string $keyId): self
    {
        $keyId = trim($keyId);
        if (preg_match('/^[A-Za-z0-9._-]{2,32}$/D', $keyId) !== 1) {
            throw new MyDigitalIdConfigurationException('MYDID_HMAC_KEY_ID_INVALID');
        }
        $key = base64_decode(trim($encodedKey), true);
        if (!is_string($key) || strlen($key) !== 32) {
            throw new MyDigitalIdConfigurationException('MYDID_HMAC_KEY_INVALID');
        }
        return new self($key, $keyId);
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

        return self::fromBase64(
            $secretReader('ONEID_MYDID_IDENTITY_HMAC_KEY_BASE64', true),
            (string) $configReader('ONEID_MYDID_IDENTITY_HMAC_KEY_ID', '')
        );
    }

    public function providerCode(): string
    {
        return self::PROVIDER_CODE;
    }

    public function normalizedNric(string $nric): string
    {
        $normalized = preg_replace('/[\s\p{Pd}-]+/u', '', trim($nric));
        if (!is_string($normalized) || preg_match('/^\d{12}$/D', $normalized) !== 1) {
            throw new MyDigitalIdConfigurationException('MYDID_NRIC_INVALID');
        }
        return $normalized;
    }

    public function nricHmac(string $nric): string
    {
        return $this->digest('nric', $this->normalizedNric($nric));
    }

    public function subjectHmac(string $issuer, string $subject): string
    {
        if ($issuer !== 'https://sso.digital-id.my/realms/upnm') {
            throw new MyDigitalIdConfigurationException('MYDID_ISSUER_INVALID');
        }
        $subject = trim($subject);
        if (
            $subject === ''
            || strlen($subject) > 255
            || preg_match('/[\x00-\x1F\x7F]/', $subject) === 1
        ) {
            throw new MyDigitalIdConfigurationException('MYDID_SUBJECT_INVALID');
        }
        return $this->digest('subject', $issuer . "\0" . $subject);
    }

    public function contextHmac(string $context, string $value): ?string
    {
        $allowed = ['ip', 'user-agent', 'session-id'];
        if (!in_array($context, $allowed, true)) {
            throw new MyDigitalIdConfigurationException('MYDID_HMAC_CONTEXT_INVALID');
        }
        if ($value === '') {
            return null;
        }
        if (strlen($value) > 2048 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value) === 1) {
            throw new MyDigitalIdConfigurationException('MYDID_HMAC_VALUE_INVALID');
        }
        return $this->digest($context, $value);
    }

    private function digest(string $context, string $value): string
    {
        return hash_hmac('sha256', "oneid:mydigitalid:{$context}\0{$value}", $this->key);
    }
}
