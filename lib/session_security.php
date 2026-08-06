<?php

require_once __DIR__ . '/auth_security.php';
require_once __DIR__ . '/locale.php';

function oneid_is_technical_heartbeat_request(array $post): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
        && in_array(array_keys($post), [
            ['update_specific_token_datetime'],
            ['admin_step_up_status', 'purpose'],
        ], true);
}

function oneid_session_is_expired(int $now, int $createdAt, int $lastActivity): bool
{
    return ($now - $lastActivity) > 1800 || ($now - $createdAt) > 28800;
}

function oneid_session_next_activity(int $now, int $lastActivity, bool $technicalHeartbeat): int
{
    return $technicalHeartbeat ? $lastActivity : $now;
}

function oneid_start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', oneid_is_https_request() ? '1' : '0');
    ini_set('session.cookie_samesite', 'Lax');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => oneid_is_https_request(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();

    $now = time();
    $createdAt = (int) ($_SESSION['oneid_session_created_at'] ?? $now);
    $lastActivity = (int) ($_SESSION['oneid_session_last_activity'] ?? $now);
    $expired = oneid_session_is_expired($now, $createdAt, $lastActivity);
    $technicalHeartbeat = oneid_is_technical_heartbeat_request($_POST ?? []);

    if ($expired) {
        $_SESSION = [];
        session_regenerate_id(true);
        $createdAt = $now;
        $lastActivity = $now;
    }

    $_SESSION['oneid_session_created_at'] = $createdAt;
    $_SESSION['oneid_session_last_activity'] = oneid_session_next_activity($now, $lastActivity, $technicalHeartbeat);
}

function oneid_establish_authenticated_session(array $user): void
{
    $sameAuthenticatedUser = ($_SESSION['login_status'] ?? '') === 'true'
        && (string) ($_SESSION['login_user'] ?? '') === (string) $user['u_id'];
    if (!$sameAuthenticatedUser) {
        session_regenerate_id(true);
    }
    $_SESSION['user'] = $user['data1'];
    $_SESSION['oneid_public_identifier'] = trim((string) ($user['data3'] ?? '')) !== ''
        ? trim((string) $user['data3'])
        : (trim((string) ($user['data4'] ?? '')) !== ''
            ? trim((string) $user['data4'])
            : (string) $user['u_id']);
    $_SESSION['login_user'] = $user['u_id'];
    $_SESSION['login_status'] = 'true';
    $_SESSION['login_user_type'] = $user['u_type'];
    $_SESSION['password_change_required'] = (int) ($user['password_change_required'] ?? 0);
    if (!$sameAuthenticatedUser) {
        $_SESSION['oneid_session_created_at'] = time();
    }
    $_SESSION['oneid_session_last_activity'] = time();
    if (!$sameAuthenticatedUser) {
        unset($_SESSION['oneid_csrf_token']);
    }
    oneid_promote_authenticated_locale((string) $user['u_id']);
}

function oneid_issue_mydigitalid_initial_password_grant(string $userId): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        throw new RuntimeException('A PHP session is required');
    }
    $_SESSION['mydigitalid_initial_password_grant'] = [
        'user_id' => $userId,
        'issued_at' => time(),
        'session_binding' => hash('sha256', session_id()),
        'nonce' => bin2hex(random_bytes(32)),
    ];
}

function oneid_has_valid_mydigitalid_initial_password_grant(string $userId, int $ttlSeconds = 300): bool
{
    $grant = $_SESSION['mydigitalid_initial_password_grant'] ?? null;
    if (!is_array($grant)
        || ($_SESSION['auth_method'] ?? '') !== 'mydigitalid'
        || (string) ($_SESSION['login_user'] ?? '') !== $userId
        || (string) ($grant['user_id'] ?? '') !== $userId
        || preg_match('/^[a-f0-9]{64}$/D', (string) ($grant['nonce'] ?? '')) !== 1
        || !hash_equals(hash('sha256', session_id()), (string) ($grant['session_binding'] ?? ''))
    ) {
        return false;
    }
    $issuedAt = (int) ($grant['issued_at'] ?? 0);
    return $issuedAt > 0 && time() >= $issuedAt && (time() - $issuedAt) <= $ttlSeconds;
}

function oneid_consume_mydigitalid_initial_password_grant(): void
{
    unset($_SESSION['mydigitalid_initial_password_grant']);
}

function oneid_totp_account_label(string $role, ?array $user = null): string
{
    $role = strtoupper(trim($role)) === 'ADMIN' ? 'Admin' : 'User';
    $identifier = trim((string) ($user['data3'] ?? ''));
    if ($identifier === '') {
        $identifier = trim((string) ($user['data4'] ?? ''));
    }
    if ($identifier === '') {
        $identifier = trim((string) ($_SESSION['oneid_public_identifier'] ?? ''));
    }
    if ($identifier === ''
        || strlen($identifier) > 50
        || preg_match('/\A[A-Za-z0-9._@-]+\z/', $identifier) !== 1
    ) {
        $identifier = trim((string) ($_SESSION['login_user'] ?? ''));
    }

    return sprintf('OneID@UPNM %s (%s)', $role, $identifier);
}
