<?php
declare(strict_types=1);

$baseUrl = rtrim((string) ($argv[1] ?? 'https://oneid.local'), '/');
$checks = [];

$request = static function (string $url): array {
    $handle = curl_init($url);
    if ($handle === false) {
        return [0, ''];
    }
    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml'],
    ]);
    $body = curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    curl_close($handle);
    return [$status, is_string($body) ? $body : ''];
};

[$loginStatus, $login] = $request($baseUrl . '/');
$checks['login responds successfully'] = $loginStatus === 200;
$checks['runtime login renders display settings'] = str_contains($login, 'data-oneid-display-settings')
    && str_contains($login, 'oneid-display-settings-panel');
$checks['runtime login exposes five bounded scales'] = substr_count($login, 'data-oneid-font-scale=') === 5
    && str_contains($login, 'data-oneid-font-scale="100"')
    && str_contains($login, 'data-oneid-font-scale="130"')
    && !str_contains($login, 'data-oneid-font-scale="140"');
$checks['runtime login uses current versioned assets'] = str_contains($login, 'oneid-display-settings.css?v=20260915-4')
    && str_contains($login, 'oneid-display-settings.js?v=20260915-4');
$checks['runtime login permits browser zoom'] = !str_contains($login, 'user-scalable=no')
    && !str_contains($login, 'maximum-scale=1.0');

foreach (['/dist/css/oneid-accessibility-baseline.css', '/dist/css/oneid-display-settings.css', '/dist/js/oneid-display-settings.js'] as $asset) {
    [$status, $body] = $request($baseUrl . $asset);
    $checks['asset is served: ' . $asset] = $status === 200 && trim($body) !== '';
}

[$missingStatus, $missing] = $request($baseUrl . '/errors/404.html');
$checks['404 presentation asset is reachable'] = $missingStatus === 200
    && str_contains($missing, 'data-oneid-display-settings');

$failed = 0;
foreach ($checks as $label => $passed) {
    fwrite(STDOUT, ($passed ? 'PASS' : 'FAIL') . ': ' . $label . PHP_EOL);
    if (!$passed) {
        $failed++;
    }
}
fwrite(STDOUT, sprintf('RESULT checks=%d failed=%d base_url=%s%s', count($checks), $failed, $baseUrl, PHP_EOL));
exit($failed === 0 ? 0 : 1);
