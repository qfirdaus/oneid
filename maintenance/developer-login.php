<?php

declare(strict_types=1);

define('ONEID_DEVELOPER_MAINTENANCE_LOGIN', true);
require dirname(__DIR__) . '/lib/config.php';
$policy = \OneId\App\Maintenance\MaintenancePolicy::evaluate($operation->get_maintenance_config() ?: []);
$featureEnabled = oneid_maintenance_developer_access_enabled();
if (!$policy['active'] || !$featureEnabled) {
    header('Location: ' . APP_URL . '/', true, 302);
    exit;
}
require dirname(__DIR__) . '/index.php';
