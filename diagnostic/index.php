<?php
/**
 * AI-NOC Monitoring Agent v3 (PHP 7.4+ / 8.x)
 * Single-file monitoring agent
 */
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Kuala_Lumpur');

/* =========================
   PHP COMPATIBILITY LAYER
========================= */

if (!function_exists('str_contains')) {
  function str_contains($haystack, $needle) {
    return $needle !== '' && strpos((string)$haystack, (string)$needle) !== false;
  }
}
if (!function_exists('str_starts_with')) {
  function str_starts_with($haystack, $needle) {
    return strpos((string)$haystack, (string)$needle) === 0;
  }
}
if (!function_exists('str_ends_with')) {
  function str_ends_with($haystack, $needle) {
    $haystack = (string)$haystack;
    $needle = (string)$needle;
    if ($needle === '') return true;
    $len = strlen($needle);
    if ($len > strlen($haystack)) return false;
    return substr($haystack, -$len) === $needle;
  }
}

/* =========================
   CONFIG
========================= */

$TOKEN = "6ca06f141d8b5fdc3adf1a0e276daa0b6462b10a43f7fd2d207ac56c21add692";
$AGENT_VERSION = '13.0-ai-noc-v3-php8';

$DEFAULT_APACHE_VHOST = 'C:/xampp/apache/conf/extra/httpd-vhosts.conf';
$MAX_DISCOVER_FILES = 40;
$MAX_VHOSTS = 80;
$HTTP_CONNECT_TIMEOUT = 3;
$HTTP_TOTAL_TIMEOUT = 6;

/* =========================
   AUTH
========================= */

$mode = strtolower(trim((string)($_GET['mode'] ?? 'full')));
if ($mode === '') $mode = 'full';

if ((string)($_GET['token'] ?? '') !== $TOKEN) {
  http_response_code(403);
  echo json_encode([
    'ok' => false,
    'error' => 'invalid token'
  ]);
  exit;
}

$agentStart = microtime(true);

/* =========================
   HELPERS
========================= */

function safe_json($data) {
  return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}

function normalize_host($host) {
  $host = strtolower(trim((string)$host));
  $host = preg_replace('/:\d+$/', '', $host);
  return (string)$host;
}

function valid_host($host) {
  if ($host === '') return false;
  return (bool)preg_match('/^[a-z0-9][a-z0-9\.\-]{0,252}[a-z0-9]$/i', $host);
}

function is_reserved_or_dummy_host($host) {
  $h = normalize_host($host);
  if ($h === '') return true;
  if (str_ends_with($h, '.example.com')) return true;
  if (str_ends_with($h, '.example.org')) return true;
  if (str_ends_with($h, '.example.net')) return true;
  if (str_ends_with($h, '.test')) return true;
  if (str_ends_with($h, '.invalid')) return true;
  if (str_ends_with($h, '.localhost')) return true;
  return false;
}

function command_exists($cmd) {
  if (!function_exists('shell_exec')) return false;
  $family = strtoupper((string)PHP_OS_FAMILY);
  if ($family === 'WINDOWS') {
    $out = @shell_exec('where ' . escapeshellarg($cmd) . ' 2>NUL');
    return is_string($out) && trim($out) !== '';
  }
  $out = @shell_exec('command -v ' . escapeshellarg($cmd) . ' 2>/dev/null');
  return is_string($out) && trim($out) !== '';
}

function run_cmd($cmd) {
  if (!function_exists('shell_exec')) return '';
  $out = @shell_exec($cmd);
  return is_string($out) ? trim($out) : '';
}

function parse_key_value_lines($text) {
  $out = [];
  $lines = preg_split('/\r\n|\r|\n/', (string)$text);
  foreach ($lines as $line) {
    $line = trim($line);
    if ($line === '') continue;
    $parts = preg_split('/\s+/', $line, 2);
    if (count($parts) === 2) {
      $out[strtolower($parts[0])] = trim($parts[1]);
    }
  }
  return $out;
}

function detect_os_family() {
  $f = (string)(PHP_OS_FAMILY ?? 'Unknown');
  if ($f === '') $f = 'Unknown';
  return $f;
}

function detect_web_server() {
  $raw = strtolower((string)($_SERVER['SERVER_SOFTWARE'] ?? ''));
  if ($raw === '') return 'unknown';
  if (str_contains($raw, 'nginx')) return 'nginx';
  if (str_contains($raw, 'apache')) return 'Apache';
  if (str_contains($raw, 'iis')) return 'IIS';
  return (string)($_SERVER['SERVER_SOFTWARE'] ?? 'unknown');
}

