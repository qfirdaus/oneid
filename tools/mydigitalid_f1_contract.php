<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

$root = dirname(__DIR__);
$checks = 0;
$failures = 0;
$check = static function (bool $passed, string $description) use (&$checks, &$failures): void {
    $checks++;
    if (!$passed) {
        $failures++;
    }
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $description);
};
$read = static function (string $path) use ($root): string {
    $content = file_get_contents($root . '/' . $path);
    return $content === false ? '' : $content;
};

$composer = json_decode($read('composer.json'), true);
$lock = json_decode($read('composer.lock'), true);
$runtime = $read('config/runtime.php');
$template = $read('docs/examples/oneid-secrets.example.php');
$config = $read('app/Auth/MyDigitalId/MyDigitalIdConfig.php');
$protocol = $read('app/Auth/MyDigitalId/MyDigitalIdProtocolClient.php');
$login = $read('index.php');
$publicTree = glob($root . '/public/auth/mydigitalid/*') ?: [];

$lockedPackages = [];
foreach ($lock['packages'] ?? [] as $package) {
    if (is_array($package) && isset($package['name'], $package['version'])) {
        $lockedPackages[(string) $package['name']] = (string) $package['version'];
    }
}

$check(
    ($composer['require']['jumbojett/openid-connect-php'] ?? '') === '^1.0.2'
        && ($lockedPackages['jumbojett/openid-connect-php'] ?? '') === 'v1.0.2',
    'OIDC dependency is explicitly constrained and reproducibly locked'
);
$check(
    ($composer['config']['platform']['php'] ?? '') === '8.3.0'
        && ($composer['autoload']['psr-4']['OneId\\App\\'] ?? '') === 'app/',
    'Composer platform and OneID PSR-4 boundary are explicit'
);
$check(
    str_contains($runtime, "'ONEID_MYDID_ENABLED' => 'false'")
        && str_contains($runtime, "'ONEID_MYDID_PKCE_METHOD' => 'S256'")
        && !str_contains($runtime, 'ONEID_MYDID_CLIENT_SECRET'),
    'committed runtime defaults are disabled, use PKCE S256 and contain no secret'
);
$check(
    str_contains($template, "'ONEID_MYDID_CLIENT_SECRET' => ''")
        && !preg_match("/'ONEID_MYDID_CLIENT_SECRET'\\s*=>\\s*'[^']+'/", $template),
    'private runtime template documents an empty secret placeholder only'
);
$check(
    str_contains($config, "oneid_secret(\$key, \$required)")
        && str_contains($config, "if (\$enabled)")
        && str_contains($config, 'MYDID_CLIENT_SECRET_INVALID'),
    'client secret is requested only for enabled configuration'
);
$check(
    str_contains($protocol, "throw new MyDigitalIdConfigurationException('MYDID_DISABLED')")
        && str_contains($protocol, 'setVerifyPeer(true)')
        && str_contains($protocol, 'setVerifyHost(true)')
        && str_contains($protocol, "setTokenEndpointAuthMethodsSupported(['client_secret_basic'])")
        && str_contains($protocol, 'setHttpUpgradeInsecureRequests(false)'),
    'protocol client fails closed and pins backend TLS/auth behavior'
);
$check(
    str_contains($protocol, "setResponseTypes(['code'])")
        && str_contains($protocol, 'setCodeChallengeMethod($this->config->pkceMethod)')
        && str_contains($protocol, 'setIssuerValidator'),
    'protocol client pins code flow, PKCE and exact issuer validation'
);
$check(
    (
        $publicTree === []
        || (
            is_file($root . '/public/auth/mydigitalid/login.php')
            && is_file($root . '/public/auth/mydigitalid/callback.php')
        )
    )
        && !str_contains($login, 'action_mydigitalid')
        && !str_contains($login, 'mydigitalid_auth'),
    'no active login action exists; later-phase endpoints, when present, remain separate and dormant'
);
$check(
    str_contains($read('.gitignore'), '/vendor/')
        && str_contains($read('.gitignore'), '/resources/references/mydigital-id/'),
    'generated dependencies and provider reference material stay outside Git'
);

printf("RESULT checks=%d failures=%d runtime_activation=0 schema_mutation=0\n", $checks, $failures);
exit($failures === 0 ? 0 : 1);
