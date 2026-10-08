<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$page = (string) file_get_contents($root . '/page/dashboard.php');
$en = require $root . '/config/locales/en.php';
$ms = require $root . '/config/locales/ms.php';

$checks = [
    'security context renders four concise account cards' => str_contains($page, 'id="user_security_context"')
        && substr_count($page, 'class="user-security-summary__card') === 4
        && str_contains($page, 'user_security_active_sessions')
        && str_contains($page, 'user_security_last_login')
        && str_contains($page, 'user_security_device_status'),
    'MFA status is read from policy and active factor state' => str_contains($page, '$userMfaPolicyReader->policy()->mode')
        && str_contains($page, "factor_type='TOTP' AND factor_status='ACTIVE'")
        && str_contains($page, '$userMfaSummaryMethod'),
    'session summary reuses the authenticated session response' => str_contains($page, 'updateUserSecuritySummary(Array.isArray(response) ? response : [])')
        && str_contains($page, 'session.current_token')
        && str_contains($page, 'session.token_issued_at'),
    'device warning uses evidence without claiming device recognition' => str_contains($page, "device === 'unknown device'")
        && str_contains($page, 'unknownCount > 0 || otherCount > 0')
        && str_contains($en['dashboard.security_summary.device_other_help'] ?? '', 'if you do not recognise them'),
    'security is an icon context beside recently used' => str_contains($page, 'class="user-app-category-tools"')
        && str_contains($page, 'id="user_security_context_trigger"')
        && str_contains($page, 'id="user_app_recent_trigger"')
        && str_contains($page, 'aria-controls="user_security_context"'),
    'security context uses one compact card column' => str_contains($page, '.user-security-context { position:absolute')
        && str_contains($page, '.user-security-summary__grid { display:grid; grid-template-columns:1fr; gap:7px;')
        && str_contains($page, 'grid-template-columns:minmax(0,1fr)')
        && str_contains($page, '.user-security-context { left:10px; right:10px; top:48px; width:auto; max-width:none; }'),
    'each security item stays within two compact visual lines' => str_contains($page, 'grid-template-rows:13px 24px')
        && str_contains($page, 'justify-items:start;')
        && str_contains($page, 'text-align:left;')
        && str_contains($page, '<b id="user_security_active_sessions">&mdash;</b><small id="user_security_sessions_help"')
        && str_contains($page, 'font-size:11px; line-height:1.25; text-align:left; white-space:nowrap;')
        && str_contains($page, 'font-size:8px; font-weight:400; line-height:1.25; text-align:left; text-overflow:ellipsis; white-space:nowrap;')
        && str_contains($page, ".attr('title', sessionsHelp)")
        && str_contains($page, ".attr('title', deviceHelp)"),
    'security and recently used contexts have matching width' => str_contains($page, '.user-security-context { position:absolute; z-index:21; top:46px; right:20px; width:460px;')
        && str_contains($page, '.user-app-recent { position:absolute; z-index:20; top:46px; right:20px; width:460px;'),
    'security context supports close outside click and Escape' => str_contains($page, 'data-security-context-close')
        && str_contains($page, "closest('#user_security_context, #user_security_context_trigger')")
        && str_contains($page, "event.key === 'Escape' && !\$('#user_security_context').prop('hidden')"),
    'security labels are available in English and Malay' => ($en['dashboard.security_summary.title'] ?? '') === 'Account Security'
        && ($ms['dashboard.security_summary.title'] ?? '') === 'Keselamatan Akaun'
        && ($en['dashboard.security_summary.mfa_active'] ?? '') === 'Active'
        && ($ms['dashboard.security_summary.mfa_active'] ?? '') === 'Aktif',
    'summary introduces no database mutation statement' => !preg_match('/user-security-summary[\s\S]{0,12000}\b(?:INSERT|UPDATE|DELETE)\b/i', $page),
];

$failed = 0;
foreach ($checks as $label => $passed) {
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $label);
    if (!$passed) $failed++;
}
printf("RESULT checks=%d failed=%d\n", count($checks), $failed);
exit($failed === 0 ? 0 : 1);
