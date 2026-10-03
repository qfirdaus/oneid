<?php
declare(strict_types=1);
// Dedicated document root only. No route is added to the existing OneID web host.
header('Cache-Control: no-store'); header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff'); header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
header('X-Frame-Options: DENY');
$root = dirname(__DIR__);
require_once $root.'/app/Auth/MobileOidc/RuntimeEnvironment.php';
$configFile = getenv('ONEID_MOBILE_CONFIG') ?: $root . '/.private/mobile-oidc-hosted.php';
try {
    $real = realpath($configFile);
    if (!in_array($root, ['/var/www/oneid-uat','/var/www/oneid'], true) || !$real || !str_starts_with($real, $root . '/.private/')
        || (fileperms($real) & 0027) !== 0) { http_response_code(404); exit; }
    // Runtime kill switch must not wait for OPcache's timestamp revalidation interval.
    if (function_exists('opcache_invalidate')) opcache_invalidate($real, true);
    $config = require $real;
    // Disabled response occurs before loading libraries, DB, SMTP or PHP session.
    if (!is_array($config) || ($config['enabled'] ?? false) !== true || !in_array(($config['environment'] ?? ''), ['staging','production'], true)) { http_response_code(404); exit; }
    if (isset($config['pilot_until']) && (!is_int($config['pilot_until']) || time() >= $config['pilot_until'])) { http_response_code(404); exit; }
    $environment = \OneId\App\Auth\MobileOidc\RuntimeEnvironment::validate($root,$config);
    $fixture = ($config['loopback_fixture'] ?? false) === true;
    $origin = parse_url($config['origin']);
    $expectedHost = $origin['host'] . (isset($origin['port']) ? ':' . $origin['port'] : '');
    if (($_SERVER['HTTP_HOST'] ?? '') !== $expectedHost
        || ($fixture && (PHP_SAPI !== 'cli-server' || ($origin['host'] ?? '') !== '127.0.0.1' || ($_SERVER['REMOTE_ADDR'] ?? '') !== '127.0.0.1'))
        || (!$fixture && (($origin['scheme'] ?? '') !== 'https' || strtolower((string) ($_SERVER['HTTPS'] ?? '')) !== 'on'))
        || ($config['database_scope_confirmed'] ?? false) !== true) { http_response_code(503); exit; }
    if (!$fixture) {
        // Pilot snapshots must not freeze the operational user MFA configuration.
        $runtimeFile = $root . '/.private/runtime.php';
        if (function_exists('opcache_invalidate')) opcache_invalidate($runtimeFile, true);
        $runtime = require $runtimeFile;
        if (($runtime['ONEID_ENVIRONMENT']??'')!==$environment) throw new RuntimeException('MOBILE_RUNTIME_ENVIRONMENT_MISMATCH');
        $read = static function (string $key) use ($runtime): mixed {
            $value = getenv($key);
            return $value !== false && $value !== '' ? $value : ($runtime[$key] ?? null);
        };
        $mode = $read('ONEID_USER_MFA_MODE');
        if (!in_array($mode, ['OFF','ENROLLMENT','PILOT_ENFORCED','ENFORCED'], true)) throw new RuntimeException('MOBILE_MFA_RUNTIME_INVALID');
        $config['mfa_runtime_mode'] = $mode;
        $config['mfa_activation_authorized'] = filter_var($read('ONEID_USER_MFA_ACTIVATION_AUTHORIZED'), FILTER_VALIDATE_BOOLEAN);
    }
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $routes = ['/mobile/mydigitalid/callback' => ['GET'], '/login' => ['GET','POST'], '/consent' => ['GET'], '/token-hook' => ['POST'], '/mobile/session' => ['GET'], '/mobile/logout' => ['POST'], '/login.css' => ['GET']];
    if (!isset($routes[$path])) { http_response_code(404); exit; }
    if (!in_array($method, $routes[$path], true)) { header('Allow: ' . implode(', ', $routes[$path])); http_response_code(405); exit; }
    if ($path === '/login.css') { header('Content-Type: text/css'); readfile(__DIR__ . '/login.css'); exit; }
    if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 65536) { http_response_code(413); exit; }
    if ($path === '/token-hook' && !hash_equals((string) ($config['hook_key'] ?? ''), (string) ($_SERVER['HTTP_X_ONEID_HOOK_KEY'] ?? ''))) { http_response_code(403); exit; }
    require_once $root . '/app/Auth/MobileOidc/bootstrap.php';
    require_once $root . '/app/Maintenance/MaintenancePolicy.php';
    date_default_timezone_set('UTC');
    $pdo = new PDO($config['dsn'], $config['db_user'], $config['db_password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
    $available = static function () use ($pdo): bool {
        $row = $pdo->query('SELECT maintenance_mode,maintenance_starts_at,maintenance_ends_at FROM sys_config WHERE singleton_key=1')->fetch(PDO::FETCH_ASSOC);
        if (!$row || !in_array($row['maintenance_mode'], ['OFF','SCHEDULED','INDEFINITE'], true)) return false;
        if ($row['maintenance_mode'] === 'SCHEDULED' && (strtotime((string) $row['maintenance_starts_at']) === false || strtotime((string) $row['maintenance_ends_at']) === false
            || strtotime((string) $row['maintenance_ends_at']) <= strtotime((string) $row['maintenance_starts_at']))) return false;
        return !\OneId\App\Maintenance\MaintenancePolicy::evaluate($row)['active'];
    };
    $delivery = $fixture ? ($config['delivery_factory'])() : new \OneId\App\Auth\MobileOidc\ConfiguredOtpDelivery($config['smtp']);
    $primitive = new \OneId\App\Auth\UserMfa\UserMfaTotpPrimitive(new \OneId\App\Auth\TotpSecretCipher(\OneId\App\Auth\TotpKeyring::fromFile($config['totp_keyring'])));
    $adapter = \OneId\App\Auth\MobileOidc\HostedAdapterFactory::create($pdo, $config, $delivery, $primitive, $available);
    $provider = new \OneId\App\Auth\MobileOidc\HydraAdminClient($config['admin_url'], $config['issuer']);
    $protocol = new \OneId\App\Auth\MobileOidc\MobileProtocolService($adapter, $provider, $config['clients'], $config['hook_key']);
    if ($path === '/login' || $path === '/mobile/mydigitalid/callback') {
        $myDigitalId = null;
        if (($config['mydigitalid_enabled'] ?? false) === true) {
            try { $myDigitalId = \OneId\App\Auth\MobileOidc\MobileMyDigitalId::fromRuntime($pdo,$adapter,$environment); }
            catch (\Throwable) { error_log('Mobile MyDigital ID configuration unavailable'); }
        }
        // Device testing (Chrome 153) blocks native callback redirects following POST
        // whenever form-action is present, even with the callback explicitly allowed.
        // Omit only for hosted mobile UI. Keep script/default restrictions, Origin +
        // CSRF validation in HostedLogin and exact provider redirect URI allowlists.
        header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'self'; frame-ancestors 'none'; base-uri 'none'");
        if ($path === '/mobile/mydigitalid/callback' && !$myDigitalId) { http_response_code(404); exit; }
        $recovery = new \OneId\App\Auth\MobileOidc\MobilePasswordRecovery($pdo, new \OneId\App\Auth\MobileOidc\PdoIdentitySource($pdo, $config['mfa_runtime_mode'], $config['mfa_activation_authorized'], $available, true, $environment), $fixture ? $delivery : new \OneId\App\Auth\MobileOidc\ConfiguredOtpDelivery($config['smtp'], true), $config['binding_key']);
        (new \OneId\App\Auth\MobileOidc\HostedLogin($adapter,$config,$myDigitalId,$recovery))->handle($method); exit;
    }
    if ($path === '/consent') {
        if (!is_string($_GET['consent_challenge'] ?? null)) { http_response_code(400); exit; }
        header('Location: ' . $protocol->consent($_GET['consent_challenge']), true, 303); exit;
    }
    if ($path === '/token-hook') {
        $key = (string) ($_SERVER['HTTP_X_ONEID_HOOK_KEY'] ?? '');
        if (!hash_equals($config['hook_key'], $key)) { http_response_code(403); exit; }
        if (!str_starts_with(strtolower($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json')) { http_response_code(415); exit; }
        $raw = file_get_contents('php://input', false, null, 0, 65537);
        if (strlen($raw) > 65536) { http_response_code(413); exit; }
        $body = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($body)) { http_response_code(400); exit; }
        $result = $protocol->hook($key, $body);
    } else $result = $protocol->status((string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ''), $path === '/mobile/logout');
    http_response_code($result['status']);
    if (isset($result['body'])) { header('Content-Type: application/json'); echo json_encode($result['body'], JSON_THROW_ON_ERROR); }
} catch (Throwable $error) {
    // Log only a correlation and class; no upstream body, query, credential or challenge.
    $reference = bin2hex(random_bytes(8));
    error_log('Mobile OIDC unavailable reference=' . $reference . ' exception=' . get_class($error));
    http_response_code(503); header('Content-Type: application/json');
    echo json_encode(['error' => 'TEMPORARILY_UNAVAILABLE', 'reference' => $reference]);
}
