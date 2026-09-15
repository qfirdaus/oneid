<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$read = static function (string $path) use ($root): string {
    $content = file_get_contents($root . '/' . $path);
    if ($content === false) {
        throw new RuntimeException('Unable to read ' . $path);
    }
    return $content;
};
$checks = [];
$renderer = $read('lib/display_settings.php');
$css = $read('public/dist/css/oneid-display-settings.css');
$js = $read('public/dist/js/oneid-display-settings.js');

$checks['shared renderer exposes a named settings dialog'] = str_contains($renderer, 'role="dialog"')
    && str_contains($renderer, 'aria-labelledby="oneid-display-settings-title"');
$checks['trigger exposes expanded state and controlled panel'] = str_contains($renderer, 'aria-expanded="false"')
    && str_contains($renderer, 'aria-controls="oneid-display-settings-panel"');
$checks['compact gear trigger retains an accessible label'] = str_contains($renderer, '<svg aria-hidden="true"')
    && str_contains($renderer, 'aria-label="<?=$h(\'display_settings.open\')?>"')
    && !str_contains($renderer, '<span><?=$h(\'display_settings.open\')?></span>');
$checks['font choices expose pressed state'] = substr_count($renderer, 'data-oneid-font-scale=') === 5
    && str_contains($renderer, 'aria-pressed="true"');
$checks['three optional presentation preferences exist'] = str_contains($renderer, 'data-oneid-setting="highContrast"')
    && str_contains($renderer, 'data-oneid-setting="reduceMotion"')
    && str_contains($renderer, 'data-oneid-setting="underlineLinks"');
$checks['status announcements are polite'] = str_contains($renderer, 'role="status" aria-live="polite"');

$checks['font scale allowlist is bounded'] = str_contains($js, "allowedScales = ['100', '108', '115', '123', '130']");
$checks['storage key is versioned'] = str_contains($js, 'oneid.display-settings.v3')
    && str_contains($js, "legacyStorageKeys = ['oneid.display-settings.v1', 'oneid.display-settings.v2']");
$checks['invalid or unavailable storage falls back safely'] = substr_count($js, 'catch (error)') >= 3
    && str_contains($js, 'normalize(defaults)');
$checks['preferences only modify root presentation attributes'] = str_contains($js, 'data-oneid-font-scale')
    && str_contains($js, 'data-oneid-contrast')
    && str_contains($js, 'data-oneid-motion')
    && str_contains($js, 'data-oneid-links');
$checks['normalized 100 percent is applied explicitly'] = str_contains(
    $js,
    "root.setAttribute('data-oneid-font-scale', state.fontScale)"
) && !str_contains($js, "state.fontScale === '100'");
$checks['dialog supports Escape and returns focus'] = str_contains($js, "event.key === 'Escape'")
    && str_contains($js, 'shut(true)');
$checks['gear mounts beside a supported language selector'] = str_contains(
    $js,
    ".login-locale-switcher, .profile-locale-switcher"
) && str_contains($js, "localeSwitcher.appendChild(widget)")
    && str_contains($css, '.oneid-display-settings.is-locale-mounted');
$checks['dashboard panel prefers the right side and remains viewport bounded'] = str_contains(
    $js,
    "localeSwitcher.classList.contains('profile-locale-switcher')"
) && str_contains($js, 'triggerRect.right + gap + panelRect.width <= window.innerWidth - gap')
    && str_contains($js, 'window.innerHeight - panelRect.height - gap');
$checks['reset removes browser preference'] = str_contains($js, 'window.localStorage.removeItem(storageKey)');
$checks['legacy font scale preferences are retired safely'] = str_contains(
    $js,
    'legacyStorageKeys.forEach'
);

$checks['CSS supports all five normalized font levels'] = str_contains($css, 'data-oneid-font-scale="100"]{font-size:90%}')
    && str_contains($css, 'data-oneid-font-scale="108"]{font-size:97.2%}')
    && str_contains($css, 'data-oneid-font-scale="115"]{font-size:103.5%}')
    && str_contains($css, 'data-oneid-font-scale="123"]{font-size:110.7%}')
    && str_contains($css, 'data-oneid-font-scale="130"]{font-size:117%}');
$checks['CSS supports contrast motion and link states'] = str_contains($css, 'data-oneid-contrast="high"')
    && str_contains($css, 'data-oneid-motion="reduce"')
    && str_contains($css, 'data-oneid-links="underline"');
$checks['settings controls retain 44px targets and visible focus'] = str_contains($css, 'min-height:44px')
    && str_contains($css, ':focus-visible');
$checks['settings footer overrides the legacy absolute footer'] = str_contains(
    $css,
    '.oneid-display-settings__footer{'
) && str_contains($css, 'position:static') && str_contains($css, 'height:auto');
$checks['preference checkboxes lead left-aligned copy'] = str_contains(
    $css,
    '.oneid-display-settings__toggles input{'
) && str_contains($css, 'order:-1') && str_contains($css, 'text-align:left');
$checks['preference descriptions share the title edge'] = str_contains($css, 'margin-left:0!important')
    && str_contains($css, 'padding-left:0!important') && str_contains($css, 'text-indent:0');
$checks['text size help aligns with its legend'] = str_contains(
    $css,
    '.oneid-display-settings__group p{'
) && str_contains($css, 'margin:3px 0 11px!important');
$checks['settings heading and eyebrow share a left edge'] = str_contains(
    $css,
    '.oneid-display-settings__header>div{'
) && substr_count($css, 'text-align:left') >= 5
    && str_contains($css, '.oneid-display-settings__header h2{');

$userPages = ['index.php', 'page/dashboard.php', 'page/user_mfa_security.php',
    'page/user_mfa_challenge.php', 'resources/views/maintenance.php', 'public/errors/404.html'];
foreach ($userPages as $page) {
    $source = $read($page);
    $checks[$page . ' loads display settings assets'] = str_contains($source, 'oneid-display-settings.css?v=20260915-4')
        && str_contains($source, 'oneid-display-settings.js?v=20260915-4');
}

$adminSource = $read('admin/dashboard.php') . $read('admin/user_list.php')
    . $read('admin/report_preview.php') . $read('page/admin_step_up.php');
$checks['admin operational pages remain excluded'] = !str_contains($adminSource, 'oneid-display-settings.');
$checks['shared login excludes maintenance administrators'] = str_contains($read('index.php'), '$displaySettingsAvailable')
    && str_contains($read('index.php'), "!defined('ONEID_ADMIN_MAINTENANCE_LOGIN')");
$checks['MFA challenge excludes maintenance administrators'] = str_contains(
    $read('page/user_mfa_challenge.php'),
    'if(!$maintenanceMfa)'
);

$ms = $read('config/locales/ms.php');
$en = $read('config/locales/en.php');
foreach (['open', 'title', 'text_size', 'high_contrast', 'reduce_motion', 'underline_links', 'reset'] as $key) {
    $needle = "'display_settings.{$key}'";
    $checks['display setting locale parity: ' . $key] = str_contains($ms, $needle) && str_contains($en, $needle);
}

$failed = 0;
foreach ($checks as $label => $passed) {
    fwrite(STDOUT, ($passed ? 'PASS' : 'FAIL') . ': ' . $label . PHP_EOL);
    if (!$passed) {
        $failed++;
    }
}
fwrite(STDOUT, sprintf('RESULT checks=%d failed=%d%s', count($checks), $failed, PHP_EOL));
exit($failed === 0 ? 0 : 1);
