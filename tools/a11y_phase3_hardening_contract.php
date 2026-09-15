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
$js = $read('public/dist/js/oneid-display-settings.js');
$css = $read('public/dist/css/oneid-display-settings.css');

$checks['renderer contains one stable panel id'] = substr_count($renderer, 'id="oneid-display-settings-panel"') === 1;
$checks['renderer contains one initially selected font level'] = substr_count($renderer, 'aria-pressed="true"') === 1
    && substr_count($renderer, 'aria-pressed="false"') === 4;
$checks['renderer has no inline event handlers'] = preg_match('/\son[a-z]+\s*=/i', $renderer) !== 1;
$checks['renderer escapes every translated value'] = !str_contains($renderer, 'oneid_translate(')
    || str_contains($renderer, '$h = static fn');
$checks['presentation script performs no network request'] = !str_contains($js, 'fetch(')
    && !str_contains($js, 'XMLHttpRequest') && !str_contains($js, 'sendBeacon');
$checks['presentation script does not read identity or cookies'] = !str_contains($js, 'document.cookie')
    && !str_contains($js, 'sessionStorage') && !str_contains($js, 'user_id');
$checks['storage values pass through normalization'] = str_contains($js, 'return normalize(JSON.parse(')
    && str_contains($js, 'JSON.stringify(normalize(state))');
$checks['default remains 100 and maximum remains 130'] = str_contains($js, "fontScale: '100'")
    && str_contains($js, "allowedScales = ['100', '108', '115', '123', '130']")
    && !str_contains($js, "'140'");
$checks['panel state uses properties rather than HTML injection'] = !str_contains($js, 'innerHTML')
    && !str_contains($js, 'insertAdjacentHTML') && str_contains($js, 'textContent');
$checks['panel responds to Escape outside click and resize'] = str_contains($js, "event.key === 'Escape'")
    && str_contains($js, "document.addEventListener('click'")
    && str_contains($js, "window.addEventListener('resize'");
$checks['dashboard placement is bounded on both axes'] = str_contains($js, 'window.innerWidth - panelRect.width - gap')
    && str_contains($js, 'window.innerHeight - panelRect.height - gap');
$checks['panel itself has bounded viewport dimensions'] = str_contains($css, 'max-height:calc(100vh - 90px)')
    && str_contains($css, 'width:min(390px,calc(100vw - 2rem))') && str_contains($css, 'overflow:auto');
$checks['legacy footer cannot cover preference controls'] = str_contains($css, '.oneid-display-settings__footer{')
    && str_contains($css, 'position:static') && str_contains($css, 'height:auto');
$checks['focus targets and reduced motion remain present'] = str_contains($css, ':focus-visible')
    && str_contains($css, '@media(prefers-reduced-motion:reduce)');
$checks['mobile font grid has an explicit fallback'] = str_contains($css, '@media(max-width:480px)')
    && str_contains($css, 'grid-template-columns:repeat(2,1fr)');

$adminFiles = ['admin/dashboard.php', 'admin/user_list.php', 'admin/report_preview.php', 'page/admin_step_up.php'];
foreach ($adminFiles as $file) {
    $checks[$file . ' remains isolated'] = !str_contains($read($file), 'oneid-display-settings.');
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