/* =========================
   DISCOVERY: VHOST
========================= */

function discover_candidate_config_files($osFamily, $defaultApacheVhost, $maxFiles) {
  $files = [];

  if ($osFamily === 'Windows') {
    if (is_file($defaultApacheVhost)) $files[] = $defaultApacheVhost;
  } else {
    $linuxCandidates = [
      '/etc/apache2/sites-enabled',
      '/etc/httpd/conf.d',
      '/etc/nginx/sites-enabled',
      '/etc/nginx/conf.d'
    ];

    foreach ($linuxCandidates as $dir) {
      if (!is_dir($dir)) continue;
      $items = @scandir($dir);
      if (!is_array($items)) continue;
      foreach ($items as $it) {
        if ($it === '.' || $it === '..') continue;
        $full = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $it;
        if (is_file($full)) {
          $files[] = $full;
          if (count($files) >= $maxFiles) break 2;
        }
      }
    }
  }

  $uniq = [];
  foreach ($files as $f) $uniq[$f] = $f;
  return array_values($uniq);
}

function parse_apache_vhosts_from_content($content) {
  $out = [];
  if (!is_string($content) || trim($content) === '') return $out;

  if (!preg_match_all('/<VirtualHost[^>]*>(.*?)<\/VirtualHost>/si', $content, $blocks)) {
    return $out;
  }

  foreach ($blocks[1] as $block) {
    $docroot = null;
    $hosts = [];

    if (preg_match('/DocumentRoot\s+"?([^"\n\r]+)"?/i', $block, $mDr)) {
      $docroot = trim((string)$mDr[1]);
    }

    if (preg_match('/ServerName\s+([^\s\n\r]+)/i', $block, $mSn)) {
      $hosts[] = trim((string)$mSn[1]);
    }

    if (preg_match_all('/ServerAlias\s+([^\n\r]+)/i', $block, $mSa)) {
      foreach ($mSa[1] as $line) {
        $parts = preg_split('/\s+/', trim((string)$line));
        foreach ($parts as $h) {
          $h = trim((string)$h);
          if ($h === '' || str_contains($h, '*')) continue;
          $hosts[] = $h;
        }
      }
    }

    foreach ($hosts as $h) {
      $h = normalize_host($h);
      if (!valid_host($h)) continue;
      $out[$h] = ['domain' => $h, 'document_root' => $docroot];
    }
  }

  return array_values($out);
}

function parse_nginx_vhosts_from_content($content) {
  $out = [];
  if (!is_string($content) || trim($content) === '') return $out;

  if (!preg_match_all('/server\s*\{(.*?)\}/si', $content, $blocks)) {
    return $out;
  }

  foreach ($blocks[1] as $block) {
    $docroot = null;
    $hosts = [];

    if (preg_match('/root\s+([^;\n\r]+);/i', $block, $mRoot)) {
      $docroot = trim((string)$mRoot[1], " \t\n\r\0\x0B\"");
    }

    if (preg_match('/server_name\s+([^;]+);/i', $block, $mSn)) {
      $parts = preg_split('/\s+/', trim((string)$mSn[1]));
      foreach ($parts as $h) {
        $h = trim((string)$h);
        if ($h === '' || str_contains($h, '*') || $h === '_') continue;
        $hosts[] = $h;
      }
    }

    foreach ($hosts as $h) {
      $h = normalize_host($h);
      if (!valid_host($h)) continue;
      $out[$h] = ['domain' => $h, 'document_root' => $docroot];
    }
  }

  return array_values($out);
}

function discover_vhosts($osFamily, $defaultApacheVhost, $maxFiles, $maxVhosts) {
  $files = discover_candidate_config_files($osFamily, $defaultApacheVhost, $maxFiles);
  $all = [];

  foreach ($files as $f) {
    $raw = @file_get_contents($f);
    if (!is_string($raw) || $raw === '') continue;

    $isNginx = (str_contains(strtolower($f), 'nginx') || preg_match('/\bserver\s*\{/', $raw));
    $parsed = $isNginx ? parse_nginx_vhosts_from_content($raw) : parse_apache_vhosts_from_content($raw);

    foreach ($parsed as $v) {
      $d = normalize_host((string)($v['domain'] ?? ''));
      if (!valid_host($d) || is_reserved_or_dummy_host($d)) continue;
      $all[$d] = [
        'domain' => $d,
        'document_root' => (string)($v['document_root'] ?? '')
      ];
      if (count($all) >= $maxVhosts) break 2;
    }
  }

  if (empty($all)) {
    $hh = normalize_host((string)($_SERVER['HTTP_HOST'] ?? ''));
    if ($hh !== '' && valid_host($hh) && !is_reserved_or_dummy_host($hh)) {
      $all[$hh] = ['domain' => $hh, 'document_root' => (string)($_SERVER['DOCUMENT_ROOT'] ?? '')];
    }
  }

  return [
    'vhosts' => array_values($all),
    'config_files' => $files
  ];
}

