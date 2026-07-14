<?php

/**
 * Authenticated login/logout characterization for R5.2B0.
 *
 * Credentials are accepted only through the environment variables declared in
 * tests/characterization/r52_authenticated_logout_contracts.php. Values are
 * never printed or written to the repository.
 *
 * Usage:
 *   php tools/r52_authenticated_logout.php https://oneid.local user --insecure
 *   php tools/r52_authenticated_logout.php https://oneid.local admin --insecure
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(2);
}

$projectRoot = dirname(__DIR__);
$contracts = require $projectRoot . '/tests/characterization/r52_authenticated_logout_contracts.php';
$baseUrl = rtrim((string) ($argv[1] ?? ''), '/');
$role = strtolower((string) ($argv[2] ?? ''));
$insecure = in_array('--insecure', $argv, true);

if ($baseUrl === '' || filter_var($baseUrl, FILTER_VALIDATE_URL) === false || !isset($contracts[$role])) {
    fwrite(STDERR, "Usage: php tools/r52_authenticated_logout.php <base-url> <user|admin> [--insecure]\n");
    exit(2);
}

$contract = $contracts[$role];
$username = getenv($contract['username_env']);
$password = getenv($contract['password_env']);

if (!is_string($username) || trim($username) === '' || !is_string($password) || $password === '') {
    fwrite(
        STDERR,
        sprintf(
            "Missing test credentials: set %s and %s in the current shell environment.\n",
            $contract['username_env'],
            $contract['password_env']
        )
    );
    exit(2);
}

$cookieJar = tempnam(sys_get_temp_dir(), 'oneid-r52b0-');
if ($cookieJar === false) {
    fwrite(STDERR, "Unable to create temporary cookie jar.\n");
    exit(2);
}
chmod($cookieJar, 0600);
register_shutdown_function(static function () use ($cookieJar): void {
    if (is_file($cookieJar)) {
        @unlink($cookieJar);
    }
});

$failed = 0;
$checks = 0;
$report = static function (bool $ok, string $item, string $detail = '') use (&$failed, &$checks): void {
    $checks++;
    if (!$ok) {
        $failed++;
    }
    printf(
        "%s %-46s%s\n",
        $ok ? 'PASS' : 'FAIL',
        $item,
        $detail === '' ? '' : ' ' . $detail
    );
};

$request = static function (
    string $url,
    string $method = 'GET',
    array $form = [],
    array $headers = [],
    ?string $jar = null,
    ?string $explicitCookie = null
) use ($insecure): array {
    $handle = curl_init($url);
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYHOST => $insecure ? 0 : 2,
        CURLOPT_SSL_VERIFYPEER => !$insecure,
        CURLOPT_USERAGENT => 'OneID-R5.2B0-Characterization/1.0',
    ];
    if ($jar !== null) {
        $options[CURLOPT_COOKIEFILE] = $jar;
        $options[CURLOPT_COOKIEJAR] = $jar;
    }
    if ($explicitCookie !== null) {
        $options[CURLOPT_COOKIE] = $explicitCookie;
    }
    if ($method === 'POST') {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = http_build_query($form, '', '&', PHP_QUERY_RFC3986);
    }
    if ($headers !== []) {
        $options[CURLOPT_HTTPHEADER] = $headers;
    }
    curl_setopt_array($handle, $options);

    $raw = curl_exec($handle);
    $error = curl_error($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
    $contentType = strtolower((string) curl_getinfo($handle, CURLINFO_CONTENT_TYPE));
    $headerSize = (int) curl_getinfo($handle, CURLINFO_HEADER_SIZE);
    curl_close($handle);

    $responseHeaders = is_string($raw) ? substr($raw, 0, $headerSize) : '';
    $body = is_string($raw) ? substr($raw, $headerSize) : '';
    $location = '';
    if (preg_match('/^Location:\s*(.+)$/mi', $responseHeaders, $matches) === 1) {
        $location = trim($matches[1]);
    }

    return compact('error', 'status', 'contentType', 'body', 'location');
};

$readCookies = static function (string $jar): array {
    $cookies = [];
    $lines = @file($jar, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) {
        return $cookies;
    }
    foreach ($lines as $line) {
        if (str_starts_with($line, '#HttpOnly_')) {
            $line = substr($line, strlen('#HttpOnly_'));
        } elseif (str_starts_with($line, '#')) {
            continue;
        }
        $parts = explode("\t", $line);
        if (count($parts) >= 7) {
            $cookies[$parts[5]] = $parts[6];
        }
    }
    return $cookies;
};

$cookieHeader = static function (array $cookies): string {
    $pairs = [];
    foreach ($cookies as $name => $value) {
        $pairs[] = $name . '=' . $value;
    }
    return implode('; ', $pairs);
};

printf("R5.2B0 host=%s role=%s\n", $baseUrl, $role);

$landing = $request($baseUrl . '/', 'GET', [], [], $cookieJar);
$report(
    $landing['error'] === '' && $landing['status'] === 200 && str_starts_with($landing['contentType'], 'text/html'),
    'anonymous landing contract',
    'status=' . $landing['status']
);

$csrfToken = '';
if (preg_match('/X-CSRF-Token\'\s*:\s*("[a-f0-9]{64}")/i', $landing['body'], $matches) === 1) {
    $decoded = json_decode($matches[1], true);
    $csrfToken = is_string($decoded) ? $decoded : '';
}
$report($csrfToken !== '', 'CSRF token issued');

$anonymousDashboard = $request($baseUrl . $contract['dashboard'], 'GET', [], [], $cookieJar);
$report(
    $anonymousDashboard['error'] === ''
        && $anonymousDashboard['status'] === 302
        && $anonymousDashboard['location'] === $baseUrl . '/',
    'anonymous dashboard rejected',
    'status=' . $anonymousDashboard['status']
);

$beforeLoginCookies = $readCookies($cookieJar);
$sessionBefore = (string) ($beforeLoginCookies['PHPSESSID'] ?? '');
$report($sessionBefore !== '', 'anonymous PHP session issued');

$login = $request(
    $baseUrl . '/lib/q_func.php',
    'POST',
    ['auth' => 'auth', 'username' => $username, 'password' => $password],
    ['Accept: application/json', 'X-CSRF-Token: ' . $csrfToken],
    $cookieJar
);
$loginJson = json_decode($login['body'], true);
$loginResult = is_array($loginJson) ? (int) ($loginJson['login_status'] ?? 0) : null;
$loginOk = $login['error'] === ''
    && $login['status'] === 200
    && is_array($loginJson)
    && $loginResult === 1;
$loginReason = 'ok';
if ($login['error'] !== '') {
    $loginReason = 'transport_error';
} elseif ($login['status'] !== 200) {
    $loginReason = 'unexpected_http_status';
} elseif (!is_array($loginJson)) {
    $loginReason = 'invalid_json_body';
} elseif ($loginResult === 0) {
    $loginReason = 'authentication_rejected';
} elseif ($loginResult !== 1) {
    $loginReason = 'unexpected_login_status';
}
$report(
    $loginOk,
    'login contract',
    sprintf(
        'status=%d type=%s result=%s',
        $login['status'],
        $login['contentType'] !== '' ? $login['contentType'] : '-',
        $loginReason
    )
);

if (!$loginOk) {
    fwrite(STDERR, "Authentication did not succeed; remaining authenticated checks were not attempted.\n");
    printf("RESULT checks=%d failed=%d\n", $checks, $failed);
    exit(1);
}

$report(
    (string) ($loginJson['redirect_uri'] ?? '') === 'page/dashboard',
    'login redirect URI contract'
);

$authenticatedCookies = $readCookies($cookieJar);
$sessionAfter = (string) ($authenticatedCookies['PHPSESSID'] ?? '');
$report(
    $sessionAfter !== '' && $sessionBefore !== '' && !hash_equals($sessionBefore, $sessionAfter),
    'session ID rotated after login'
);
$report(isset($authenticatedCookies['sso_cre']) && $authenticatedCookies['sso_cre'] !== '', 'SSO cookie issued');
$capturedAuthenticatedCookie = $cookieHeader($authenticatedCookies);

$userDashboard = $request($baseUrl . '/page/dashboard.php', 'GET', [], [], $cookieJar);
$report(
    $userDashboard['error'] === ''
        && $userDashboard['status'] === 200
        && str_starts_with($userDashboard['contentType'], 'text/html'),
    'authenticated user dashboard',
    'status=' . $userDashboard['status']
);

$adminDashboard = $request($baseUrl . '/admin/dashboard.php', 'GET', [], [], $cookieJar);
$report(
    $adminDashboard['error'] === '' && $adminDashboard['status'] === $contract['admin_status'],
    'role authorization contract',
    'status=' . $adminDashboard['status'] . ' expected=' . $contract['admin_status']
);

$logout = $request($baseUrl . $contract['logout'], 'GET', [], [], $cookieJar);
$report(
    $logout['error'] === ''
        && $logout['status'] === 302
        && $logout['location'] === $baseUrl . '/',
    'logout redirect contract',
    'status=' . $logout['status']
);

$afterLogoutCookies = $readCookies($cookieJar);
$report(
    !isset($afterLogoutCookies['sso_cre']) || $afterLogoutCookies['sso_cre'] === '',
    'SSO cookie cleared'
);

$afterLogout = $request($baseUrl . $contract['dashboard'], 'GET', [], [], $cookieJar);
$report(
    $afterLogout['error'] === ''
        && $afterLogout['status'] === 302
        && $afterLogout['location'] === $baseUrl . '/',
    'logged-out cookie jar rejected',
    'status=' . $afterLogout['status']
);

$replay = $request(
    $baseUrl . $contract['dashboard'],
    'GET',
    [],
    [],
    null,
    $capturedAuthenticatedCookie
);
$report(
    $replay['error'] === '' && $replay['status'] === 302 && $replay['location'] === $baseUrl . '/',
    'pre-logout session replay rejected',
    'status=' . $replay['status']
);

printf("RESULT checks=%d failed=%d\n", $checks, $failed);
exit($failed === 0 ? 0 : 1);
