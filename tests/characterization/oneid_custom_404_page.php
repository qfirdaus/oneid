<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$pagePath = $root . '/public/errors/404.html';
$page = is_file($pagePath) ? file_get_contents($pagePath) : false;
$checks = [
    'static_page' => is_string($page),
    'bilingual_message' => is_string($page)
        && str_contains($page, 'Halaman tidak ditemui')
        && str_contains($page, 'Page not found')
        && str_contains($page, 'The address you entered may be incorrect'),
    'language_switch' => is_string($page)
        && str_contains($page, 'href="#ms"')
        && str_contains($page, 'href="#en"')
        && str_contains($page, '<svg viewBox="0 0 24 24"')
        && str_contains($page, 'background: #078fca')
        && str_contains($page, '#en:target')
        && str_contains($page, '#en:target ~ #ms'),
    'oneid_brand' => is_string($page)
        && str_contains($page, '/img/logo_oneid.png')
        && str_contains($page, '/img/logo_upnm_30.png'),
    'safe_home_action' => is_string($page)
        && preg_match('/<a class="primary" href="\\/">/D', $page) === 1,
    'no_external_assets' => is_string($page)
        && preg_match('/(?:src|href)="https?:\\/\\//i', $page) !== 1,
    'not_indexable' => is_string($page)
        && str_contains($page, 'content="noindex, nofollow"'),
    'no_dynamic_input' => is_string($page)
        && !str_contains($page, '<script')
        && !str_contains($page, '<?'),
];

$failures = 0;
foreach ($checks as $name => $passed) {
    echo ($passed ? 'PASS ' : 'FAIL ') . $name . PHP_EOL;
    if (!$passed) {
        ++$failures;
    }
}

printf(
    "RESULT checks=%d failures=%d external_assets=0 dynamic_input=0\n",
    count($checks),
    $failures
);
exit($failures === 0 ? 0 : 1);