function discover_auto_endpoints($docRoot) {
  $paths = ['/'];
  $docRoot = trim((string)$docRoot);
  if ($docRoot === '' || !is_dir($docRoot)) return $paths;

  $candidates = [
    '/' => ['type' => 'file', 'path' => 'index.php'],
    '/api' => ['type' => 'dir', 'path' => 'api'],
    '/rest' => ['type' => 'dir', 'path' => 'rest'],
    '/graphql' => ['type' => 'file_or_dir', 'path' => 'graphql'],
    '/login' => ['type' => 'file_or_dir', 'path' => 'login'],
    '/health' => ['type' => 'file_or_dir', 'path' => 'health'],
    '/status' => ['type' => 'file_or_dir', 'path' => 'status']
  ];

  foreach ($candidates as $uri => $rule) {
    if ($uri === '/') continue;
    $full = rtrim($docRoot, '/\\') . DIRECTORY_SEPARATOR . $rule['path'];
    $ok = false;
    if ($rule['type'] === 'dir') $ok = is_dir($full);
    if ($rule['type'] === 'file') $ok = is_file($full);
    if ($rule['type'] === 'file_or_dir') $ok = is_dir($full) || is_file($full) || is_file($full . '.php');
    if ($ok) $paths[] = $uri;
  }

  $uniq = [];
  foreach ($paths as $p) $uniq[$p] = $p;
  return array_values($uniq);
}

/* =========================
   CHECKS
========================= */

