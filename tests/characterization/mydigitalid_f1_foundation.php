<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use OneId\App\Auth\MyDigitalId\MyDigitalIdConfig;
use OneId\App\Auth\MyDigitalId\MyDigitalIdConfigurationException;
use OneId\App\Auth\MyDigitalId\MyDigitalIdProtocolClient;
use OneId\App\Auth\MyDigitalId\MyDigitalIdProviderMetadata;

$checks = 0;
$failures = 0;
$check = static function (bool $passed, string $description) use (&$checks, &$failures): void {
    $checks++;
    if (!$passed) {
        $failures++;
    }
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $description);
};

$valid = [
    'ONEID_MYDID_ENABLED' => 'false',
    'ONEID_MYDID_ISSUER' => 'https://sso.digital-id.my/realms/upnm',
    'ONEID_MYDID_CLIENT_ID' => 'upnm-generic',
    'ONEID_MYDID_REDIRECT_URI' => 'https://oneid-uat.upnm.edu.my/auth/mydigitalid/callback.php',
    'ONEID_MYDID_POST_LOGOUT_REDIRECT_URI' => 'https://oneid-uat.upnm.edu.my/',
    'ONEID_MYDID_SCOPE' => 'openid',
    'ONEID_MYDID_HTTP_TIMEOUT_SECONDS' => '12',
    'ONEID_MYDID_PKCE_METHOD' => 'S256',
];
$reader = static fn(string $key, mixed $fallback = null): mixed => $valid[$key] ?? $fallback;
$secretCalls = 0;
$secretReader = static function (string $key, bool $required = true) use (&$secretCalls): string {
    $secretCalls++;
    return 'fixture-secret-value-not-real';
};

$disabled = MyDigitalIdConfig::fromRuntime($reader, $secretReader);
$check(!$disabled->enabled && $secretCalls === 0, 'disabled configuration never reads the client secret');

try {
    (new MyDigitalIdProtocolClient($disabled))->build();
    $disabledBlocked = false;
} catch (MyDigitalIdConfigurationException $exception) {
    $disabledBlocked = $exception->reason === 'MYDID_DISABLED';
}
$check($disabledBlocked, 'disabled protocol client cannot be built');

$enabledValues = $valid;
$enabledValues['ONEID_MYDID_ENABLED'] = 'true';
$enabledReader = static fn(string $key, mixed $fallback = null): mixed =>
    $enabledValues[$key] ?? $fallback;
$enabled = MyDigitalIdConfig::fromRuntime($enabledReader, $secretReader);
$client = (new MyDigitalIdProtocolClient($enabled))->build();
$check(
    $enabled->enabled
        && $secretCalls === 1
        && $client->getClientID() === 'upnm-generic'
        && $client->getIssuer() === $enabled->issuer,
    'enabled fixture builds the expected confidential client without network I/O'
);
$check(
    $client->getRedirectURL() === $enabled->redirectUri
        && $client->getScopes() === ['openid']
        && $client->getResponseTypes() === ['code'],
    'client pins redirect URI, openid scope and code response type'
);
$check(
    $client->getCodeChallengeMethod() === 'S256'
        && $client->getVerifyPeer()
        && $client->getVerifyHost()
        && $client->getTimeout() === 12
        && !$client->getHttpUpgradeInsecureRequests(),
    'client pins PKCE S256, TLS verification, timeout and explicit redirect URL behavior'
);

$metadata = [
    'issuer' => $enabled->issuer,
    'authorization_endpoint' => $enabled->issuer . '/protocol/openid-connect/auth',
    'token_endpoint' => $enabled->issuer . '/protocol/openid-connect/token',
    'userinfo_endpoint' => $enabled->issuer . '/protocol/openid-connect/userinfo',
    'jwks_uri' => $enabled->issuer . '/protocol/openid-connect/certs',
    'end_session_endpoint' => $enabled->issuer . '/protocol/openid-connect/logout',
    'response_types_supported' => ['code'],
    'grant_types_supported' => ['authorization_code'],
    'code_challenge_methods_supported' => ['S256'],
];
try {
    MyDigitalIdProviderMetadata::assertCompatible($metadata, $enabled);
    $metadataAccepted = true;
} catch (MyDigitalIdConfigurationException) {
    $metadataAccepted = false;
}
$check($metadataAccepted, 'exact compatible provider metadata is accepted');

