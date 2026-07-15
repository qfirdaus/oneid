<?php
require_once dirname(__DIR__) . '/bootstrap/app.php';
require_once __DIR__ . '/secrets.php';
require_once __DIR__ . '/auth_security.php';

date_default_timezone_set((string) oneid_config('ONEID_TIMEZONE'));

$appUrl = trim((string) oneid_config('ONEID_APP_URL'));
if ($appUrl === '' || filter_var($appUrl, FILTER_VALIDATE_URL) === false) {
  $appUrl = 'https://oneid.local';
}

$appDebug = filter_var(oneid_config('ONEID_APP_DEBUG'), FILTER_VALIDATE_BOOLEAN);

define('APP_URL', rtrim($appUrl, '/'));
define('APP_DEBUG', $appDebug);

error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');

if (PHP_SAPI !== 'cli' && !headers_sent()) {
  header('X-Content-Type-Options: nosniff');
  header('X-Frame-Options: SAMEORIGIN');
  header('Referrer-Policy: strict-origin-when-cross-origin');
  header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
  header("Content-Security-Policy-Report-Only: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' data: https://fonts.gstatic.com; img-src 'self' data: https:; connect-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self'");
}

define('DB_DSN', oneid_secret('ONEID_DB_DSN'));
define('DB_USERNAME', oneid_secret('ONEID_DB_USERNAME'));
define('DB_PASSWORD', oneid_secret('ONEID_DB_PASSWORD'));
define('DB_CHARACSET', (string) oneid_config('ONEID_DB_CHARSET'));
//---Configure this for IDP

define('SSO_IDP_DOMAIN', rtrim((string) oneid_config('ONEID_SSO_IDP_URL'), '/') . '/');
define('SSO_SP_DASHBOARD', rtrim((string) oneid_config('ONEID_SSO_DASHBOARD_URL'), '/'));
//Token
//ENcryption Keys

//MYSQL ERROR Handling COdes
define('MYSQL_DUPLICATE_CODE', 1062);

// Scheduled sync (Windows Task Scheduler / cron)
define('SYNC_CRON_TRIGGERED_BY', (string) oneid_config('ONEID_SYNC_TRIGGERED_BY'));

//Logging
//Get user IP Address
define('LOG_IP', getenv('HTTP_CLIENT_IP')?:
getenv('HTTP_X_FORWARDED_FOR')?:
getenv('HTTP_X_FORWARDED')?:
getenv('HTTP_FORWARDED_FOR')?:
getenv('HTTP_FORWARDED')?:
getenv('REMOTE_ADDR'));

function handleException($exception) {
  error_log(sprintf(
    'Unhandled %s: %s in %s:%d',
    get_class($exception),
    $exception->getMessage(),
    $exception->getFile(),
    $exception->getLine()
  ));

  if (!headers_sent()) {
    http_response_code(500);
  }

  $requestUri = $_SERVER['REQUEST_URI'] ?? '';
  $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
  $expectsJson = strpos($accept, 'application/json') !== false
    || strpos($requestUri, '/api') !== false
    || strpos($requestUri, '/q_func') !== false;

  if ($expectsJson) {
    if (!headers_sent()) {
      header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['error' => 'Internal server error']);
    return;
  }

  echo 'Internal server error';
}

set_exception_handler( 'handleException' );

require_once __DIR__ . '/Database.php';
$operation=new Database();

?>
