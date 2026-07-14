<?php

// Temporary R4 compatibility endpoint. Keep the legacy URL available while
// the implementation remains outside the public document root.
$projectRoot = dirname(__DIR__, 2);
require_once $projectRoot . '/lib/integration_security.php';

oneid_integration_audit('legacy_compat_route', [
    'endpoint' => 'sso_IDP_index',
    'method' => (string) ($_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN'),
]);

require $projectRoot . '/lib/sso_IDP_index.php';
