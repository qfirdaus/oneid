<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$master = (string) file_get_contents($projectRoot . '/docs/ADMIN_STEP_UP_2FA_AUDIT_DAN_CADANGAN.md');
$audit = (string) file_get_contents($projectRoot . '/docs/AUDIT_DAN_PELAN_PENAMBAHBAIKAN_ADMIN_SSO_CONFIGURATION.md');
$handoff = (string) file_get_contents($projectRoot . '/docs/SC7_SC8_PENDING_CONFIGURATION_HANDOFF.md');
$activeSessions = (string) file_get_contents($projectRoot . '/docs/AS2_REVOKED_TOKEN_DAN_BAKI_ACTIVE_SESSION_AUDIT.md');

$checks = 0;
$failed = 0;
$report = static function (bool $passed, string $label) use (&$checks, &$failed): void {
    $checks++;
    if (!$passed) {
        $failed++;
    }
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $label);
};

$report(
    str_contains($master, 'sumber induk Fasa 7 Admin Step-Up 2FA')
        && str_contains($master, 'SC7-01 Admin Step-Up 2FA')
        && str_contains($master, 'SC7-06 Controlled Session Revocation'),
    'master audit declares authority and covers SC7-01 through SC7-06'
);
$report(
    str_contains($master, '`ADMIN_ACCESS`')
        && str_contains($master, '`SECURITY_CONFIGURATION_CHANGE`')
        && str_contains($master, '`ACTIVE_SESSION_REVOCATION`')
        && str_contains($master, 'tidak boleh saling menggantikan'),
    'purpose grants are isolated for access, configuration and session revocation'
);
$report(
    str_contains($master, 'OTP melalui e-mel')
        && str_contains($master, 'Microsoft Authenticator')
        && str_contains($master, 'secret TOTP yang dienkripsi')
        && str_contains($master, 'application encryption key'),
    'email OTP and encrypted TOTP factor lifecycle are covered'
);
$report(
    str_contains($master, 'Structured rejection audit')
        && str_contains($master, 'zero mutation')
        && str_contains($master, '`STEP_UP_PURPOSE_MISMATCH`'),
    'authorization and Step-Up rejection audit is fail closed and zero mutation'
);
$report(
    str_contains($master, 'controlled bootstrap')
        && str_contains($master, '`ADMIN_2FA_BOOTSTRAP_ENABLED`')
        && str_contains($master, 'Break-glass bukan toggle kekal'),
    'first activation and audited expiring break-glass are explicit'
);
$report(
    str_contains($master, 'fresh target preview')
        && str_contains($master, 'typed confirmation')
        && str_contains($master, 'self-lockout protection')
        && str_contains($master, 'result audit merekonsiliasi'),
    'controlled session revocation retains preview, confirmation and reconciliation'
);
$report(
    str_contains($master, 'Monitoring, UAT dan Controlled Rollout')
        && str_contains($master, 'Gate Sebelum Implementasi')
        && str_contains($master, 'IMPLEMENTATION ON HOLD'),
    'UAT, monitoring, implementation gates and hold state are documented'
);
$report(
    str_contains($audit, 'BROWSER UAT STAGING PASS')
        && str_contains($audit, 'ADMIN_STEP_UP_2FA_AUDIT_DAN_CADANGAN.md'),
    'configuration audit reflects SC3 completion and links the SC7 master audit'
);
$report(
    str_contains($handoff, 'dokumen induk tersebut mengatasi')
        && str_contains($handoff, 'Mandatory change reason, optimistic locking')
        && str_contains($activeSessions, 'ADMIN_STEP_UP_2FA_AUDIT_DAN_CADANGAN.md'),
    'handoff and Active Sessions audit defer to the consolidated master'
);

printf("RESULT checks=%d failed=%d\n", $checks, $failed);
exit($failed === 0 ? 0 : 1);
