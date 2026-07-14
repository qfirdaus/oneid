<?php

require_once __DIR__ . '/secrets.php';

/**
 * Compatibility-first protection for OneID integration endpoints.
 *
 * ONEID_API_AUTH_MODE:
 * - observe (default): record legacy/invalid calls without blocking them.
 * - enforce: require a valid per-client credential, endpoint and scope.
 */

function oneid_integration_request_id()
{
    static $requestId = null;
    if ($requestId === null) {
        try {
            $requestId = bin2hex(random_bytes(8));
        } catch (Throwable $e) {
            $requestId = hash('sha256', uniqid('', true) . mt_rand());
            $requestId = substr($requestId, 0, 16);
        }
    }
    return $requestId;
}

function oneid_integration_auth_mode()
{
    $mode = strtolower(trim(oneid_secret('ONEID_API_AUTH_MODE', false)));
    return $mode === 'enforce' ? 'enforce' : 'observe';
}

function oneid_integration_clients()
{
    static $clients = null;
    if ($clients !== null) {
        return $clients;
    }

    $raw = trim(oneid_secret('ONEID_API_CLIENTS_JSON', false));
    $decoded = $raw === '' ? [] : json_decode($raw, true);
    $clients = is_array($decoded) ? $decoded : [];
    return $clients;
}

function oneid_integration_header($name)
{
    $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    return trim((string) ($_SERVER[$key] ?? ''));
}

function oneid_integration_bearer_token()
{
    $authorization = oneid_integration_header('Authorization');
    if (preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)) {
        return trim($matches[1]);
    }
    return '';
}

function oneid_integration_client_ip()
{
    // REMOTE_ADDR is intentionally used. Forwarded headers are only safe when
    // the reverse proxy is explicitly trusted and normalized by the web server.
    return (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
}

function oneid_integration_audit($event, array $context = [])
{
    $record = array_merge([
        'component' => 'oneid_integration',
        'event' => $event,
        'request_id' => oneid_integration_request_id(),
        'ip' => oneid_integration_client_ip(),
    ], $context);
    error_log(json_encode($record, JSON_UNESCAPED_SLASHES));
}

function oneid_integration_json_error($status, $code, $message)
{
    http_response_code((int) $status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    header('X-OneID-Request-ID: ' . oneid_integration_request_id());
    echo json_encode([
        'status' => 'error',
        'code' => $code,
        'message' => $message,
        'request_id' => oneid_integration_request_id(),
    ]);
    exit;
}

function oneid_integration_client_allows(array $client, $field, $value)
{
    $allowed = $client[$field] ?? [];
    return is_array($allowed) && (in_array('*', $allowed, true) || in_array($value, $allowed, true));
}

function oneid_integration_rate_limit($identity, $endpoint, $enforce)
{
    $configured = (int) oneid_secret('ONEID_API_RATE_LIMIT_PER_MINUTE', false);
    $limit = $configured > 0 ? min($configured, 10000) : 600;
    $window = (int) floor(time() / 60);
    $key = hash('sha256', $identity . '|' . $endpoint);
    $path = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'oneid-api-' . $key . '.rate';
    $handle = @fopen($path, 'c+');
    if (!$handle) {
        oneid_integration_audit('rate_limit_unavailable', ['endpoint' => $endpoint]);
        return;
    }

    $count = 1;
    if (flock($handle, LOCK_EX)) {
        $stored = stream_get_contents($handle);
        $parts = explode(':', trim((string) $stored), 2);
        $storedWindow = isset($parts[0]) ? (int) $parts[0] : 0;
        $storedCount = isset($parts[1]) ? (int) $parts[1] : 0;
        $count = $storedWindow === $window ? max(0, $storedCount) + 1 : 1;
        rewind($handle);
        ftruncate($handle, 0);
        fwrite($handle, $window . ':' . $count);
        fflush($handle);
        flock($handle, LOCK_UN);
    }
    fclose($handle);

    if ($count > $limit) {
        oneid_integration_audit('rate_limit_exceeded', [
            'endpoint' => $endpoint,
            'identity' => $identity,
            'count' => $count,
            'limit' => $limit,
            'enforced' => $enforce,
        ]);
        if ($enforce) {
            oneid_integration_json_error(429, 'rate_limit_exceeded', 'Too many requests.');
        }
    }
}

function oneid_integration_guard($endpoint, $requiredScope)
{
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    header('X-OneID-Request-ID: ' . oneid_integration_request_id());

    $mode = oneid_integration_auth_mode();
    $enforce = $mode === 'enforce';
    $clientId = oneid_integration_header('X-OneID-Client-ID');
    $secret = oneid_integration_bearer_token();
    $clients = oneid_integration_clients();
    $client = $clientId !== '' && isset($clients[$clientId]) && is_array($clients[$clientId])
        ? $clients[$clientId]
        : null;
    $authenticated = false;
    $reason = 'missing_credentials';

    if ($client !== null && $secret !== '') {
        $expectedHash = strtolower(trim((string) ($client['secret_hash'] ?? '')));
        $presentedHash = hash('sha256', $secret);
        if (($client['status'] ?? 'active') !== 'active') {
            $reason = 'client_disabled';
        } elseif ($expectedHash === '' || !hash_equals($expectedHash, $presentedHash)) {
            $reason = 'invalid_secret';
        } elseif (!oneid_integration_client_allows($client, 'endpoints', $endpoint)) {
            $reason = 'endpoint_denied';
        } elseif (!oneid_integration_client_allows($client, 'scopes', $requiredScope)) {
            $reason = 'scope_denied';
        } else {
            $allowlist = $client['ip_allowlist'] ?? [];
            if (is_array($allowlist) && $allowlist !== [] && !in_array(oneid_integration_client_ip(), $allowlist, true)) {
                $reason = 'ip_denied';
            } else {
                $authenticated = true;
                $reason = 'authenticated';
            }
        }
    } elseif ($clientId !== '') {
        $reason = $client === null ? 'unknown_client' : 'missing_secret';
    }

    $identity = $authenticated ? $clientId : 'legacy:' . oneid_integration_client_ip();
    oneid_integration_rate_limit($identity, $endpoint, $enforce);
    oneid_integration_audit('api_access', [
        'endpoint' => $endpoint,
        'scope' => $requiredScope,
        'client_id' => $clientId === '' ? null : $clientId,
        'auth_result' => $reason,
        'mode' => $mode,
    ]);

    if (!$authenticated && $enforce) {
        $status = in_array($reason, ['endpoint_denied', 'scope_denied', 'ip_denied', 'client_disabled'], true) ? 403 : 401;
        oneid_integration_json_error($status, 'integration_access_denied', 'Integration credential is required or not authorized.');
    }

    return [
        'client_id' => $authenticated ? $clientId : null,
        'authenticated' => $authenticated,
        'mode' => $mode,
        'auth_result' => $reason,
    ];
}
