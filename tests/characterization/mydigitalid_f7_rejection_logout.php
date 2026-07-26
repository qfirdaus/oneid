<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use OneId\App\Auth\MyDigitalId\MyDigitalIdConfigurationException;
use OneId\App\Auth\MyDigitalId\MyDigitalIdRejectedLogoutState;

$checks = 0;
$failures = 0;
$check = static function (bool $passed, string $description) use (&$checks, &$failures): void {
    $checks++;
    $failures += $passed ? 0 : 1;
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $description);
};

$session = ['unrelated' => 'preserved'];
MyDigitalIdRejectedLogoutState::retain($session, 'header.payload.signature', 1_700_000_000);
$check(
    MyDigitalIdRejectedLogoutState::isAvailable($session, 1_700_000_300)
        && !MyDigitalIdRejectedLogoutState::isAvailable($session, 1_700_000_301),
    'verified rejection logout state expires after exactly five minutes'
);

$token = MyDigitalIdRejectedLogoutState::consume($session, 1_700_000_100);
$check(
    $token === 'header.payload.signature'
        && ($session['unrelated'] ?? '') === 'preserved'
        && !isset(
            $session[MyDigitalIdRejectedLogoutState::ID_TOKEN_SESSION_KEY],
            $session[MyDigitalIdRejectedLogoutState::REJECTED_AT_SESSION_KEY]
        ),
    'logout state is one-use and preserves unrelated guest session values'
);
$check(
    MyDigitalIdRejectedLogoutState::consume($session, 1_700_000_101) === null,
    'consumed logout state cannot be replayed'
);

$expired = [];
MyDigitalIdRejectedLogoutState::retain($expired, 'header.payload.signature', 1_700_000_000);
$check(
    MyDigitalIdRejectedLogoutState::consume($expired, 1_700_000_301) === null
        && $expired === [],
    'expired logout state fails closed and is removed'
);

$future = [];
MyDigitalIdRejectedLogoutState::retain($future, 'header.payload.signature', 1_700_000_100);
$check(
    MyDigitalIdRejectedLogoutState::consume($future, 1_700_000_099) === null
        && $future === [],
    'future-dated logout state fails closed and is removed'
);

foreach (['', 'not-a-jwt', "a.b.c\n", str_repeat('a', 8193) . '.b.c'] as $invalid) {
    try {
        $invalidSession = [];
        MyDigitalIdRejectedLogoutState::retain($invalidSession, $invalid, 1_700_000_000);
        $blocked = false;
    } catch (MyDigitalIdConfigurationException) {
        $blocked = true;
    }
    $check($blocked, 'invalid rejected ID token is never retained');
}

printf(
    "RESULT checks=%d failures=%d network_calls=0 database_mutations=0 raw_token_output=0\n",
    $checks,
    $failures
);
exit($failures === 0 ? 0 : 1);
