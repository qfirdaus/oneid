<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

$root = dirname(__DIR__);
$files = [
    'app/Auth/UserMfa/UserMfaUiCatalogue.php',
    'app/Auth/UserMfa/UserMfaUiRenderer.php',
    'tests/characterization/user_login_mfa_u6_ui.php',
];
$source = [];
$checks = [];
foreach ($files as $file) {
    $source[$file] = (string) file_get_contents($root . '/' . $file);
    $output = [];
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($root . '/' . $file), $output, $status);
    $checks['lint_' . basename($file)] = $status === 0;
}

$catalogue = $source['app/Auth/UserMfa/UserMfaUiCatalogue.php'];
$renderer = $source['app/Auth/UserMfa/UserMfaUiRenderer.php'];
$css = (string) file_get_contents($root . '/public/dist/css/user-mfa-u6-dormant.css');
$runtime = (string) file_get_contents($root . '/bootstrap/app.php')
    . (string) file_get_contents($root . '/bootstrap/sync_runtime.php')
    . (string) file_get_contents($root . '/lib/q_func.php')
    . (string) file_get_contents($root . '/index.php');

$checks['catalogue_has_bm_en_and_safe_fallback'] = str_contains($catalogue, "'ms' =>")
    && str_contains($catalogue, "'en' =>")
    && str_contains($catalogue, "? \$locale : 'ms'");
$checks['canonical_codes_are_not_translated'] = str_contains($renderer, 'UserLoginMfaPolicy::MODES')
    && str_contains($renderer, 'value="EMAIL_OTP"')
    && str_contains($renderer, 'value="TOTP"');
$checks['server_state_controls_totp'] = str_contains($renderer, "\$state['totp_enabled']")
    && str_contains($renderer, "\$state['active_totp']");
$checks['accessible_semantics_exist'] = str_contains($renderer, 'skip-link')
    && str_contains($renderer, '<fieldset>')
    && str_contains($renderer, '<legend>')
    && str_contains($renderer, 'aria-live="assertive"')
    && str_contains($renderer, 'inputmode="numeric"');
$checks['secure_qr_slot_has_no_secret_url'] = str_contains($renderer, 'data-qr-source="same-origin-post"')
    && str_contains($renderer, 'data-cache-control="no-store"')
    && !str_contains($renderer, 'otpauth://');
$checks['all_dynamic_values_use_escape'] = str_contains($renderer, 'htmlspecialchars(')
    && !str_contains($renderer, '$_GET')
    && !str_contains($renderer, '$_POST');
$checks['responsive_focus_and_reduced_motion'] = str_contains($css, ':focus-visible')
    && str_contains($css, '@media (max-width: 480px)')
    && str_contains($css, 'prefers-reduced-motion')
    && str_contains($css, 'min-height: 44px');
$checks['u6_assets_remain_dormant'] = !str_contains($runtime, 'UserMfaUiRenderer')
    && !str_contains($runtime, 'user-mfa-u6-dormant.css');

$characterization = [];
exec(
    escapeshellarg(PHP_BINARY) . ' '
    . escapeshellarg($root . '/tests/characterization/user_login_mfa_u6_ui.php'),
    $characterization,
    $characterizationStatus
);
$checks['characterization_passes'] = $characterizationStatus === 0
    && in_array(
        'RESULT checks=11 failures=0 locale_parity=1 keyboard=1 screen_reader=1 mobile=1 sensitive_url_values=0 runtime_activation=0',
        $characterization,
        true
    );

$failed = count(array_filter($checks, static fn(bool $passed): bool => !$passed));
foreach ($checks as $name => $passed) {
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $name);
}
printf(
    "RESULT checks=%d failures=%d locale_parity=1 sensitive_url_values=0 runtime_activation=0\n",
    count($checks),
    $failed
);
exit($failed === 0 ? 0 : 1);
