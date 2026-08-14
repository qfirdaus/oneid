<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$database = (string) file_get_contents($root . '/lib/Database.php');
$service = (string) file_get_contents($root . '/app/Admin/WebAppService.php');
$admin = (string) file_get_contents($root . '/admin/dashboard.php');
$migration = (string) file_get_contents($root . '/docs/migrations/20260814_app_production_readiness_up.sql');

$checks = [
    'migration defaults every copied app to not ready' => str_contains($migration, 'production_ready TINYINT(1) NOT NULL DEFAULT 0'),
    'group ACL filters production apps server-side' => str_contains($database, "B.production_ready=1 AND COALESCE(TRIM(B.production_domain),'')<>''") && str_contains($database, "public function specfic_user_get_sp_list_by_group"),
    'single ACL filters production apps server-side' => substr_count($database, "B.production_ready=1") >= 2,
    'site API code resolver filters production apps' => str_contains($database, "S.production_ready=1 AND COALESCE(TRIM(S.production_domain),'')<>''") && str_contains($database, 'resolve_site_api_code'),
    'production requests receive production URL' => substr_count($database, "THEN B.production_domain ELSE B.sp_domain") >= 2 && str_contains($database, "THEN S.production_domain ELSE S.sp_domain"),
    'service requires HTTPS production URL before ready' => str_contains($service, 'WA2_PRODUCTION_URL_REQUIRED') && str_contains($service, 'WA2_PRODUCTION_URL_NOT_ALLOWED'),
    'admin can explicitly manage readiness and URL' => substr_count($admin, 'production_ready') >= 4 && substr_count($admin, 'production_url') >= 4,
];

$passed = 0;
foreach ($checks as $label => $ok) {
    echo ($ok ? 'PASS' : 'FAIL') . ' ' . $label . "\n";
    $passed += $ok ? 1 : 0;
}
printf("RESULT %d/%d\n", $passed, count($checks));
exit($passed === count($checks) ? 0 : 1);
