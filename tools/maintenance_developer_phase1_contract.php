<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

$root = dirname(__DIR__);
$gate = (string) file_get_contents($root . '/app/Maintenance/MaintenanceGate.php');
$login = (string) file_get_contents($root . '/lib/q_func.php');
$session = (string) file_get_contents($root . '/lib/session_security.php');
$requestSecurity = (string) file_get_contents($root . '/lib/request_security.php');
$dashboard = (string) file_get_contents($root . '/page/dashboard.php');
$policy = (string) file_get_contents(
    $root . '/docs/MD1_KEPERLUAN_DAN_POLISI_AKSES_DEVELOPER_MAINTENANCE.md'
);

$checks = [];
$checks['baseline maintenance bypass is restricted to verified u_type=1 admin'] =
    str_contains($gate, "login_user_type'] ?? '') === '1'")
    && str_contains($gate, 'oneid_maintenance_admin_verified_until');
$checks['baseline maintenance login rejects non-admin accounts'] =
    str_contains($login, 'MAINTENANCE_ADMIN_REQUIRED')
    && str_contains($login, '$results!==false&&$maintenanceAdminLogin');
$checks['baseline maintenance admin redirects to admin dashboard'] =
    str_contains($login, "\$array['redirect_uri'] = 'admin/dashboard'");
$checks['authenticated session preserves source u_type'] =
    str_contains($session, "\$_SESSION['login_user_type'] = \$user['u_type']");
$checks['admin boundary remains exact u_type=1'] =
    str_contains($requestSecurity, "login_user_type'] ?? '') === '1'");
$checks['user dashboard requires authenticated active token'] =
    str_contains($dashboard, 'oneid_require_authenticated_page();')
    && str_contains($dashboard, 'oneid_require_active_sso_page($operation);');
$checks['phase 1 locks separate capability and no new u_type'] =
    str_contains($policy, 'MD1-D01 — Capability berasingan')
    && str_contains($policy, 'MD1-D02 — Jangan perluaskan `u_type`');
$checks['phase 1 requires server-side revalidation and MFA'] =
    str_contains($policy, 'MD1-D05 — MFA wajib')
    && str_contains($policy, 'MD1-D09 — Revocation berkuat kuasa pada sesi aktif');
$checks['phase 1 defaults feature to dormant'] =
    str_contains($policy, 'ONEID_MAINTENANCE_DEVELOPER_ACCESS_ENABLED=false');
$checks['phase 1 contains authorization and decision contracts'] =
    str_contains($policy, '## 6. Matriks authorization')
    && str_contains($policy, '## 9. Kod keputusan canonical')
    && str_contains($policy, '## 11. Acceptance criteria keseluruhan');
$checks['phase 1 owner approval and phase 2 authorization are recorded'] =
    str_contains($policy, 'Status:** diluluskan owner pada 4 September 2026; Fasa 2 dibenarkan')
    && str_contains($policy, 'Keputusan owner:** diluluskan pada 4 September 2026')
    && str_contains($policy, '## 13. Gate kelulusan Fasa 1');

$failed = 0;
foreach ($checks as $label => $passed) {
    echo ($passed ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
    $failed += $passed ? 0 : 1;
}

echo sprintf('RESULT checks=%d failed=%d', count($checks), $failed) . PHP_EOL;
exit($failed === 0 ? 0 : 1);
