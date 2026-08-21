<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$files = [
    'migration' => (string) file_get_contents($root . '/docs/migrations/20260731_user_login_mfa_temporary_exemption_up.sql'),
    'service' => (string) file_get_contents($root . '/app/Admin/UserMfaTemporaryExemptionService.php'),
    'reader' => (string) file_get_contents($root . '/app/Auth/UserMfa/PdoUserMfaPolicyReader.php'),
    'decision' => (string) file_get_contents($root . '/app/Auth/UserMfa/UserMfaPrimaryAuthDecision.php'),
    'guard' => (string) file_get_contents($root . '/lib/request_security.php'),
    'api' => (string) file_get_contents($root . '/lib/q_func.php'),
    'ui' => (string) file_get_contents($root . '/admin/dashboard.php'),
    'js' => (string) file_get_contents($root . '/public/assetsM/js/user-2fa-temporary-exemption.js'),
    'locale_en' => (string) file_get_contents($root . '/config/locales/en.php'),
    'locale_ms' => (string) file_get_contents($root . '/config/locales/ms.php'),
];

$checks = [];
$checks['temporary_only_72h'] = str_contains($files['migration'], 'INTERVAL 72 HOUR')
    && str_contains($files['service'], 'private const DURATIONS = [1, 4, 8, 24, 72]');
$checks['single_active_per_user'] = str_contains($files['migration'], 'uq_user_mfa_exemption_active')
    && str_contains($files['migration'], 'active_user_slot');
$checks['administrator_forbidden'] = str_contains($files['service'], "['u_type'] === 1")
    && str_contains($files['service'], 'USER_MFA_EXEMPTION_ADMIN_FORBIDDEN');
$checks['mandatory_approval_fields'] = str_contains($files['migration'], 'approved_by VARCHAR(20) NOT NULL')
    && str_contains($files['migration'], 'change_reason VARCHAR(500) NOT NULL')
    && str_contains($files['migration'], 'change_reference VARCHAR(100) NOT NULL')
    && str_contains($files['migration'], 'compensating_control VARCHAR(500) NOT NULL');
$checks['fail_closed_expiry'] = str_contains($files['reader'], "e.exemption_status='ACTIVE'")
    && str_contains($files['reader'], 'e.expires_at>NOW(6)')
    && str_contains($files['reader'], 'return false;');
$checks['pending_revoked_atomically'] = str_contains($files['service'], 'beginTransaction')
    && str_contains($files['service'], "transaction_status='REVOKED'")
    && str_contains($files['service'], 'otp_hash=NULL')
    && str_contains($files['service'], 'commit()');
$checks['login_decision_wired'] = str_contains($files['decision'], 'temporarilyExempt($userId)');
$checks['step_up_guarded'] = str_contains($files['guard'], "'admin_create_user_mfa_exemption'")
    && str_contains($files['guard'], "'admin_revoke_user_mfa_exemption'")
    && str_contains($files['guard'], "'SECURITY_CONFIGURATION_CHANGE'");
$checks['api_and_ui'] = str_contains($files['api'], 'UserMfaTemporaryExemptionService')
    && str_contains($files['ui'], 'user_2fa_exemption_panel')
    && str_contains($files['ui'], 'user_2fa_exemption_candidate_results')
    && str_contains($files['js'], "request('admin_search_user_mfa_exemption_candidates'")
    && str_contains($files['js'], "request('admin_search_user_mfa_exemptions'")
    && str_contains($files['js'], "request('admin_revoke_user_mfa_exemption'");
$checks['history_and_expiry_alert'] = str_contains($files['service'], 'ORDER BY')
    && str_contains($files['service'], 'expires_soon')
    && str_contains($files['js'], 'item.expires_soon');
$checks['realtime_candidate_search'] = str_contains($files['service'], 'LIMIT 10')
    && str_contains($files['js'], "addEventListener('input', scheduleCandidateSearch)")
    && str_contains($files['js'], 'candidateSearchSequence')
    && str_contains($files['ui'], 'max-height: 310px');
$checks['simplified_reason_selection'] = str_contains($files['ui'], 'id="user_2fa_exemption_reason"')
    && str_contains($files['ui'], 'value="PHONE_LOST"')
    && str_contains($files['ui'], 'value="OTHER"')
    && str_contains($files['ui'], 'id="user_2fa_exemption_other_wrap"')
    && !str_contains($files['ui'], 'id="user_2fa_exemption_confirmation"');
$checks['automatic_controlled_fields'] = str_contains($files['js'], 'generatedReference()')
    && str_contains($files['js'], 'compensating_control: text.control')
    && str_contains($files['js'], 'typed_confirmation: phrase()')
    && str_contains($files['js'], 'text.confirmTitle')
    && str_contains($files['js'], "reasonCode === 'OTHER'");
$checks['simplified_form_multilingual'] = str_contains($files['locale_en'], "'admin.configuration.user_2fa_exemption_reason_lost'")
    && str_contains($files['locale_ms'], "'admin.configuration.user_2fa_exemption_reason_lost'")
    && str_contains($files['locale_en'], "'admin.configuration.user_2fa_exemption_review'")
    && str_contains($files['locale_ms'], "'admin.configuration.user_2fa_exemption_review'");

$failed = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));
printf(
    "USER_MFA_TEMPORARY_EXEMPTION checks=%d passed=%d\n",
    count($checks),
    count($checks) - count($failed)
);
if ($failed !== []) {
    fwrite(STDERR, 'FAIL ' . implode(',', $failed) . PHP_EOL);
    exit(1);
}
echo "PASS TEMPORARY_FAIL_CLOSED_ADMIN_FORBIDDEN_STEP_UP_AUDITED\n";
