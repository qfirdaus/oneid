<?php

require_once __DIR__ . '/auth_security.php';
require_once __DIR__ . '/locale.php';
require_once dirname(__DIR__) . '/app/Auth/UserSessionTimeoutPolicy.php';

use OneId\App\Auth\UserSessionTimeoutPolicy;

function oneid_is_technical_heartbeat_request(array $post): bool
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        return false;
    }
    $keys = array_values(array_diff(array_keys($post), ['_csrf_token']));
    sort($keys);
    return in_array($keys, [
        ['update_specific_token_datetime'],
        ['admin_step_up_status', 'purpose'],
        ['admin_step_up_renew'],
        ['user_session_status'],
        ['user_session_renew'],
        ['user_session_expire'],
    ], true);
}

function oneid_session_is_expired(
    int $now,
    int $createdAt,
    int $lastActivity,
    int $idleSeconds = UserSessionTimeoutPolicy::DEFAULT_IDLE_SECONDS
): bool
{
    return UserSessionTimeoutPolicy::isExpired($now, $createdAt, $lastActivity, $idleSeconds);
}

function oneid_session_next_activity(int $now, int $lastActivity, bool $technicalHeartbeat): int
{
    return $technicalHeartbeat ? $lastActivity : $now;
}

/** @return array<string, int> */
function oneid_session_deadline_state(int $now, int $createdAt, int $lastActivity, int $idleSeconds): array
{
    $idleRemaining = max(0, ($lastActivity + $idleSeconds) - $now);
    $absoluteRemaining = max(0, ($createdAt + UserSessionTimeoutPolicy::ABSOLUTE_SECONDS) - $now);

    return [
        'idle_timeout_seconds' => $idleSeconds,
        'idle_remaining_seconds' => $idleRemaining,
        'absolute_remaining_seconds' => $absoluteRemaining,
        'effective_remaining_seconds' => min($idleRemaining, $absoluteRemaining),
        'server_epoch' => $now,
    ];
}

function oneid_refresh_session_activity(?int $now = null): void
{
    if (session_status() !== PHP_SESSION_ACTIVE
        || ($_SESSION['login_status'] ?? '') !== 'true'
        || trim((string) ($_SESSION['login_user'] ?? '')) === ''
    ) {
        return;
    }
    $_SESSION['oneid_session_last_activity'] = $now ?? time();
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
    $_SESSION['oneid_session_created_at'] = (int) ($_SESSION['oneid_session_created_at'] ?? $now);
    $_SESSION['oneid_session_last_activity'] = (int) ($_SESSION['oneid_session_last_activity'] ?? $now);
}

function oneid_configured_session_idle_seconds(object $operation): int
{
    try {
        if (!method_exists($operation, 'get_system_config')) {
            throw new RuntimeException('Session configuration reader is unavailable.');
        }
        $configuration = $operation->get_system_config();
        if (!is_array($configuration)) {
            throw new RuntimeException('Session configuration was not found.');
        }

        return UserSessionTimeoutPolicy::idleSeconds($configuration['token_timeout'] ?? null);
    } catch (Throwable $exception) {
        error_log('User session timeout policy fallback reason=' . get_class($exception));
        return UserSessionTimeoutPolicy::DEFAULT_IDLE_SECONDS;
    }
}

/** @return array<string, int> */
function oneid_current_session_deadline_state(object $operation, ?int $now = null): array
{
    $currentTime = $now ?? time();
    return oneid_session_deadline_state(
        $currentTime,
        (int) ($_SESSION['oneid_session_created_at'] ?? $currentTime),
        (int) ($_SESSION['oneid_session_last_activity'] ?? $currentTime),
        oneid_configured_session_idle_seconds($operation)
    );
}

function oneid_set_configured_sso_cookie(object $operation, string $token): void
{
    $idleSeconds = oneid_configured_session_idle_seconds($operation);
    $lifetime = min($idleSeconds, UserSessionTimeoutPolicy::ABSOLUTE_SECONDS);
    if (($_SESSION['login_status'] ?? '') === 'true') {
        $lifetime = oneid_current_session_deadline_state($operation)['effective_remaining_seconds'];
    }
    oneid_set_sso_cookie($token, max(1, $lifetime));
}

function oneid_refresh_configured_sso_cookie(object $operation): void
{
    $token = oneid_sso_cookie_token();
    if ($token !== '') {
        oneid_set_configured_sso_cookie($operation, $token);
    }
}

function oneid_apply_configured_session_policy(object $operation): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    $now = time();
    $createdAt = (int) ($_SESSION['oneid_session_created_at'] ?? $now);
    $lastActivity = (int) ($_SESSION['oneid_session_last_activity'] ?? $now);
    $idleSeconds = oneid_configured_session_idle_seconds($operation);
    $technicalHeartbeat = oneid_is_technical_heartbeat_request($_POST ?? []);

    if (oneid_session_is_expired($now, $createdAt, $lastActivity, $idleSeconds)) {
        $expiredUser = ($_SESSION['login_status'] ?? '') === 'true'
            ? trim((string) ($_SESSION['login_user'] ?? ''))
            : '';
        if ($expiredUser !== '') {
            oneid_clear_sso_cookie();
            $ipAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
            if (filter_var($ipAddress, FILTER_VALIDATE_IP) === false) {
                $ipAddress = '0.0.0.0';
            }
            $detail = sprintf(
                'user=%s action=user_portal_session_expire outcome=expired reason=idle_or_absolute token_revoked=0',
                $expiredUser
            );
            try {
                $auditWritten = method_exists($operation, 'syslog_record')
                    && $operation->syslog_record(68, $detail, $ipAddress) === 1;
            } catch (Throwable) {
                $auditWritten = false;
            }
            if (!$auditWritten) {
                error_log('User portal session expiry audit unavailable');
            }
        }
        $_SESSION = [];
        session_regenerate_id(true);
        $createdAt = $now;
        $lastActivity = $now;
        if ($expiredUser !== '') {
            $_SESSION['oneid_portal_session_expired_at'] = $now;
        }
    }

    $_SESSION['oneid_session_created_at'] = $createdAt;
    $_SESSION['oneid_session_last_activity'] = oneid_session_next_activity(
        $now,
        $lastActivity,
        $technicalHeartbeat
    );
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
    // Expiry is a guard for the unauthenticated session that just ended. It
    // must never survive a successful authentication into the new session.
    unset($_SESSION['oneid_portal_session_expired_at']);
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
