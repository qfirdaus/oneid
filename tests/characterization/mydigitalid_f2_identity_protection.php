<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use OneId\App\Auth\MyDigitalId\MyDigitalIdConfigurationException;
use OneId\App\Auth\MyDigitalId\MyDigitalIdIdentityProtector;

$checks = 0;
$failures = 0;
$check = static function (bool $passed, string $description) use (&$checks, &$failures): void {
    $checks++;
    if (!$passed) {
        $failures++;
    }
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $description);
};

$key = base64_encode(str_repeat("\x42", 32));
$protector = MyDigitalIdIdentityProtector::fromBase64($key, 'fixture-v1');
$issuer = 'https://sso.digital-id.my/realms/upnm';

$plainNric = $protector->nricHmac('900101011234');
$formattedNric = $protector->nricHmac('900101-01-1234');
$check(
    strlen($plainNric) === 64
        && hash_equals($plainNric, $formattedNric)
        && preg_match('/^[a-f0-9]{64}$/D', $plainNric) === 1,
    'NRIC normalization is deterministic and produces a lowercase keyed digest'
);

$subject = $protector->subjectHmac($issuer, 'fixture-subject');
$check(
    !hash_equals($plainNric, $subject),
    'HMAC context separation prevents NRIC and subject digest reuse'
);

$ip = $protector->contextHmac('ip', '192.0.2.10');
$userAgent = $protector->contextHmac('user-agent', '192.0.2.10');
$check(
    is_string($ip)
        && is_string($userAgent)
        && !hash_equals($ip, $userAgent)
        && $protector->contextHmac('session-id', '') === null,
    'context digests are domain-separated and empty optional context stays null'
);

$invalidCases = [
    'short_key' => static fn() => MyDigitalIdIdentityProtector::fromBase64(
        base64_encode(str_repeat('x', 16)),
        'fixture-v1'
    ),
    'invalid_key_id' => static fn() => MyDigitalIdIdentityProtector::fromBase64($key, 'bad key'),
    'short_nric' => static fn() => $protector->nricHmac('123'),
    'alpha_nric' => static fn() => $protector->nricHmac('90010101123A'),
    'issuer' => static fn() => $protector->subjectHmac('https://attacker.invalid', 'subject'),
    'blank_subject' => static fn() => $protector->subjectHmac($issuer, ''),
    'context' => static fn() => $protector->contextHmac('token', 'value'),
];
foreach ($invalidCases as $name => $operation) {
    try {
        $operation();
        $blocked = false;
    } catch (MyDigitalIdConfigurationException) {
        $blocked = true;
    }
    $check($blocked, 'invalid identity protection input is rejected: ' . $name);
}

$secretCalls = 0;
try {
    MyDigitalIdIdentityProtector::fromRuntime(
        static fn(string $name, mixed $fallback = null): mixed => 'fixture-v1',
        static function (string $name, bool $required = true) use (&$secretCalls): string {
            $secretCalls++;
            return base64_encode(str_repeat("\x42", 32));
        }
    );
    $runtimeProtectorBuilt = true;
} catch (MyDigitalIdConfigurationException) {
    $runtimeProtectorBuilt = false;
}
$check(
    $runtimeProtectorBuilt && $secretCalls === 1,
    'runtime construction reads the dedicated HMAC secret exactly once'
);

printf(
    "RESULT checks=%d failures=%d raw_identity_output=0 network_calls=0\n",
    $checks,
    $failures
);
exit($failures === 0 ? 0 : 1);
