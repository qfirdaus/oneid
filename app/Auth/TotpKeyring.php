<?php

declare(strict_types=1);

namespace OneId\App\Auth;

use RuntimeException;

final class TotpKeyring
{
    /** @param array<string, string> $keys */
    private function __construct(
        private readonly string $activeVersion,
        private readonly array $keys
    ) {
    }

    public static function fromFile(string $path): self
    {
        if ($path === '' || !is_file($path) || !is_readable($path)) {
            throw new RuntimeException('F7_TOTP_KEYRING_UNAVAILABLE');
        }

        $mode = fileperms($path);
        if ($mode === false || (($mode & 0007) !== 0) || (($mode & 0030) !== 0)) {
            throw new RuntimeException('F7_TOTP_KEYRING_PERMISSIONS_INVALID');
        }

        $loaded = require $path;
        if (!is_array($loaded)) {
            throw new RuntimeException('F7_TOTP_KEYRING_FORMAT_INVALID');
        }

        $activeVersion = $loaded['active_version'] ?? null;
        $encodedKeys = $loaded['keys'] ?? null;
        if (!is_string($activeVersion)
            || preg_match('/\A[a-zA-Z0-9._-]{1,32}\z/', $activeVersion) !== 1
            || !is_array($encodedKeys)
        ) {
            throw new RuntimeException('F7_TOTP_KEYRING_FORMAT_INVALID');
        }

        $keys = [];
        foreach ($encodedKeys as $version => $encodedKey) {
            if (!is_string($version)
                || preg_match('/\A[a-zA-Z0-9._-]{1,32}\z/', $version) !== 1
                || !is_string($encodedKey)
            ) {
                throw new RuntimeException('F7_TOTP_KEYRING_FORMAT_INVALID');
            }
            $decoded = base64_decode($encodedKey, true);
            if ($decoded === false || strlen($decoded) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
                throw new RuntimeException('F7_TOTP_KEYRING_KEY_INVALID');
            }
            $keys[$version] = $decoded;
        }

        if (!isset($keys[$activeVersion])) {
            throw new RuntimeException('F7_TOTP_KEYRING_ACTIVE_KEY_MISSING');
        }

        return new self($activeVersion, $keys);
    }

    public function activeVersion(): string
    {
        return $this->activeVersion;
    }

    public function activeKey(): string
    {
        return $this->keys[$this->activeVersion];
    }

    public function key(string $version): string
    {
        if (!isset($this->keys[$version])) {
            throw new RuntimeException('F7_TOTP_KEY_VERSION_UNKNOWN');
        }
        return $this->keys[$version];
    }
}
