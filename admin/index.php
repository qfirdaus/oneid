<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/session_security.php';
oneid_start_secure_session();

// Permit this directory entry point to inspect the maintenance policy and
// choose a safe destination. Other admin files retain their existing gates.
define('ONEID_ADMIN_MAINTENANCE_LOGIN', true);
require_once dirname(__DIR__) . '/lib/config.php';

$maintenancePolicy = \OneId\App\Maintenance\MaintenancePolicy::evaluate(
    $operation->get_maintenance_config() ?: []
);
$isAuthenticatedAdmin = ($_SESSION['login_status'] ?? '') === 'true'
    && (string) ($_SESSION['login_user_type'] ?? '') === '1';

if ($maintenancePolicy['active']) {
    $destination = $isAuthenticatedAdmin
        ? APP_URL . '/admin/dashboard'
        : APP_URL . '/admin/login.php';
} else {
    $destination = APP_URL . '/';
}

header('Cache-Control: no-store, no-cache, must-revalidate');
header('Location: ' . $destination, true, 302);
exit;