$tamperedMetadata = $metadata;
$tamperedMetadata['issuer'] = 'https://attacker.invalid/realms/upnm';
try {
    MyDigitalIdProviderMetadata::assertCompatible($tamperedMetadata, $enabled);
    $tamperedBlocked = false;
} catch (MyDigitalIdConfigurationException $exception) {
    $tamperedBlocked = $exception->reason === 'MYDID_METADATA_MISMATCH_ISSUER';
}
$check($tamperedBlocked, 'issuer metadata mismatch is rejected');

$weakMetadata = $metadata;
$weakMetadata['code_challenge_methods_supported'] = ['plain'];
try {
    MyDigitalIdProviderMetadata::assertCompatible($weakMetadata, $enabled);
    $weakPkceBlocked = false;
} catch (MyDigitalIdConfigurationException $exception) {
    $weakPkceBlocked = $exception->reason === 'MYDID_PKCE_UNSUPPORTED';
}
$check($weakPkceBlocked, 'provider without PKCE S256 is rejected');

$invalidCases = [
    'enabled' => ['ONEID_MYDID_ENABLED', 'sometimes', 'MYDID_ENABLED_INVALID'],
    'issuer' => ['ONEID_MYDID_ISSUER', 'https://attacker.invalid/realms/upnm', 'MYDID_ISSUER_INVALID'],
    'client' => ['ONEID_MYDID_CLIENT_ID', 'bad client id', 'MYDID_CLIENT_ID_INVALID'],
    'redirect' => ['ONEID_MYDID_REDIRECT_URI', 'http://oneid-uat.upnm.edu.my/auth/mydigitalid/callback.php', 'MYDID_REDIRECT_URI_INVALID'],
    'redirect_query' => ['ONEID_MYDID_REDIRECT_URI', 'https://oneid-uat.upnm.edu.my/auth/mydigitalid/callback.php?next=evil', 'MYDID_REDIRECT_URI_INVALID'],
    'logout' => ['ONEID_MYDID_POST_LOGOUT_REDIRECT_URI', 'https://oneid-uat.upnm.edu.my/other', 'MYDID_POST_LOGOUT_REDIRECT_URI_INVALID'],
    'scope' => ['ONEID_MYDID_SCOPE', 'openid profile', 'MYDID_SCOPE_INVALID'],
    'timeout' => ['ONEID_MYDID_HTTP_TIMEOUT_SECONDS', '120', 'MYDID_HTTP_TIMEOUT_INVALID'],
    'pkce' => ['ONEID_MYDID_PKCE_METHOD', 'plain', 'MYDID_PKCE_METHOD_INVALID'],
];
foreach ($invalidCases as $name => [$key, $value, $reason]) {
    $case = $valid;
    $case[$key] = $value;
    try {
        MyDigitalIdConfig::fromRuntime(
            static fn(string $readKey, mixed $fallback = null): mixed => $case[$readKey] ?? $fallback,
            $secretReader
        );
        $blocked = false;
    } catch (MyDigitalIdConfigurationException $exception) {
        $blocked = $exception->reason === $reason;
    }
    $check($blocked, 'invalid configuration is rejected: ' . $name);
}

$missingSecretReader = static fn(string $key, bool $required = true): string => '';
try {
    MyDigitalIdConfig::fromRuntime($enabledReader, $missingSecretReader);
    $missingSecretBlocked = false;
} catch (MyDigitalIdConfigurationException $exception) {
    $missingSecretBlocked = $exception->reason === 'MYDID_CLIENT_SECRET_INVALID';
}
$check($missingSecretBlocked, 'enabled configuration without a valid secret is rejected');

printf(
    "RESULT checks=%d failures=%d network_calls=0 runtime_activation=0 schema_mutation=0\n",
    $checks,
    $failures
);
exit($failures === 0 ? 0 : 1);
