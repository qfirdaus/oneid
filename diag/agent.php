<?php
/**
 * Lightweight Server Agent v2.1 (upgrade)
 * Return JSON: PHP/web info, opcache, session, timezone, perms, MySQL (wajib),
 * dan Sybase + MSSQL (opsyen).
 *
 * Dipanggil oleh proxy:
 *   POST server_agent.php?token=YOUR_TOKEN
 *   Body: { "token": "YOUR_TOKEN" }
 */

declare(strict_types=1);

//////////////////// CONFIG ////////////////////
// WAJIB tukar token ini (jangan guna default)
const AGENT_TOKEN = 'ONEID*&#123upNMSECRETTOKEN7*@__ONEID';

// (Opsyen) limit IP caller (kosongkan kalau tak perlu)
$ALLOW_IPS = []; // contoh: ['123.123.123.123','10.10.0.0/16']

// ===== MySQL (WAJIB untuk health check DB utama) =====
$MYSQL_DSN  = 'mysql:host=172.16.2.247;dbname=ssodb;charset=utf8mb4'; //'mysql:host=127.0.0.1;dbname=test;charset=utf8mb4'
$MYSQL_USER = 'ssoupnm';
$MYSQL_PASS = '$$0@Upnm';
$MYSQL_TIMEOUT_MS = 800;

// ===== Sybase (OPSYEN) - hanya jika ada =====
// Contoh DSN PDO dblib: 'dblib:host=SYBASE_HOST:5000;dbname=NAMA_DB;charset=UTF-8'
// ===== Sybase (OPSYEN) - eHRM via DSN ODBC / dblib =====
// Config asas Sybase eHRM
$SYBASE_USER       = 'ehrm';
$SYBASE_PASS       = 'eHRM@2025';
$SYBASE_DB         = 'ehrmdb';
// Nama DSN dalam ODBC (Windows: ODBC Data Sources → System DSN)
// Contoh: dsn_sybase_ehrmdb
$SYBASE_ODBC_DSN   = 'dsn_sybase_ehrmdb';

// Host & port untuk fallback dblib (contoh bila dalam Docker / Linux)
$SYBASE_HOST       = '172.16.2.14';
$SYBASE_PORT       = '5004'; // atau 5000 kalau perlu

$SYBASE_TIMEOUT_MS = 1200;


// ===== MSSQL (OPSYEN) - hanya jika ada =====
// Contoh DSN PDO sqlsrv: 'sqlsrv:Server=192.168.1.10,1433;Database=NamaDB'
$MSSQL_DSN  = '';
$MSSQL_USER = '';
$MSSQL_PASS = '';
$MSSQL_TIMEOUT_MS = 1200;

// Paths untuk semak permission public (writable?)
$PUBLIC_PATHS = [
    __DIR__ . '/../public',
    __DIR__ . '/../public_html',
    dirname(__DIR__) . '/public',
];

////////////////////////////////////////////////

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// ===== Helper deny =====
function deny(string $msg, int $code = 403): void
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ========== Auth: token ==========
$tokenGet  = $_GET['token']  ?? '';
$rawBody   = file_get_contents('php://input');
$tokenBody = '';

if ($rawBody) {
    $j = json_decode($rawBody, true);
    if (is_array($j) && isset($j['token'])) {
        $tokenBody = (string) $j['token'];
    }
}

$tokenUsed = $tokenBody !== '' ? $tokenBody : $tokenGet;

if (!hash_equals(AGENT_TOKEN, (string) $tokenUsed)) {
    deny('Invalid token', 401);
}

// ========== IP allowlist (opsyen) ==========
if (!empty($ALLOW_IPS)) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ok = false;

    if ($ip !== '') {
        foreach ($ALLOW_IPS as $rule) {
            if (strpos($rule, '/') !== false) {
                // CIDR (IPv4 basic)
                [$subnet, $mask] = explode('/', $rule, 2);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) &&
                    filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    $ipLong   = ip2long($ip);
                    $netLong  = ip2long($subnet);
                    $maskLong = -1 << (32 - (int) $mask);
                    if (($ipLong & $maskLong) === ($netLong & $maskLong)) {
                        $ok = true;
                        break;
                    }
                }
            } else {
                if ($ip === $rule) {
                    $ok = true;
                    break;
                }
            }
        }
    }

    if (!$ok) {
        deny('IP not allowed', 403);
    }
}

