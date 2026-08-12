<?php

declare(strict_types=1);

define('ONEID_ADMIN_MAINTENANCE_LOGIN', true);
require dirname(__DIR__) . '/lib/config.php';
$maintenanceLoginPolicy = \OneId\App\Maintenance\MaintenancePolicy::evaluate($operation->get_maintenance_config() ?: []);
if (!$maintenanceLoginPolicy['active']) {
    header('Location: ' . APP_URL . '/', true, 302);
    exit;
}
require dirname(__DIR__) . '/index.php';
