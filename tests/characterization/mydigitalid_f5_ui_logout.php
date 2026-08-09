<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use OneId\App\Auth\MyDigitalId\MyDigitalIdConfig;
use OneId\App\Auth\MyDigitalId\MyDigitalIdConfigurationException;
use OneId\App\Auth\MyDigitalId\MyDigitalIdLogoutUrl;

$checks = 0;
$failures = 0;
$check = static function (bool $passed, string $description) use (&$checks, &$failures): void {
    $checks++;
    $failures += $passed ? 0 : 1;
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $description);
};
$runtime = [
    'ONEID_ENVIRONMENT' => 'staging',
    'ONEID_MYDID_ENABLED' => 'true',
    'ONEID_MYDID_ISSUER' => 'https://sso.digital-id.my/realms/upnm',
    'ONEID_MYDID_CLIENT_ID' => 'upnm-generic',
    'ONEID_MYDID_REDIRECT_URI' => 'https://oneid-uat.upnm.edu.my/auth/mydigitalid/callback.php',
    'ONEID_MYDID_POST_LOGOUT_REDIRECT_URI' => 'https://oneid-uat.upnm.edu.my/',
    'ONEID_MYDID_SCOPE' => 'openid',
    'ONEID_MYDID_HTTP_TIMEOUT_SECONDS' => '12',
    'ONEID_MYDID_PKCE_METHOD' => 'S256',
];
$config = MyDigitalIdConfig::fromRuntime(
    static fn(string $key, mixed $fallback = null): mixed => $runtime[$key] ?? $fallback,
    static fn(string $key, bool $required = true): string => str_repeat('s', 32)
);
$idToken = 'header.payload.signature';
$url = (new MyDigitalIdLogoutUrl($config))->build($idToken);
parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
$check(
    str_starts_with(
        $url,
        'https://sso.digital-id.my/realms/upnm/protocol/openid-connect/logout?'
    )
        && ($query['id_token_hint'] ?? '') === $idToken
        && ($query['post_logout_redirect_uri'] ?? '') === 'https://oneid-uat.upnm.edu.my/',
    'logout URL pins provider, verified ID-token hint and registered post-logout URI'
);

foreach (['', 'not-a-jwt', "a.b.c\nheader", str_repeat('a', 8193) . '.b.c'] as $invalid) {
    try {
        (new MyDigitalIdLogoutUrl($config))->build($invalid);
        $blocked = false;
    } catch (MyDigitalIdConfigurationException) {
        $blocked = true;
    }
    $check($blocked, 'invalid logout token is rejected');
}

$disabledRuntime = $runtime;
$disabledRuntime['ONEID_MYDID_ENABLED'] = 'false';
$disabled = MyDigitalIdConfig::fromRuntime(
    static fn(string $key, mixed $fallback = null): mixed => $disabledRuntime[$key] ?? $fallback,
    static fn(string $key, bool $required = true): string => ''
);
try {
    (new MyDigitalIdLogoutUrl($disabled))->build($idToken);
    $disabledBlocked = false;
} catch (MyDigitalIdConfigurationException) {
    $disabledBlocked = true;
}
$check($disabledBlocked, 'provider logout remains unavailable while feature flag is false');

printf("RESULT checks=%d failures=%d network_calls=0\n", $checks, $failures);
exit($failures === 0 ? 0 : 1);
