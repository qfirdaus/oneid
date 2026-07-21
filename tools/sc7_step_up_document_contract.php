<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$master = (string) file_get_contents($projectRoot . '/docs/ADMIN_STEP_UP_2FA_AUDIT_DAN_CADANGAN.md');
$audit = (string) file_get_contents($projectRoot . '/docs/AUDIT_DAN_PELAN_PENAMBAHBAIKAN_ADMIN_SSO_CONFIGURATION.md');
$handoff = (string) file_get_contents($projectRoot . '/docs/SC7_SC8_PENDING_CONFIGURATION_HANDOFF.md');
$activeSessions = (string) file_get_contents($projectRoot . '/docs/AS2_REVOKED_TOKEN_DAN_BAKI_ACTIVE_SESSION_AUDIT.md');
$readiness = (string) file_get_contents($projectRoot . '/docs/F7_0_READINESS_ADMIN_STEP_UP_2FA.md');
$changeRecord = (string) file_get_contents($projectRoot . '/docs/F7_0_CHANGE_RECORD_ONEID-F7-2FA-20260720-01.md');
$f72 = (string) file_get_contents($projectRoot . '/docs/F7_2_EMAIL_OTP_CHALLENGE_ENGINE.md');
$f73 = (string) file_get_contents($projectRoot . '/docs/F7_3_TOTP_FACTOR_LIFECYCLE.md');
$f74 = (string) file_get_contents($projectRoot . '/docs/F7_4_SERVER_SIDE_ENFORCEMENT.md');
$f75 = (string) file_get_contents($projectRoot . '/docs/F7_5_UI_BOOTSTRAP_DAN_RECOVERY.md');

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
        && str_contains($master, 'F7.1-F7.6 IMPLEMENTED')
        && str_contains($master, 'ACCEPTED — OPERATIONAL MONITORING')
        && str_contains($master, 'F7_0_READINESS_ADMIN_STEP_UP_2FA.md'),
    'UAT, monitoring, implementation gates and F7.0 readiness state are documented'
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
$report(
    str_contains($readiness, 'READINESS RECORDED — F7.1 APPLIED/ACCEPTED')
        && str_contains($readiness, '**Runtime/schema mutation:** NONE')
        && str_contains($readiness, '48 admin action')
        && str_contains($readiness, 'F7.0-G01')
        && str_contains($readiness, 'F7.0-G12'),
    'F7.0 readiness records zero mutation, endpoint baseline and gated NO-GO'
);
$report(
    str_contains($master, 'tiada faktor default global')
        && str_contains($readiness, 'sebagai default sendiri')
        && str_contains($readiness, 'Preference ini bukan grant keselamatan')
        && str_contains($readiness, 'akses admin ditolak secara fail-closed'),
    'factor default is an admin preference, never a global policy or security bypass'
);
$report(
    str_contains($changeRecord, 'ONEID-F7-2FA-20260720-01')
        && str_contains($changeRecord, 'zero runtime/schema mutation')
        && str_contains($changeRecord, '48 action dan 2 page')
        && str_contains($changeRecord, 'F7.1 APPLIED / VERIFIED / ACCEPTED')
        && str_contains($changeRecord, 'admin_2fa_enabled=0'),
    'F7.0 change record preserves live-apply evidence and feature-OFF acceptance'
);
$report(
    str_contains($changeRecord, 'Model pemilik tunggal')
        && str_contains($changeRecord, 'Tiada admin kedua terlibat')
        && str_contains($changeRecord, 'automated contract')
        && str_contains($changeRecord, 'tidak dilanjutkan kepada production'),
    'single-owner UAT decision has compensating controls and no production carry-over'
);
$report(
    str_contains($f72, 'IMPLEMENTED / VERIFIED / ACCEPTED — FEATURE OFF')
        && str_contains($f72, 'Argon2id hash')
        && str_contains($f72, 'SMTP failure merevoke challenge')
        && str_contains($f72, 'sender call atau row baharu'),
    'F7.2 evidence covers hash-only OTP, delivery gating and live feature-OFF safety'
);
$report(
    str_contains($f73, 'SCHEMA APPLIED / FEATURE OFF')
        && str_contains($f73, 'anti-replay')
        && str_contains($f73, '0 row')
        && str_contains($f73, 'belum disambungkan kepada route'),
    'F7.3 evidence covers TOTP lifecycle, anti-replay and zero live enrollment'
);
$report(
    str_contains($f74, 'IMPLEMENTED / VERIFIED / FEATURE OFF')
        && str_contains($f74, '48 action admin')
        && str_contains($f74, 'Purpose matrix lengkap')
        && str_contains($f74, '0 factor, preference'),
    'F7.4 evidence covers complete purpose mapping and dormant enforcement'
);
$report(
        str_contains($f75, 'PILOT ACTIVATED')
        && str_contains($f75, 'QR TOTP dirender secara lokal')
        && str_contains($f75, 'session ID dan CSRF token dirotasi')
        && str_contains($f75, 'FUNCTIONAL UAT HANDED OFF'),
    'F7.5 evidence covers local QR, rotation, bootstrap controls and F7.6 handoff'
);

printf("RESULT checks=%d failed=%d\n", $checks, $failed);
exit($failed === 0 ? 0 : 1);