function dns_ok($host) {
  $host = normalize_host($host);
  if ($host === '') return false;

  $ip = @gethostbyname($host);
  if ($ip && $ip !== $host && filter_var($ip, FILTER_VALIDATE_IP)) return true;

  if (function_exists('dns_get_record')) {
    $records = [];
    foreach ([DNS_A, DNS_AAAA, DNS_CNAME] as $type) {
      $rr = @dns_get_record($host, $type);
      if (is_array($rr) && !empty($rr)) foreach ($rr as $r) $records[] = $r;
    }
    if (empty($records)) {
      $rrAny = @dns_get_record($host);
      if (is_array($rrAny) && !empty($rrAny)) foreach ($rrAny as $r) $records[] = $r;
    }

    $cname = '';
    foreach ($records as $r) {
      if (isset($r['ip']) && filter_var($r['ip'], FILTER_VALIDATE_IP)) return true;
      if (isset($r['ipv6']) && filter_var($r['ipv6'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) return true;
      if ($cname === '' && isset($r['target']) && is_string($r['target']) && $r['target'] !== '') {
        $cname = rtrim(strtolower($r['target']), '.');
      }
    }
    if ($cname !== '') {
      $cnameIp = @gethostbyname($cname);
      if ($cnameIp && $cnameIp !== $cname && filter_var($cnameIp, FILTER_VALIDATE_IP)) return true;
    }
  }

  if (strtoupper((string)PHP_OS_FAMILY) === 'WINDOWS' && function_exists('shell_exec')) {
    $out = @shell_exec('nslookup ' . escapeshellarg($host) . ' 2>NUL');
    if (is_string($out) && $out !== '') {
      if (preg_match_all('/Address:\s*([0-9]{1,3}(?:\.[0-9]{1,3}){3})/i', $out, $m) && !empty($m[1])) {
        $picked = trim((string)end($m[1]));
        if (filter_var($picked, FILTER_VALIDATE_IP)) return true;
      }
    }
  }

  return false;
}

function tcp_ok($host) {
  $fp = @fsockopen((string)$host, 443, $e, $s, 3);
  if ($fp) {
    fclose($fp);
    return true;
  }
  return false;
}

function classify_latency($lat) {
  if (!is_numeric($lat)) return 'UNKNOWN';
  $v = (float)$lat;
  if ($v < 0.2) return 'FAST';
  if ($v <= 1.0) return 'NORMAL';
  return 'SLOW';
}

function detect_http_content_error($raw) {
  if (!is_string($raw) || $raw === '') return false;
  $needle = strtolower($raw);
  $signatures = [
    'fatal error',
    'database error',
    'maintenance',
    'application error'
  ];
  foreach ($signatures as $s) {
    if (str_contains($needle, $s)) return true;
  }
  return false;
}

function http_check($url, $connectTimeout, $totalTimeout) {
  $start = microtime(true);

  $result = [
    'ok' => false,
    'reachable' => false,
    'http' => 0,
    'latency' => null,
    'errno' => 0,
    'error' => '',
    'redirect_count' => 0,
    'effective_url' => '',
    'raw_len' => 0,
    'http_content_error' => false,
    'suspicious' => false
  ];

  if (!function_exists('curl_init')) {
    $result['error'] = 'curl extension not available';
    return $result;
  }

  $ch = curl_init((string)$url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER => false,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 4,
    CURLOPT_CONNECTTIMEOUT => (int)$connectTimeout,
    CURLOPT_TIMEOUT => (int)$totalTimeout,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,
    CURLOPT_HTTPHEADER => [
      'User-Agent: UPNM-NOC-Agent/13.0',
      'Accept: */*',
      'Connection: close'
    ]
  ]);

  $raw = curl_exec($ch);
  $result['http'] = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $result['errno'] = (int)curl_errno($ch);
  $result['error'] = (string)curl_error($ch);
  $result['redirect_count'] = (int)curl_getinfo($ch, CURLINFO_REDIRECT_COUNT);
  $result['effective_url'] = (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
  curl_close($ch);

  $result['latency'] = round(microtime(true) - $start, 3);
  $result['reachable'] = ($result['errno'] === 0 && $result['http'] > 0);
  $result['ok'] = ($result['reachable'] && $result['http'] >= 200 && $result['http'] < 400 && $result['http'] !== 320);

  $len = is_string($raw) ? strlen($raw) : 0;
  $result['raw_len'] = $len;
  $result['http_content_error'] = detect_http_content_error((string)$raw);
  $result['suspicious'] = ($len > 0 && $len < 100);

  return $result;
}

function ssl_check($host) {
  $ctx = stream_context_create([
    'ssl' => [
      'capture_peer_cert' => true,
      'verify_peer' => false,
      'verify_peer_name' => false
    ]
  ]);

  $c = @stream_socket_client('ssl://' . (string)$host . ':443', $e, $s, 5, STREAM_CLIENT_CONNECT, $ctx);
  if (!$c) {
    return ['days' => null, 'cn' => '', 'issuer' => '', 'ok' => false, 'severity' => 'CRITICAL'];
  }

  $p = @stream_context_get_params($c);
  @fclose($c);

  $cert = $p['options']['ssl']['peer_certificate'] ?? null;
  if (!$cert) {
    return ['days' => null, 'cn' => '', 'issuer' => '', 'ok' => false, 'severity' => 'CRITICAL'];
  }

  $d = @openssl_x509_parse($cert);
  $exp = (int)($d['validTo_time_t'] ?? 0);
  $days = ($exp > 0) ? (int)round(($exp - time()) / 86400) : null;

  $sev = 'CRITICAL';
  if (is_numeric($days)) {
    $dd = (int)$days;
    if ($dd > 30) $sev = 'OK';
    else if ($dd >= 0) $sev = 'WARN';
    else $sev = 'CRITICAL';
  }

  return [
    'days' => $days,
    'cn' => (string)($d['subject']['CN'] ?? ''),
    'issuer' => (string)($d['issuer']['CN'] ?? ''),
    'ok' => true,
    'severity' => $sev
  ];
}

/* =========================
   SYSTEM TELEMETRY
========================= */

function get_linux_cpu_load() {
  $raw = @file_get_contents('/proc/loadavg');
  if (!is_string($raw) || trim($raw) === '') return null;
  $parts = preg_split('/\s+/', trim($raw));
  return isset($parts[0]) && is_numeric($parts[0]) ? (float)$parts[0] : null;
}

function get_linux_mem() {
  $raw = @file_get_contents('/proc/meminfo');
  if (!is_string($raw) || trim($raw) === '') return [null, null, null];
  $kv = parse_key_value_lines($raw);
  $total = null;
  $available = null;
  if (isset($kv['memtotal:']) && preg_match('/(\d+)/', $kv['memtotal:'], $m1)) $total = (int)$m1[1] * 1024;
  if (isset($kv['memavailable:']) && preg_match('/(\d+)/', $kv['memavailable:'], $m2)) $available = (int)$m2[1] * 1024;
  if ($total !== null && $available !== null && $total > 0) {
    $used = $total - $available;
    $pct = round(($used / $total) * 100, 2);
    return [$total, $used, $pct];
  }
  return [null, null, null];
}

function get_linux_uptime() {
  $raw = @file_get_contents('/proc/uptime');
  if (!is_string($raw) || trim($raw) === '') return null;
  $parts = preg_split('/\s+/', trim($raw));
  return isset($parts[0]) && is_numeric($parts[0]) ? (int)floor((float)$parts[0]) : null;
}

function get_windows_cpu_load() {
  $out = run_cmd('wmic cpu get loadpercentage /value 2>NUL');
  if ($out === '') return null;
  if (preg_match('/LoadPercentage\s*=\s*(\d+)/i', $out, $m)) return (int)$m[1];
  return null;
}

function get_windows_mem() {
  $out = run_cmd('wmic OS get FreePhysicalMemory,TotalVisibleMemorySize /value 2>NUL');
  if ($out === '') return [null, null, null];
  $freeKb = null;
  $totalKb = null;
  if (preg_match('/FreePhysicalMemory\s*=\s*(\d+)/i', $out, $m1)) $freeKb = (int)$m1[1];
  if (preg_match('/TotalVisibleMemorySize\s*=\s*(\d+)/i', $out, $m2)) $totalKb = (int)$m2[1];
  if ($freeKb !== null && $totalKb !== null && $totalKb > 0) {
    $total = $totalKb * 1024;
    $used = ($totalKb - $freeKb) * 1024;
    $pct = round(($used / $total) * 100, 2);
    return [$total, $used, $pct];
  }
  return [null, null, null];
}

function pick_disk_path() {
  $candidates = [
    (string)($_SERVER['DOCUMENT_ROOT'] ?? ''),
    __DIR__,
    'D:/WWW',
    'C:/',
    '/'
  ];
  foreach ($candidates as $p) {
    if ($p !== '' && @is_dir($p)) return $p;
  }
  return __DIR__;
}

function system_stats($osFamily, $webServer) {
  $diskPath = pick_disk_path();
  $diskTotal = @disk_total_space($diskPath);
  $diskFree = @disk_free_space($diskPath);
  $diskTotalNum = is_numeric($diskTotal) ? (float)$diskTotal : 0.0;
  $diskFreeNum = is_numeric($diskFree) ? (float)$diskFree : 0.0;
  $diskUsedPct = ($diskTotalNum > 0) ? round((($diskTotalNum - $diskFreeNum) / $diskTotalNum) * 100, 2) : null;

  $cpuLoad = null;
  $ramTotal = null;
  $ramUsed = null;
  $ramPercent = null;
  $uptimeSeconds = null;

  if ($osFamily === 'Linux') {
    $cpuLoad = get_linux_cpu_load();
    list($ramTotal, $ramUsed, $ramPercent) = get_linux_mem();
    $uptimeSeconds = get_linux_uptime();
  } else if ($osFamily === 'Windows') {
    $cpuLoad = get_windows_cpu_load();
    list($ramTotal, $ramUsed, $ramPercent) = get_windows_mem();
  }

  return [
    'hostname' => gethostname(),
    'php' => PHP_VERSION,
    'os' => PHP_OS,
    'os_family' => $osFamily,
    'web_server' => $webServer,
    'time' => date('Y-m-d H:i:s'),
    'disk_total' => ($diskTotalNum > 0 ? $diskTotalNum : null),
    'disk_free' => ($diskTotalNum > 0 ? $diskFreeNum : null),
    'disk_used_percent' => $diskUsedPct,
    'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 1),
    'cpu_load' => $cpuLoad,
    'ram_total' => $ramTotal,
    'ram_used' => $ramUsed,
    'ram_percent' => $ramPercent,
    'uptime_seconds' => $uptimeSeconds,
  ];
}

/* =========================
   DOCKER / SERVICES / PROCESS / NETWORK
========================= */

function discover_containers() {
  $rows = [];
  if (!command_exists('docker')) return $rows;
  $out = run_cmd('docker ps --format "{{.Names}}" 2>NUL');
  if ($out === '') return $rows;
  $lines = preg_split('/\r\n|\r|\n/', $out);
  foreach ($lines as $name) {
    $name = trim((string)$name);
    if ($name === '') continue;
    $rows[] = ['name' => $name, 'status' => 'running'];
  }
  return $rows;
}

function discover_docker_networks() {
  $rows = [];
  if (!command_exists('docker')) return $rows;
  $out = run_cmd('docker network ls --format "{{.Name}}" 2>NUL');
  if ($out === '') return $rows;
  $lines = preg_split('/\r\n|\r|\n/', $out);
  foreach ($lines as $n) {
    $n = trim((string)$n);
    if ($n === '') continue;
    $rows[] = $n;
  }
  return $rows;
}

function discover_services($osFamily) {
  $services = [
    'nginx' => 'unknown',
    'apache2' => 'unknown',
    'docker' => 'unknown',
    'mysql' => 'unknown',
    'redis' => 'unknown',
  ];

  if ($osFamily === 'Linux') {
    if (!command_exists('systemctl')) return $services;
    foreach ($services as $svc => $_) {
      $state = run_cmd('systemctl is-active ' . escapeshellarg($svc) . ' 2>/dev/null');
      if ($state !== '') $services[$svc] = trim($state);
    }
  }

  if ($osFamily === 'Windows') {
    $map = [
      'nginx' => 'nginx',
      'apache2' => 'Apache2.4',
      'docker' => 'docker',
      'mysql' => 'mysql',
      'redis' => 'redis'
    ];
    foreach ($map as $k => $winSvc) {
      $q = run_cmd('sc query ' . escapeshellarg($winSvc) . ' 2>NUL');
      if ($q === '') continue;
      if (stripos($q, 'RUNNING') !== false) $services[$k] = 'active';
      else if (stripos($q, 'STOPPED') !== false) $services[$k] = 'inactive';
      else $services[$k] = 'unknown';
    }
  }

  return $services;
}

function discover_process_counts($osFamily) {
  $targets = ['php-fpm', 'nginx', 'apache', 'gunicorn', 'node', 'java'];
  $counts = [];
  foreach ($targets as $t) $counts[$t] = 0;

  if ($osFamily === 'Linux') {
    $ps = run_cmd('ps aux 2>/dev/null');
    if ($ps !== '') {
      $lines = preg_split('/\r\n|\r|\n/', $ps);
      foreach ($lines as $ln) {
        $low = strtolower((string)$ln);
        foreach ($targets as $t) {
          if (str_contains($low, $t)) $counts[$t]++;
        }
      }
    }
  } else if ($osFamily === 'Windows') {
    $tl = run_cmd('tasklist 2>NUL');
    if ($tl !== '') {
      $lines = preg_split('/\r\n|\r|\n/', $tl);
      foreach ($lines as $ln) {
        $low = strtolower((string)$ln);
        if (str_contains($low, 'php-cgi') || str_contains($low, 'php.exe')) $counts['php-fpm']++;
        if (str_contains($low, 'nginx')) $counts['nginx']++;
        if (str_contains($low, 'httpd') || str_contains($low, 'apache')) $counts['apache']++;
        if (str_contains($low, 'gunicorn')) $counts['gunicorn']++;
        if (str_contains($low, 'node')) $counts['node']++;
        if (str_contains($low, 'java')) $counts['java']++;
      }
    }
  }

  return $counts;
}

function discover_open_ports($osFamily) {
  $watch = [80, 443, 3000, 5000, 8000, 8080, 9000];
  $found = [];

  if ($osFamily === 'Linux') {
    $raw = run_cmd('ss -tulnp 2>/dev/null');
    $lines = preg_split('/\r\n|\r|\n/', $raw);
    foreach ($lines as $ln) {
      foreach ($watch as $p) {
        if (preg_match('/:' . preg_quote((string)$p, '/') . '\b/', (string)$ln)) {
          $found[(string)$p] = ['port' => $p, 'detected' => true, 'raw' => trim((string)$ln)];
        }
      }
    }
  } else if ($osFamily === 'Windows') {
    $raw = run_cmd('netstat -ano 2>NUL');
    $lines = preg_split('/\r\n|\r|\n/', $raw);
    foreach ($lines as $ln) {
      foreach ($watch as $p) {
        if (preg_match('/:' . preg_quote((string)$p, '/') . '\s+/i', (string)$ln)) {
          $found[(string)$p] = ['port' => $p, 'detected' => true, 'raw' => trim((string)$ln)];
        }
      }
    }
  }

  $out = [];
  foreach ($watch as $p) {
    if (isset($found[(string)$p])) $out[] = $found[(string)$p];
  }
  return $out;
}

function build_dependency_map($endpoints, $containers, $ports, $processes) {
  $maps = [];

  $portNums = [];
  foreach ($ports as $p) {
    if (is_array($p) && isset($p['port'])) $portNums[] = (int)$p['port'];
  }
  if (empty($portNums)) $portNums = [443];

  $containerNames = [];
  foreach ($containers as $c) {
    if (is_array($c) && !empty($c['name'])) $containerNames[] = (string)$c['name'];
  }

  foreach ($endpoints as $ep) {
    if (!is_array($ep)) continue;
    $domain = (string)($ep['host'] ?? '');
    if ($domain === '') continue;

    $firstLabel = explode('.', $domain)[0] ?? '';
    $containerPick = '';
    foreach ($containerNames as $cn) {
      if ($firstLabel !== '' && str_contains(strtolower($cn), strtolower($firstLabel))) {
        $containerPick = $cn;
        break;
      }
    }

    $processPick = '';
    foreach ($processes as $name => $cnt) {
      if ((int)$cnt > 0) {
        $processPick = (string)$name;
        break;
      }
    }

    $maps[] = [
      'domain' => $domain,
      'container' => $containerPick,
      'port' => (int)($portNums[0] ?? 443),
      'process' => $processPick,
    ];
  }

  return $maps;
}

/* =========================
   RUN DISCOVERY + CHECKS
========================= */

$osFamily = detect_os_family();
$webServer = detect_web_server();

$disc = discover_vhosts($osFamily, $DEFAULT_APACHE_VHOST, $MAX_DISCOVER_FILES, $MAX_VHOSTS);
$vhosts = $disc['vhosts'];

$endpointResults = [];
foreach ($vhosts as $v) {
  $host = normalize_host((string)($v['domain'] ?? ''));
  if (!valid_host($host) || is_reserved_or_dummy_host($host)) continue;

  $docRoot = (string)($v['document_root'] ?? '');
  $autoEndpoints = discover_auto_endpoints($docRoot);

  $url = 'https://' . $host . '/';
  $dns = dns_ok($host);
  $tcp = false;
  $http = ['ok' => false, 'reachable' => false, 'http' => 0, 'latency' => null, 'errno' => 0, 'error' => '', 'redirect_count' => 0, 'effective_url' => '', 'raw_len' => 0, 'http_content_error' => false, 'suspicious' => false];
  $ssl = ['days' => null, 'cn' => '', 'issuer' => '', 'ok' => false, 'severity' => 'CRITICAL'];

  if ($dns) {
    $tcp = tcp_ok($host);
    if ($tcp) {
      $http = http_check($url, $HTTP_CONNECT_TIMEOUT, $HTTP_TOTAL_TIMEOUT);
      $ssl = ssl_check($host);
    }
  }

  $httpOk = (bool)($http['ok'] ?? false);
  $reachable = (bool)($http['reachable'] ?? false);
  $httpCode = (int)($http['http'] ?? 0);
  $latency = is_numeric($http['latency'] ?? null) ? (float)$http['latency'] : null;

  $endpointResults[] = [
    'name' => strtoupper($host),
    'host' => $host,
    'url' => $url,
    'type' => 'WEB',
    'tags' => 'apache-vhost',
    'owner' => 'BTMK',
    'notes' => 'Auto-detected from vhost',

    'online' => ($dns && $tcp && $reachable),
    'reachable' => ($dns && $tcp && $reachable),

    'dns_ok' => $dns,
    'tcp_ok' => $tcp,
    'http_ok' => $httpOk,
    'http_code' => $httpCode,
    'latency' => $latency,
    'latency_class' => classify_latency($latency),

    'ssl_days_left' => $ssl['days'],
    'ssl_cn' => $ssl['cn'] ?? '',
    'ssl_issuer' => $ssl['issuer'] ?? '',
    'ssl_severity' => $ssl['severity'] ?? 'CRITICAL',

    'http_content_error' => (bool)($http['http_content_error'] ?? false),
    'suspicious' => (bool)($http['suspicious'] ?? false),
    'page_size' => (int)($http['raw_len'] ?? 0),

    'auto_endpoints' => $autoEndpoints,

    'debug' => ($http['reachable'] ?? false)
      ? ('http=' . $httpCode . ' redir=' . (int)($http['redirect_count'] ?? 0) . ' eff=' . (string)($http['effective_url'] ?? ''))
      : ('curl#' . (string)($http['errno'] ?? 0) . ' ' . (string)($http['error'] ?? '')),
  ];
}

$system = system_stats($osFamily, $webServer);

$needsContainers = in_array($mode, ['full', 'containers'], true);
$needsServices = in_array($mode, ['full', 'services'], true);
$needsProcess = in_array($mode, ['full', 'process'], true);
$needsNetwork = in_array($mode, ['full', 'network'], true);

$containers = $needsContainers ? discover_containers() : [];
$dockerNetworks = ($needsContainers || $needsNetwork || $mode === 'full') ? discover_docker_networks() : [];
$services = $needsServices ? discover_services($osFamily) : [];
$process = $needsProcess ? discover_process_counts($osFamily) : [];
$network = $needsNetwork ? discover_open_ports($osFamily) : [];

if ($mode === 'full') {
  // full mode includes everything with sane defaults
  if (empty($services)) $services = discover_services($osFamily);
  if (empty($process)) $process = discover_process_counts($osFamily);
  if (empty($network)) $network = discover_open_ports($osFamily);
}

$dependencyMap = ($mode === 'full') ? build_dependency_map($endpointResults, $containers, $network, $process) : [];

$agentRuntime = round(microtime(true) - $agentStart, 4);

$agentInfo = [
  'name' => gethostname(),
  'php' => PHP_VERSION,
  'os' => PHP_OS,
  'os_family' => $osFamily,
  'web_server' => $webServer,
  'vhost_count' => count($endpointResults),
  'config_files' => $disc['config_files'],
  'agent_runtime' => $agentRuntime,
  'elapsed_sec' => $agentRuntime,
];

/* =========================
   OUTPUT MODES
========================= */

if ($mode === 'health') {
  $online = 0;
  $total = count($endpointResults);
  foreach ($endpointResults as $r) {
    if (!empty($r['online'])) $online++;
  }

  echo safe_json([
    'ok' => true,
    'mode' => 'health',
    'agent_version' => $AGENT_VERSION,
    'time' => date('Y-m-d H:i:s'),
    'total' => $total,
    'online' => $online,
    'offline' => $total - $online,
    'agent_runtime' => $agentRuntime,
  ]);
  exit;
}

if ($mode === 'system') {
  echo safe_json([
    'ok' => true,
    'mode' => 'system',
    'agent_version' => $AGENT_VERSION,
    'agent' => $agentInfo,
    'system' => $system,
  ]);
  exit;
}

if ($mode === 'endpoints') {
  echo safe_json([
    'ok' => true,
    'mode' => 'endpoints',
    'agent_version' => $AGENT_VERSION,
    'agent' => $agentInfo,
    'endpoints' => $endpointResults,
  ]);
  exit;
}

if ($mode === 'containers') {
  echo safe_json([
    'ok' => true,
    'mode' => 'containers',
    'agent_version' => $AGENT_VERSION,
    'agent' => $agentInfo,
    'containers' => $containers,
    'docker_networks' => $dockerNetworks,
  ]);
  exit;
}

if ($mode === 'services') {
  echo safe_json([
    'ok' => true,
    'mode' => 'services',
    'agent_version' => $AGENT_VERSION,
    'agent' => $agentInfo,
    'services' => $services,
  ]);
  exit;
}

if ($mode === 'process') {
  echo safe_json([
    'ok' => true,
    'mode' => 'process',
    'agent_version' => $AGENT_VERSION,
    'agent' => $agentInfo,
    'process' => $process,
  ]);
  exit;
}

if ($mode === 'network') {
  echo safe_json([
    'ok' => true,
    'mode' => 'network',
    'agent_version' => $AGENT_VERSION,
    'agent' => $agentInfo,
    'network' => $network,
    'docker_networks' => $dockerNetworks,
  ]);
  exit;
}

// default: full

echo safe_json([
  'ok' => true,
  'mode' => 'full',
  'agent_version' => $AGENT_VERSION,

  'agent' => $agentInfo,
  'system' => $system,

  'containers' => $containers,
  'services' => $services,
  'process' => $process,
  'docker_networks' => $dockerNetworks,
  'network' => $network,
  'dependency_map' => $dependencyMap,

  'endpoints' => $endpointResults,
]);
