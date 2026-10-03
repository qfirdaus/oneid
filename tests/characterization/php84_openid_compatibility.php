<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

// Fail on dependency load warnings, including implicit-nullable notices on PHP 8.4.
set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
try {
    $class = new ReflectionClass(\Jumbojett\OpenIDConnectClient::class);
    $expected = [
        '__construct' => ['provider_url', 'client_id', 'client_secret', 'issuer'],
        'verifyJWTClaims' => ['accessToken'],
        'requestUserInfo' => ['attribute'],
        'getVerifiedClaims' => ['attribute'],
        'fetchURL' => ['post_body'],
        'introspectToken' => ['clientId', 'clientSecret'],
        'revokeToken' => ['clientId', 'clientSecret'],
    ];
    $checks = 0;
    foreach ($expected as $method => $names) {
        foreach ($class->getMethod($method)->getParameters() as $parameter) {
            if (!in_array($parameter->getName(), $names, true)) continue;
            $type = $parameter->getType();
            if (!$type instanceof ReflectionNamedType || $type->getName() !== 'string'
                || !$type->allowsNull() || !$parameter->isDefaultValueAvailable()
                || $parameter->getDefaultValue() !== null) {
                throw new RuntimeException('Nullable contract changed: ' . $method . '/' . $parameter->getName());
            }
            $checks++;
        }
    }
    if ($checks !== 12) throw new RuntimeException('Missing parameter coverage.');
    // Construction only; no external provider requests or credentials.
    new \Jumbojett\OpenIDConnectClient();
    new \Jumbojett\OpenIDConnectClient('https://issuer.example.invalid', 'fixture-client', 'fixture-secret');
    echo "PASS: 12 nullable contracts and null/string construction; no load warnings on PHP " . PHP_VERSION . "\n";
} finally { restore_error_handler(); }
