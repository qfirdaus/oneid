<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$manifest = json_decode(
    (string) file_get_contents($root . '/deployment/mobile-oidc/ios-staging-client.json'),
    true,
    32,
    JSON_THROW_ON_ERROR
);
$appConfig = json_decode(
    (string) file_get_contents($root . '/docs/integration/OneID-UPNM-Mobile-iOS-Staging.config.json'),
    true,
    32,
    JSON_THROW_ON_ERROR
);
$installer = (string) file_get_contents($root . '/tools/mobile-oidc-hosted/register-ios-staging.php');
$guide = (string) file_get_contents($root . '/docs/integration/Serahan-Integrasi-OneID-iOS-Staging.md');

$checks = [
    'iOS staging client has its own stable client ID' => ($manifest['client_id'] ?? null) === 'upnm-mobile-ios-staging',
    'provider and application use the exact approved callback' => ($manifest['redirect_uris'] ?? null) === ['com.upnmmobile1.app://oneid/callback']
        && ($appConfig['redirect_uri'] ?? null) === 'com.upnmmobile1.app://oneid/callback',
    'iOS bundle and acceptance target are recorded' => ($appConfig['bundle_id'] ?? null) === 'com.upnmmobile1.app'
        && ($appConfig['minimum_ios_version'] ?? null) === '15.0'
        && ($appConfig['acceptance_device'] ?? null) === 'iPhone 15'
        && ($appConfig['acceptance_ios_version'] ?? null) === '18.1.1',
    'client is public Authorization Code with refresh and no secret' => ($manifest['grant_types'] ?? null) === ['authorization_code', 'refresh_token']
        && ($manifest['response_types'] ?? null) === ['code']
        && ($manifest['token_endpoint_auth_method'] ?? null) === 'none'
        && !array_key_exists('client_secret', $manifest),
    'mobile session scope and audience are constrained' => ($manifest['scope'] ?? null) === 'openid profile offline_access mobile:session'
        && ($manifest['audience'] ?? null) === ['oneid-mobile-session'],
    'installer is pinned to staging and preserves existing clients' => str_contains($installer, "realpath(\$root) !== '/var/www/oneid-uat'")
        && str_contains($installer, "(\$config['environment'] ?? '') !== 'staging'")
        && str_contains($installer, "\$config['clients'][\$id] = [\$uri]")
        && str_contains($installer, '.before-ios-'),
    'guide requires PKCE S256 and states production remains off' => str_contains($guide, 'PKCE S256')
        && str_contains($guide, 'Mobile production kekal OFF'),
];

$failed = 0;
foreach ($checks as $label => $passed) {
    echo ($passed ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
    if (!$passed) $failed++;
}
printf("RESULT checks=%d failed=%d\n", count($checks), $failed);
exit($failed === 0 ? 0 : 1);
