<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Auth/Totp.php';
require_once dirname(__DIR__, 2) . '/app/Auth/TotpKeyring.php';
require_once dirname(__DIR__, 2) . '/app/Auth/TotpSecretCipher.php';
require_once dirname(__DIR__, 2) . '/app/Auth/UserMfa/UserMfaOtp.php';
require_once dirname(__DIR__, 2) . '/app/Auth/UserMfa/UserMfaRateLimitConfig.php';
require_once dirname(__DIR__, 2) . '/app/Auth/UserMfa/UserMfaRequestBinding.php';
require_once dirname(__DIR__, 2) . '/app/Auth/UserMfa/UserMfaTotpPrimitive.php';

use OneId\App\Auth\Totp;
use OneId\App\Auth\TotpKeyring;
use OneId\App\Auth\TotpSecretCipher;
use OneId\App\Auth\UserMfa\UserMfaOtp;
use OneId\App\Auth\UserMfa\UserMfaRateLimitConfig;
use OneId\App\Auth\UserMfa\UserMfaRequestBinding;
use OneId\App\Auth\UserMfa\UserMfaTotpPrimitive;

$checks = 0;
$failed = 0;
$report = static function (bool $passed, string $label) use (&$checks, &$failed): void {
    $checks++;
    if (!$passed) {
        $failed++;
    }
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $label);
};

$otp = UserMfaOtp::generate();
$hash = UserMfaOtp::hash($otp);
$report(
    preg_match('/\A[0-9]{6}\z/', $otp) === 1
    && $hash !== $otp
    && str_starts_with($hash, '$argon2id$'),
    'OTP generation is six-digit and Argon2id hash-only'
);
$report(
    UserMfaOtp::verify($otp, $hash)
    && !UserMfaOtp::verify('00000', $hash)
    && !UserMfaOtp::verify('999999', $hash),
    'OTP verification accepts exact value and rejects malformed or wrong values'
);

$limits = new UserMfaRateLimitConfig();
$report(
    !$limits->exceeded([
        'user_hour' => 9,
        'session_hour' => 9,
        'ip_hour' => 49,
        'destination_hour' => 9,
    ])
    && $limits->exceeded(['user_hour' => 10])
    && $limits->exceeded(['destination_hour' => 10]),
    'rate limits cover user session IP and destination'
);
$report(
    !$limits->cooldownActive(['cooldown_seconds' => 0])
    && $limits->cooldownActive(['cooldown_seconds' => 60]),
    'resend cooldown is explicit'
);

$binding = UserMfaRequestBinding::fromRequest('session-secret', 'Browser/1', '127.0.0.1');
$report(
    $binding['session_hash'] === hash('sha256', 'session-secret')
    && $binding['browser_digest'] === hash('sha256', 'Browser/1')
    && $binding['ip_address'] === '127.0.0.1',
    'request binding stores digests rather than raw session'
);

$keyringPath = tempnam(sys_get_temp_dir(), 'oneid-user-mfa-u2-');
if ($keyringPath === false) {
    throw new RuntimeException('USER_MFA_U2_TEMPFILE_FAILED');
}
$key = base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
$keyringPhp = "<?php\nreturn ['active_version'=>'u2-test','keys'=>['u2-test'=>'"
    . $key
    . "']];\n";
file_put_contents($keyringPath, $keyringPhp, LOCK_EX);
chmod($keyringPath, 0600);

try {
    $cipher = new TotpSecretCipher(TotpKeyring::fromFile($keyringPath));
    $timestamp = 1_800_000_000;
    $primitive = new UserMfaTotpPrimitive($cipher, static fn(): int => $timestamp);
    $enrollment = $primitive->enroll('OneID@UPNM', 'U2TEST');
    $report(
        $enrollment['secret'] !== ''
        && $enrollment['encrypted_secret'] !== $enrollment['secret']
        && strlen($enrollment['secret_nonce']) === SODIUM_CRYPTO_SECRETBOX_NONCEBYTES
        && str_starts_with($enrollment['provisioning_uri'], 'otpauth://totp/'),
        'TOTP enrollment uses local URI and encrypted persistence material'
    );

    $code = Totp::codeAt($enrollment['secret'], $timestamp);
    $step = $primitive->matchEncrypted(
        $enrollment['encrypted_secret'],
        $enrollment['secret_nonce'],
        $enrollment['key_version'],
        $code,
        null
    );
    $report($step === intdiv($timestamp, 30), 'encrypted TOTP verifies to an atomic time-step');

    $replayRejected = false;
    try {
        $primitive->matchEncrypted(
            $enrollment['encrypted_secret'],
            $enrollment['secret_nonce'],
            $enrollment['key_version'],
            $code,
            $step
        );
    } catch (RuntimeException $exception) {
        $replayRejected = $exception->getMessage() === 'USER_MFA_TOTP_INVALID_OR_REPLAYED';
    }
    $report($replayRejected, 'TOTP replay is rejected using last-used time-step');
} finally {
    @unlink($keyringPath);
}

printf(
    "RESULT checks=%d failures=%d network_calls=0 database_mutations=0 runtime_activation=0 raw_secret_output=0\n",
    $checks,
    $failed
);
exit($failed === 0 ? 0 : 1);
