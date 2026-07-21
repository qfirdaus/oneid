<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

$root = dirname(__DIR__);
require_once $root . '/app/Auth/TotpKeyring.php';
require_once $root . '/app/Auth/TotpSecretCipher.php';

use OneId\App\Auth\TotpKeyring;
use OneId\App\Auth\TotpSecretCipher;

$checks = 0;
$failed = 0;
$report = static function (bool $passed, string $label) use (&$checks, &$failed): void {
    $checks++;
    if (!$passed) {
        $failed++;
    }
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $label);
};

$up = (string) file_get_contents($root . '/docs/migrations/20260720_f7_1_admin_step_up_foundation_up.sql');
$down = (string) file_get_contents($root . '/docs/migrations/20260720_f7_1_admin_step_up_foundation_down.sql');
$runner = (string) file_get_contents($root . '/tools/f7_1_schema_migrate.php');
$rehearsal = (string) file_get_contents($root . '/tools/f7_1_isolated_schema_rehearsal.php');
$keyringSetup = (string) file_get_contents($root . '/tools/f7_1_keyring_setup.php');

$report(
    str_contains($up, 'admin_2fa_enabled TINYINT(1) NOT NULL DEFAULT 0')
        && str_contains($up, 'chk_sys_config_admin_2fa_enabled'),
    'feature setting is installed fail-closed and constrained'
);
$report(
    str_contains($up, 'CREATE TABLE admin_step_up_challenges')
        && str_contains($up, 'CREATE TABLE admin_step_up_grants')
        && str_contains($up, 'CREATE TABLE admin_mfa_factors')
        && str_contains($up, 'CREATE TABLE admin_mfa_preferences'),
    'challenge, grant, encrypted factor and per-admin preference stores exist'
);
$report(
    str_contains($up, "'ADMIN_ACCESS','SECURITY_CONFIGURATION_CHANGE','ACTIVE_SESSION_REVOCATION'")
        && str_contains($up, "'EMAIL_OTP','TOTP'"),
    'purpose and factor values are database constrained'
);
$report(
    str_contains($up, 'encrypted_secret VARBINARY')
        && str_contains($up, 'secret_nonce BINARY(24)')
        && str_contains($up, 'key_version VARCHAR(32)')
        && !preg_match('/\b(totp_secret|plaintext_secret)\b/i', $up),
    'factor schema stores ciphertext, nonce and key version without plaintext secret'
);
$report(
    str_contains($down, 'DROP TABLE IF EXISTS admin_step_up_grants')
        && str_contains($down, 'DROP COLUMN admin_2fa_enabled'),
    'explicit rollback migration removes dependent stores before the setting'
);
$report(
    str_contains($runner, 'ONEID_F7_CHANGE_ID')
        && str_contains($runner, 'ONEID_F7_BACKUP_EVIDENCE')
        && str_contains($runner, 'F7_PARTIAL_SCHEMA_REQUIRES_MANUAL_RECONCILIATION')
        && str_contains($runner, "exit(\$enabled === 0 ? 0 : 1)"),
    'apply runner requires change, backup and keyring gates and verifies flag OFF'
);
$report(
    str_contains($rehearsal, "'oneid_f71_rehearsal_' . \$suffix")
        && str_contains($rehearsal, 'DROP DATABASE')
        && str_contains($rehearsal, 'source_mutations=0')
        && str_contains($rehearsal, 'rehearsal_database_removed=yes'),
    'isolated forward/down rehearsal uses a bounded random database and mandatory cleanup'
);
$report(
    str_contains($keyringSetup, 'MUST_BE_OUTSIDE_REPOSITORY')
        && str_contains($keyringSetup, "fopen(\$path, 'x')")
        && str_contains($keyringSetup, 'SODIUM_CRYPTO_SECRETBOX_KEYBYTES')
        && str_contains($keyringSetup, 'chmod($path, 0600)')
        && !str_contains($keyringSetup, "echo \$key"),
    'keyring setup is outside-repository, exclusive, permission-bound and secret-safe'
);

$tempDirectory = sys_get_temp_dir() . '/oneid-f7-keyring-' . bin2hex(random_bytes(8));
mkdir($tempDirectory, 0700, true);
$keyringFile = $tempDirectory . '/keyring.php';
$keyV1 = random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
$keyV2 = random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
$keyringSource = '<?php return ' . var_export([
    'active_version' => 'v2',
    'keys' => ['v1' => base64_encode($keyV1), 'v2' => base64_encode($keyV2)],
], true) . ';';
file_put_contents($keyringFile, $keyringSource, LOCK_EX);
chmod($keyringFile, 0640);

try {
    $keyring = TotpKeyring::fromFile($keyringFile);
    $cipher = new TotpSecretCipher($keyring);
    $secret = 'JBSWY3DPEHPK3PXP';
    $encrypted = $cipher->encrypt($secret);
    $report(
        $encrypted['key_version'] === 'v2'
            && $encrypted['ciphertext'] !== $secret
            && strlen($encrypted['nonce']) === SODIUM_CRYPTO_SECRETBOX_NONCEBYTES,
        'active version encrypts TOTP material with a random nonce'
    );
    $report(
        $cipher->decrypt($encrypted['ciphertext'], $encrypted['nonce'], 'v2') === $secret,
        'encrypted TOTP material round-trips using its recorded key version'
    );

    $tampered = $encrypted['ciphertext'];
    $tampered[0] = chr(ord($tampered[0]) ^ 1);
    try {
        $cipher->decrypt($tampered, $encrypted['nonce'], 'v2');
        $report(false, 'tampered ciphertext is rejected');
    } catch (RuntimeException $exception) {
        $report($exception->getMessage() === 'F7_TOTP_DECRYPT_FAILED', 'tampered ciphertext is rejected');
    }

    try {
        $cipher->decrypt($encrypted['ciphertext'], $encrypted['nonce'], 'missing');
        $report(false, 'unknown key version is rejected');
    } catch (RuntimeException $exception) {
        $report($exception->getMessage() === 'F7_TOTP_KEY_VERSION_UNKNOWN', 'unknown key version is rejected');
    }

    chmod($keyringFile, 0666);
    try {
        TotpKeyring::fromFile($keyringFile);
        $report(false, 'group/world-writable keyring is rejected');
    } catch (RuntimeException $exception) {
        $report(
            $exception->getMessage() === 'F7_TOTP_KEYRING_PERMISSIONS_INVALID',
            'group/world-writable keyring is rejected'
        );
    }

    chmod($keyringFile, 0644);
    try {
        TotpKeyring::fromFile($keyringFile);
        $report(false, 'world-readable keyring is rejected');
    } catch (RuntimeException $exception) {
        $report(
            $exception->getMessage() === 'F7_TOTP_KEYRING_PERMISSIONS_INVALID',
            'world-readable keyring is rejected'
        );
    }
} finally {
    if (is_file($keyringFile)) {
        unlink($keyringFile);
    }
    if (is_dir($tempDirectory)) {
        rmdir($tempDirectory);
    }
    sodium_memzero($keyV1);
    sodium_memzero($keyV2);
}

printf("RESULT checks=%d failed=%d mutation_statements=0\n", $checks, $failed);
exit($failed === 0 ? 0 : 1);
