<?php

function oneid_password_hash(string $password): string
{
    return password_hash($password, PASSWORD_DEFAULT);
}

function oneid_is_legacy_md5_hash(string $hash): bool
{
    return preg_match('/^[a-f0-9]{32}$/i', $hash) === 1;
}

function oneid_legacy_md5_allowed(): bool
{
    $configuredDeadline = trim((string) getenv('ONEID_LEGACY_MD5_DEADLINE'));
    $deadline = $configuredDeadline !== ''
        ? strtotime($configuredDeadline)
        : strtotime('2026-10-13 23:59:59 Asia/Kuala_Lumpur');

    return $deadline !== false && time() <= $deadline;
}

function oneid_password_verify(string $password, string $storedHash): bool
{
    if (oneid_is_legacy_md5_hash($storedHash)) {
        return oneid_legacy_md5_allowed()
            && hash_equals(strtolower($storedHash), md5($password));
    }

    return password_verify($password, $storedHash);
}

function oneid_password_needs_rehash(string $storedHash): bool
{
    return oneid_is_legacy_md5_hash($storedHash)
        || password_needs_rehash($storedHash, PASSWORD_DEFAULT);
}

function oneid_validate_new_password(string $password): array
{
    if (strlen($password) < 12) {
        return [false, 'Password must contain at least 12 characters.'];
    }
    if (!preg_match('/[a-z]/', $password)
        || !preg_match('/[A-Z]/', $password)
        || !preg_match('/\d/', $password)
        || !preg_match('/[^a-zA-Z0-9]/', $password)) {
        return [false, 'Password must include uppercase, lowercase, number and symbol.'];
    }

    return [true, ''];
}

function oneid_generate_sso_token(): string
{
    return bin2hex(random_bytes(32));
}

function oneid_token_hash(string $token): string
{
    return hash('sha256', $token);
}

function oneid_is_https_request(): bool
{
    return (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
}

function oneid_set_sso_cookie(string $token): void
{
    setcookie('sso_cre', $token, [
        'expires' => time() + 1800,
        'path' => '/',
        'secure' => oneid_is_https_request(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function oneid_clear_sso_cookie(): void
{
    setcookie('sso_cre', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => oneid_is_https_request(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    unset($_COOKIE['sso_cre']);
}
