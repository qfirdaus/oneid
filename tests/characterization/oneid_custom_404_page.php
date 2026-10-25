<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$pages = [
    '404' => [$root . '/public/errors/404.html', 'Halaman ini tidak tersedia', 'This page is unavailable'],
    '429' => [$root . '/public/errors/429.html', 'Permintaan terlalu kerap', 'Too many requests'],
    '50x' => [$root . '/public/errors/50x.html', 'Perkhidmatan tidak tersedia buat sementara waktu', 'Service temporarily unavailable'],
];

$checks = [];
foreach ($pages as $name => [$path, $ms, $en]) {
    $page = is_file($path) ? file_get_contents($path) : false;
    $checks[$name . '_static_page'] = is_string($page);
    $checks[$name . '_bilingual_message'] = is_string($page)
        && str_contains($page, $ms)
        && str_contains($page, $en);
    $checks[$name . '_safe_and_generic'] = is_string($page)
        && str_contains($page, 'content="noindex,nofollow,noarchive"')
        && str_contains($page, 'href="/"')
        && !str_contains($page, '<?')
        && !str_contains($page, '<script')
        && !preg_match('/(?:stack trace|exception|\/var\/www|php-fpm|nginx)/i', $page);
    $checks[$name . '_no_visible_status_code'] = is_string($page)
        && !preg_match('/>\s*(?:400|401|403|404|405|408|413|414|429|500|502|503|504)\s*</', $page);
}

$css = @file_get_contents($root . '/public/errors/error.css');
$checks['shared_responsive_style'] = is_string($css)
    && str_contains($css, '@media(max-width:480px)')
    && str_contains($css, 'prefers-reduced-motion');

foreach (['staging', 'production'] as $environment) {
    $nginx = @file_get_contents($root . '/deployment/nginx/oneid-' . $environment . '.conf');
    $checks[$environment . '_mappings'] = is_string($nginx)
        && str_contains($nginx, 'error_page 400 401 403 404 405 408 413 414 /errors/404.html;')
        && str_contains($nginx, 'error_page 429 /errors/429.html;')
        && str_contains($nginx, 'error_page 500 502 503 504 /errors/50x.html;');
    $checks[$environment . '_api_preserved'] = is_string($nginx)
        && str_contains($nginx, 'fastcgi_intercept_errors off;');
}

$failures = 0;
foreach ($checks as $name => $passed) {
    echo ($passed ? 'PASS ' : 'FAIL ') . $name . PHP_EOL;
    if (!$passed) {
        ++$failures;
    }
}

printf("RESULT checks=%d failures=%d\n", count($checks), $failures);
exit($failures === 0 ? 0 : 1);
