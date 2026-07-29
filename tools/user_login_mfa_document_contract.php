<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

$root = dirname(__DIR__);
$audit = (string) file_get_contents($root . '/docs/USER_LOGIN_MFA_AUDIT_DAN_CADANGAN.md');
$plan = (string) file_get_contents($root . '/docs/USER_LOGIN_MFA_PELAN_IMPLEMENTASI_BERFASA.md');
$checks = [
    'audit_links_canonical_plan' => str_contains($audit, 'USER_LOGIN_MFA_PELAN_IMPLEMENTASI_BERFASA.md'),
    'global_mode_is_explicit' => str_contains($plan, 'OFF | ENROLLMENT | PILOT_ENFORCED | ENFORCED'),
    'email_is_mandatory_when_enabled' => str_contains($plan, 'mode selain `OFF` hanya sah apabila `email_enabled=true`'),
    'totp_has_global_kill_switch' => str_contains($plan, '`totp_enabled=false` menolak enrollment, selection dan verification TOTP'),
    'disable_preserves_factors' => str_contains($plan, 'faktor TOTP tersimpan kekal encrypted dan dormant'),
    'self_service_and_existing_admin_recovery' => str_contains($plan, 'Self-service Authenticator')
        && str_contains($plan, 'Tiada role `USER_MFA_RECOVERY`'),
    'password_only_scope_is_explicit' => str_contains($plan, 'user_login_mfa_scope = PASSWORD_ONLY'),
    'all_phases_are_present' => count(array_filter(
        range(0, 9),
        static fn(int $phase): bool => str_contains($plan, sprintf('### U%d —', $phase))
    )) === 10,
    'activation_remains_unauthorized' => str_contains($plan, '**Activation:** `NOT AUTHORIZED`')
        && str_contains($plan, 'user_login_mfa_mode=OFF'),
    'readiness_is_honest' => str_contains($plan, '### A. Keputusan reka bentuk — `COMPLETE`')
        && str_contains($plan, '### B. U0 implementation readiness — `PASS / CLOSED`')
        && str_contains($plan, '### C. Pilot/activation readiness — `PARTIAL / ACTIVATION NOT AUTHORIZED`'),
    'operational_baseline_is_complete' => str_contains($plan, '| Saiz pilot | 5–10 pengguna; sasaran 8 |')
        && str_contains($plan, '| Pilot observation | Minimum 7 hari |')
        && str_contains($plan, '| Post-change observation | Minimum 72 jam |')
        && str_contains($plan, '| Change window | 60 minit |')
        && str_contains($plan, '| Challenge metadata retention | 30 hari |')
        && str_contains($plan, '| Security/recovery audit retention | 365 hari |')
        && str_contains($plan, '| Global enforcement | `NOT AUTHORIZED` |'),
];

$failed = count(array_filter($checks, static fn(bool $passed): bool => !$passed));
foreach ($checks as $name => $passed) {
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $name);
}
printf("RESULT checks=%d failures=%d documentation_mutations=0\n", count($checks), $failed);
exit($failed === 0 ? 0 : 1);