// ===== Helper ping DB generic (PDO) =====
function ping_pdo(string $dsn, string $user, string $pass, int $timeoutMs, string $label): array
{
    if ($dsn === '' || $user === '') {
        return [
            'configured'  => false,
            'ok'          => false,
            'driver'      => $label,
            'latency_ms'  => null,
            'error'       => null,
            'server_info' => null,
        ];
    }

    $result = [
        'configured'  => true,
        'ok'          => false,
        'driver'      => $label,
        'latency_ms'  => null,
        'error'       => null,
        'server_info' => null,
    ];

    $t0 = microtime(true);
    try {
        $pdo = new PDO(
            $dsn,
            $user,
            $pass,
            [
                PDO::ATTR_TIMEOUT => max(1, (int) ceil($timeoutMs / 1000)),
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]
        );
        $pdo->query('SELECT 1')->fetch();
        $ms = (microtime(true) - $t0) * 1000;
        $result['ok']          = true;
        $result['latency_ms']  = round($ms, 1);
        $result['server_info'] = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
    } catch (Throwable $e) {
        $result['ok']    = false;
        $result['error'] = $e->getMessage();
    }

    return $result;
}

// ===== Helper: detect dir listing risk based on docroot =====
function guess_dir_listing_risk(?string $docRoot): bool
{
    if (!$docRoot || !is_dir($docRoot)) {
        return false;
    }

    // Kalau ada mana-mana index*/default* → risiko rendah
    $candidates = [
        'index.php', 'index.html', 'index.htm',
        'default.php', 'default.html', 'home.php',
    ];

    foreach ($candidates as $f) {
        if (file_exists(rtrim($docRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $f)) {
            return false;
        }
    }

    // Tiada index/default → kemungkinan directory listing ON
    return true;
}

// ===== Helper: guess environment (dev/staging/prod) =====
function guess_env(array $phpInfo): string
{
    $display = $phpInfo['display_errors'] ?? false;
    $expose  = $phpInfo['expose_php'] ?? false;
    $tz      = $phpInfo['timezone'] ?? '';

    if ($display && $expose) {
        return 'development';
    }

    if (!$display && !$expose) {
        // Ada timezone & opcache enable → assume production/staging
        $opEnabled = $phpInfo['opcache']['enabled'] ?? false;
        if ($opEnabled && $tz) {
            return 'production';
        }
        return 'staging';
    }

    return 'mixed';
}

// ===== MySQL ping (WAJIB) =====
$mysql = [
    'configured'  => false,
    'ok'          => false,
    'driver'      => 'mysql',
    'latency_ms'  => null,
    'error'       => null,
    'server_info' => null,
];

if ($MYSQL_DSN && $MYSQL_USER) {
    $mysql = ping_pdo($MYSQL_DSN, $MYSQL_USER, $MYSQL_PASS, $MYSQL_TIMEOUT_MS, 'mysql');
} else {
    $mysql['configured'] = false;
}

// ===== Sybase (opsyen) =====
$sybase = [
    'configured'  => false,
    'ok'          => false,
    'driver'      => 'sybase',
    'latency_ms'  => null,
    'error'       => null,
    'server_info' => null,
];

if ($SYBASE_DSN && $SYBASE_USER) {
    $sybase = ping_pdo($SYBASE_DSN, $SYBASE_USER, $SYBASE_PASS, $SYBASE_TIMEOUT_MS, 'sybase');
} else {
    $sybase['configured'] = false;
}

// ===== MSSQL (opsyen) =====
$mssql = [
    'configured'  => false,
    'ok'          => false,
    'driver'      => 'mssql',
    'latency_ms'  => null,
    'error'       => null,
    'server_info' => null,
];

if ($MSSQL_DSN && $MSSQL_USER) {
    $mssql = ping_pdo($MSSQL_DSN, $MSSQL_USER, $MSSQL_PASS, $MSSQL_TIMEOUT_MS, 'mssql');
} else {
    $mssql['configured'] = false;
}

// ===== PHP info =====
$loadedExt = get_loaded_extensions();
sort($loadedExt);

$phpInfo = [
    'version'            => PHP_VERSION,
    'sapi'               => PHP_SAPI,
    'extensions'         => $loadedExt,
    'display_errors'     => (bool) ini_get('display_errors'),
    'memory_limit'       => ini_get('memory_limit'),
    'max_execution_time' => (int) ini_get('max_execution_time'),
    'upload_max_filesize'=> ini_get('upload_max_filesize'),
    'post_max_size'      => ini_get('post_max_size'),
    'expose_php'         => (bool) ini_get('expose_php'),
    'timezone'           => ini_get('date.timezone') ?: null,
    'php_ini'            => php_ini_loaded_file() ?: null,
    'disabled_functions' => ini_get('disable_functions') ?: '',
    'session' => [
        'cookie_secure'     => (bool) ini_get('session.cookie_secure'),
        'cookie_httponly'   => (bool) ini_get('session.cookie_httponly'),
        'cookie_samesite'   => ini_get('session.cookie_samesite'),
        'use_strict_mode'   => (bool) ini_get('session.use_strict_mode'),
        'sid_length'        => (int) ini_get('session.sid_length'),
    ],
    'opcache' => [
        'enabled'                 => (bool) ini_get('opcache.enable'),
        'jit_buffer_size'         => (string) ini_get('opcache.jit_buffer_size'),
        'memory_consumption'      => ini_get('opcache.memory_consumption'),
        'max_accelerated_files'   => ini_get('opcache.max_accelerated_files'),
        'interned_strings_buffer' => ini_get('opcache.interned_strings_buffer'),
    ],
];

// ===== Web/server hints =====
$server = [
    'server'        => $_SERVER['SERVER_SOFTWARE'] ?? '',
    'protocol'      => $_SERVER['SERVER_PROTOCOL'] ?? '',
    'http2'         => (!empty($_SERVER['HTTP2']) || (isset($_SERVER['SERVER_PROTOCOL']) && stripos($_SERVER['SERVER_PROTOCOL'], 'HTTP/2') !== false)),
    'https'         => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'hostname'      => gethostname(),
    'ip'            => $_SERVER['SERVER_ADDR'] ?? ($_SERVER['LOCAL_ADDR'] ?? null),
    'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? null,
];

// Tambah env & dir_listing untuk Stage 2 scoring
$server['env']         = guess_env($phpInfo);
$server['dir_listing'] = guess_dir_listing_risk($server['document_root']);

// ===== Writable public? =====
$canWritePublic = false;
$whichPath      = null;
foreach ($PUBLIC_PATHS as $p) {
    if ($p && is_dir($p) && is_writable($p)) {
        $canWritePublic = true;
        $whichPath = $p;
        break;
    }
}

// ringkas ext essentials
$need = ['curl', 'mbstring', 'openssl', 'pdo', 'pdo_mysql', 'intl', 'json'];
$extStatus = [];
foreach ($need as $e) {
    $extStatus[$e] = extension_loaded($e);
}

echo json_encode([
    'ok'   => true,
    'php'  => $phpInfo,
    'server' => $server,
    'paths'=> [
        'can_write_public' => $canWritePublic,
        'which'            => $whichPath,
        'cwd'              => getcwd(),
        'script'           => $_SERVER['SCRIPT_FILENAME'] ?? __FILE__,
    ],
    'mysql' => $mysql,
    'sybase'=> $sybase,
    'mssql' => $mssql,
    'extensions_needed' => $extStatus,
    'time'  => gmdate('c'),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
