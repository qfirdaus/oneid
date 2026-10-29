<?php

declare(strict_types=1);

namespace OneId\App\Sync\Apel;

final class ApelSourceConfig
{
    public function __construct(
        public readonly string $host,
        public readonly int $port,
        public readonly string $database,
        public readonly string $username,
        public readonly string $password,
        public readonly string $sslCaPath,
        public readonly int $connectTimeout
    ) {
        if (filter_var($host, FILTER_VALIDATE_IP) === false
            && preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9.-]{0,252}\z/', $host) !== 1
        ) throw new \InvalidArgumentException('APEL_CONFIG_HOST_INVALID');
        if ($port < 1 || $port > 65535) throw new \InvalidArgumentException('APEL_CONFIG_PORT_INVALID');
        if (preg_match('/\A[a-zA-Z][a-zA-Z0-9_]{0,63}\z/', $database) !== 1) {
            throw new \InvalidArgumentException('APEL_CONFIG_DATABASE_INVALID');
        }
        if ($username === '' || strlen($username) > 128 || $password === '') {
            throw new \InvalidArgumentException('APEL_CONFIG_CREDENTIAL_INVALID');
        }
        if ($connectTimeout < 1 || $connectTimeout > 30) {
            throw new \InvalidArgumentException('APEL_CONFIG_TIMEOUT_INVALID');
        }
        if ($sslCaPath !== '') {
            $real = realpath($sslCaPath);
            if ($real === false || !is_file($real) || !is_readable($real)) {
                throw new \InvalidArgumentException('APEL_CONFIG_SSL_CA_INVALID');
            }
            if (str_starts_with($real, dirname(__DIR__, 3) . '/public/')) {
                throw new \InvalidArgumentException('APEL_CONFIG_SSL_CA_PUBLIC');
            }
        }
    }

    public static function fromPrivateRuntime(): self
    {
        $sslCa = '';
        try { $sslCa = trim(\oneid_secret('ONEID_APEL_MYSQL_SSL_CA')); } catch (\Throwable) {}
        return new self(
            trim(\oneid_secret('ONEID_APEL_MYSQL_HOST')),
            self::integer(\oneid_secret('ONEID_APEL_MYSQL_PORT'), 'APEL_CONFIG_PORT_INVALID'),
            trim(\oneid_secret('ONEID_APEL_MYSQL_DATABASE')),
            trim(\oneid_secret('ONEID_APEL_MYSQL_USERNAME')),
            \oneid_secret('ONEID_APEL_MYSQL_PASSWORD'),
            $sslCa,
            self::integer(\oneid_secret('ONEID_APEL_MYSQL_CONNECT_TIMEOUT'), 'APEL_CONFIG_TIMEOUT_INVALID')
        );
    }

    private static function integer(string $value, string $error): int
    {
        if (preg_match('/\A[0-9]{1,5}\z/', $value) !== 1) throw new \InvalidArgumentException($error);
        return (int) $value;
    }
}
