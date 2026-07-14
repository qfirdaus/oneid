<?php

require_once __DIR__ . '/auth_security.php';

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
    $expired = ($now - $lastActivity) > 1800 || ($now - $createdAt) > 28800;

    if ($expired) {
        $_SESSION = [];
        session_regenerate_id(true);
        $createdAt = $now;
    }

    $_SESSION['oneid_session_created_at'] = $createdAt;
    $_SESSION['oneid_session_last_activity'] = $now;
}

function oneid_establish_authenticated_session(array $user): void
{
    $sameAuthenticatedUser = ($_SESSION['login_status'] ?? '') === 'true'
        && (string) ($_SESSION['login_user'] ?? '') === (string) $user['u_id'];
    if (!$sameAuthenticatedUser) {
        session_regenerate_id(true);
    }
    $_SESSION['user'] = $user['data1'];
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
}
