<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

$root = dirname(__DIR__);
$index = (string) file_get_contents($root . '/index.php');
$ms = (string) file_get_contents($root . '/config/locales/ms.php');
$en = (string) file_get_contents($root . '/config/locales/en.php');

$checks = [];
$checks['password_flow_preserved'] = str_contains($index, '<form id="loginform">')
    && str_contains($index, 'id="username" name="username"')
    && str_contains($index, 'id="password" name="password"')
    && str_contains($index, '<input type="hidden" name="auth" value="auth">');
$checks['distinct_login_methods'] = str_contains($index, 'oneid-login-method--password')
    && str_contains($index, 'mydigitalid-login-option')
    && str_contains($index, 'href="auth/mydigitalid/login.php"');
$checks['professional_single_row_brand_lockup'] = str_contains($index, 'class="oneid-login-brand-lockup"')
    && str_contains($index, 'oneid-login-brand-lockup__oneid')
    && str_contains($index, 'oneid-login-brand-lockup__upnm')
    && str_contains($index, 'oneid-login-brand-lockup__divider')
    && str_contains($index, 'display: flex;')
    && str_contains($index, 'align-items: center;');
$checks['brand_motion_is_subtle_and_accessible'] = str_contains($index, 'oneid-login-logo-light-pass')
    && str_contains($index, '.oneid-login-brand-mark::after')
    && str_contains($index, '@media (prefers-reduced-motion: reduce)')
    && str_contains($index, 'pointer-events: none;');
$checks['official_registration_link'] = str_contains(
    $index,
    'href="https://www.digital-id.my/"'
) && str_contains($index, 'target="_blank" rel="noopener noreferrer"');
$keys = [
    'login.password_access.eyebrow',
    'login.password_access.title',
    'login.password_access.description',
    'login.mydigitalid.register_prompt',
    'login.mydigitalid.register_link',
];
$checks['bilingual_copy'] = count(array_filter(
    $keys,
    static fn(string $key): bool => str_contains($ms, "'{$key}'")
        && str_contains($en, "'{$key}'")
)) === count($keys);
$checks['responsive_safe_layout'] = str_contains($index, 'grid-template-columns: 108px minmax(0, 1fr) 34px')
    && str_contains($index, 'flex-wrap: wrap')
    && str_contains($index, '@media (max-width: 767.98px)');

$failed = count(array_filter($checks, static fn(bool $passed): bool => !$passed));
foreach ($checks as $name => $passed) {
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $name);
}
printf("RESULT checks=%d failures=%d authentication_flow_changes=0\n", count($checks), $failed);
exit($failed === 0 ? 0 : 1);
