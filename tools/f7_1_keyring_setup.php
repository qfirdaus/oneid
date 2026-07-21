<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

require_once dirname(__DIR__) . '/app/Auth/TotpKeyring.php';

use OneId\App\Auth\TotpKeyring;

$path = getenv('ONEID_TOTP_KEYRING_FILE') ?: '';
if ($path === '' || $path[0] !== '/' || str_contains($path, "\0")) {
    fwrite(STDERR, "FAIL F7_TOTP_KEYRING_PATH_INVALID\n");
    exit(1);
}

$projectRoot = realpath(dirname(__DIR__));
$parent = dirname($path);
if ($projectRoot !== false && str_starts_with($path, $projectRoot . DIRECTORY_SEPARATOR)) {
    fwrite(STDERR, "FAIL F7_TOTP_KEYRING_MUST_BE_OUTSIDE_REPOSITORY\n");
    exit(1);
}

if (!is_dir($parent) && !mkdir($parent, 0700, true) && !is_dir($parent)) {
    fwrite(STDERR, "FAIL F7_TOTP_KEYRING_DIRECTORY_CREATE_FAILED\n");
    exit(1);
}
chmod($parent, 0700);

if (file_exists($path)) {
    try {
        $keyring = TotpKeyring::fromFile($path);
        printf(
            "PASS keyring=existing active_version=%s path_fingerprint=%s\n",
            $keyring->activeVersion(),
            substr(hash('sha256', $path), 0, 16)
        );
        exit(0);
    } catch (Throwable) {
        fwrite(STDERR, "FAIL F7_TOTP_EXISTING_KEYRING_INVALID\n");
        exit(1);
    }
}

$key = random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
$source = '<?php return ' . var_export([
    'active_version' => 'v1',
    'keys' => ['v1' => base64_encode($key)],
], true) . ';' . PHP_EOL;

$handle = @fopen($path, 'x');
if ($handle === false) {
    sodium_memzero($key);
    fwrite(STDERR, "FAIL F7_TOTP_KEYRING_EXCLUSIVE_CREATE_FAILED\n");
    exit(1);
}
$written = fwrite($handle, $source);
fflush($handle);
fclose($handle);
chmod($path, 0600);
sodium_memzero($key);
unset($source);

if ($written === false) {
    fwrite(STDERR, "FAIL F7_TOTP_KEYRING_WRITE_FAILED\n");
    exit(1);
}

$keyring = TotpKeyring::fromFile($path);
printf(
    "PASS keyring=created active_version=%s mode=%04o path_fingerprint=%s\n",
    $keyring->activeVersion(),
    fileperms($path) & 0777,
    substr(hash('sha256', $path), 0, 16)
);
