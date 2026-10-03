<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || !in_array($argv[1] ?? '', ['--up', '--rollback'], true)) {
    fwrite(STDERR, "Usage: php schema-plan.php --up|--rollback (prints SQL only)\n"); exit(2);
}
require_once dirname(__DIR__, 2) . '/app/Auth/MobileOidc/bootstrap.php';
use OneId\App\Auth\MobileOidc\LifecycleSchema;
echo "-- REVIEW ONLY: this command never connects to a database.\n";
if ($argv[1] === '--up') {
    echo "-- Apply Phase B schema first. Observer defaults OFF. Use an isolated, verified UAT database.\nDELIMITER $$\n";
    foreach (LifecycleSchema::statements() as $statement) echo $statement . "$$\n";
    echo "DELIMITER ;\n-- No activation SQL is executed or included.\n";
} else {
    echo "-- Disable hosted feature first. Retain tables, versions and subjects.\nUPDATE mobile_oidc_control SET observer_enabled=0 WHERE singleton_id=1;\n";
    foreach (array_reverse(LifecycleSchema::triggerNames()) as $name) echo 'DROP TRIGGER IF EXISTS ' . $name . ";\n";
}
