<?php

declare(strict_types=1);

namespace OneId\App\Maintenance;

final class MaintenanceGate
{
    public static function enforce(object $operation): void
    {
        if (PHP_SAPI === 'cli' || defined('ONEID_MAINTENANCE_BYPASS')) return;
        self::applyRequestedLocale();
        $stored = method_exists($operation, 'get_maintenance_config') ? $operation->get_maintenance_config() : null;
        if (!is_array($stored)) return;
        $policy = MaintenancePolicy::evaluate($stored);
        if (!$policy['active']) return;
        $path=(string)(parse_url((string)($_SERVER['REQUEST_URI']??''),PHP_URL_PATH)??'');
        if ($path==='/api.php'||$path==='/api') return;
        $isAdmin = ($_SESSION['login_status'] ?? '') === 'true'
            && (string)($_SESSION['login_user_type'] ?? '') === '1'
            && (int)($_SESSION['oneid_maintenance_admin_verified_until'] ?? 0) >= time();
        $pendingAdminMfa = ($_SESSION['user_mfa_pending_admin_maintenance'] ?? false) === true;
        if ($isAdmin || $pendingAdminMfa || defined('ONEID_ADMIN_MAINTENANCE_LOGIN')) return;
        $maintenanceLoginPost = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
            && (string) ($_POST['maintenance_admin_login'] ?? '') === '1'
            && array_key_exists('auth', $_POST)
            && (str_ends_with($path, '/lib/q_func') || str_ends_with($path, '/lib/q_func.php'));
        if ($maintenanceLoginPost) return;
        self::respond($policy);
    }

    public static function respond(array $policy): never
    {
        if (!headers_sent()) {
            http_response_code(503);
            header('Cache-Control: no-store, no-cache, must-revalidate');
            header('X-OneID-Maintenance: active');
            if (is_int($policy['retry_after_seconds'] ?? null)) header('Retry-After: ' . $policy['retry_after_seconds']);
        }
        $accept=(string)($_SERVER['HTTP_ACCEPT']??'');$uri=(string)($_SERVER['REQUEST_URI']??'');
        if(str_contains($accept,'application/json')||str_contains($uri,'q_func')||str_contains($uri,'/api')){
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status'=>0,'code'=>'MAINTENANCE_ACTIVE','error'=>'OneID is under maintenance','maintenance'=>$policy]);exit;
        }
        require dirname(__DIR__,2).'/resources/views/maintenance.php';
        exit;
    }

    private static function applyRequestedLocale(): void
    {
        if (!isset($_GET['locale']) || !is_scalar($_GET['locale'])) return;
        $requested=(string)$_GET['locale'];
        if (function_exists('oneid_set_session_locale') && oneid_set_session_locale($requested)) {
            if (function_exists('oneid_set_guest_locale_cookie')) {
                oneid_set_guest_locale_cookie($requested);
            }
        }
    }
}
