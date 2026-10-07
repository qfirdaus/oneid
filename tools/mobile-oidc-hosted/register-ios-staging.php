<?php
declare(strict_types=1);

// Register the approved iOS public client on OneID staging only.
if (PHP_SAPI !== 'cli' || !in_array($argv[1] ?? '', ['--check', '--apply'], true)) {
    fwrite(STDERR, "Usage: php register-ios-staging.php --check|--apply\n");
    exit(2);
}
$apply = ($argv[1] ?? '') === '--apply';
if ($apply && posix_geteuid() !== 0) {
    fwrite(STDERR, "STOP: --apply requires sudo.\n");
    exit(2);
}
$root = dirname(__DIR__, 2);
if (realpath($root) !== '/var/www/oneid-uat') {
    fwrite(STDERR, "STOP: staging root required.\n");
    exit(2);
}

function iosProvider(string $path, ?array $body = null): array
{
    $ch = curl_init('http://127.0.0.1:24145' . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_PROXY => '',
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    ]);
    if ($body !== null) {
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($body, JSON_THROW_ON_ERROR)]);
    }
    $raw = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if ($raw === false) {
        throw new RuntimeException('PROVIDER_UNAVAILABLE');
    }
    return [$status, $raw === '' ? [] : json_decode($raw, true, 32, JSON_THROW_ON_ERROR)];
}

try {
    $path = $root . '/.private/mobile-oidc-hosted.php';
    if (is_link($path) || fileowner($path) !== 0) {
        throw new RuntimeException('CONFIG_OWNER_INVALID');
    }
    $config = require $path;
    if (($config['environment'] ?? '') !== 'staging'
        || ($config['issuer'] ?? '') !== 'https://oneid-uat.upnm.edu.my'
        || ($config['admin_url'] ?? '') !== 'http://127.0.0.1:24145') {
        throw new RuntimeException('STAGING_REQUIRED');
    }
    $client = json_decode(
        (string) file_get_contents($root . '/deployment/mobile-oidc/ios-staging-client.json'),
        true,
        32,
        JSON_THROW_ON_ERROR
    );
    $id = 'upnm-mobile-ios-staging';
    $uri = 'com.upnmmobile1.app://oneid/callback';
    if (($client['client_id'] ?? '') !== $id
        || ($client['client_name'] ?? '') !== 'UPNM Mobile iOS staging'
        || ($client['redirect_uris'] ?? []) !== [$uri]
        || ($client['grant_types'] ?? []) !== ['authorization_code', 'refresh_token']
        || ($client['response_types'] ?? []) !== ['code']
        || ($client['scope'] ?? '') !== 'openid profile offline_access mobile:session'
        || ($client['audience'] ?? []) !== ['oneid-mobile-session']
        || ($client['token_endpoint_auth_method'] ?? '') !== 'none') {
        throw new RuntimeException('CLIENT_CONTRACT_CHANGED');
    }
    if (isset($config['clients'][$id]) && $config['clients'][$id] !== [$uri]) {
        throw new RuntimeException('ADAPTER_CLIENT_CONFLICT');
    }
    [$status, $existing] = iosProvider('/admin/clients/' . $id);
    if ($status !== 404 && $status !== 200) {
        throw new RuntimeException('PROVIDER_LOOKUP_FAILED');
    }
    if (!$apply) {
        echo "CHECK PASS: iOS staging contract is valid; provider status=" . ($status === 404 ? 'NOT_REGISTERED' : 'REGISTERED') . ".\n";
        echo "client_id: {$id}\nredirect_uri: {$uri}\npublic_client: true\nPKCE: S256 required\n";
        exit(0);
    }
    if ($status === 404) {
        [$status, $existing] = iosProvider('/admin/clients', $client);
        if ($status !== 201) {
            throw new RuntimeException('REGISTRATION_FAILED');
        }
    }
    foreach ($client as $key => $value) {
        $actual = $existing[$key] ?? null;
        if (is_array($value) && is_array($actual)) {
            sort($value);
            sort($actual);
        }
        if ($actual !== $value) {
            throw new RuntimeException('PROVIDER_CLIENT_CONFLICT');
        }
    }
    umask(0077);
    $backup = $path . '.before-ios-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4));
    if (!copy($path, $backup) || !chmod($backup, 0600)) {
        throw new RuntimeException('BACKUP_FAILED');
    }
    $config['clients'][$id] = [$uri];
    $tmp = $path . '.' . bin2hex(random_bytes(8));
    if (file_put_contents($tmp, "<?php\nreturn " . var_export($config, true) . ";\n") === false
        || !chmod($tmp, 0640)
        || !chgrp($tmp, 'www-data')
        || !rename($tmp, $path)) {
        throw new RuntimeException('INSTALL_FAILED');
    }
    echo "SUCCESS: iOS staging client registered and adapter allowlist updated.\n";
    echo "client_id: {$id}\nredirect_uri: {$uri}\nNo client secret. Existing clients and web login preserved.\n";
    echo "backup: {$backup}\n";
} catch (Throwable $e) {
    if (isset($tmp) && is_file($tmp)) {
        unlink($tmp);
    }
    fwrite(STDERR, "STOP: iOS registration/configuration incomplete (" . $e->getMessage() . "). Existing credentials were not printed.\n");
    exit(1);
}
