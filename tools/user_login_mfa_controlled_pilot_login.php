<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' || !function_exists('posix_isatty') || !posix_isatty(STDIN)) {
    fwrite(STDERR, "FAIL CONTROLLED_PILOT_REQUIRES_INTERACTIVE_TTY\n");
    exit(2);
}
if ((getenv('ONEID_USER_MFA_PILOT_CONFIRMATION') ?: '')
    !== 'TEST 0530-09 PASSWORD MFA SSO ACL AND REVOKE TOKEN'
) {
    fwrite(STDERR, "FAIL CONTROLLED_PILOT_CONFIRMATION_REQUIRED\n");
    exit(2);
}

require_once dirname(__DIR__) . '/lib/config.php';
foreach ([
    'app/Auth/UserMfa/UserMfaAuditWriterInterface.php',
    'app/Auth/UserMfa/UserMfaEmailSenderInterface.php',
    'app/Auth/UserMfa/UserMfaEmailOtpException.php',
    'app/Auth/UserMfa/UserMfaEmailOtpPersistenceInterface.php',
    'app/Auth/UserMfa/UserMfaOtp.php',
    'app/Auth/UserMfa/UserMfaRateLimitConfig.php',
    'app/Auth/UserMfa/UserMfaRequestBinding.php',
    'app/Auth/UserMfa/UserMfaEmailOtpService.php',
    'app/Auth/UserMfa/UserMfaPendingLoginException.php',
    'app/Auth/UserMfa/UserMfaPendingLoginPersistenceInterface.php',
    'app/Auth/UserMfa/UserMfaLoginFinalizerInterface.php',
    'app/Auth/UserMfa/UserLoginMfaPolicy.php',
    'app/Auth/UserMfa/UserMfaPendingLoginCoordinator.php',
    'app/Auth/UserMfa/LegacyUserMfaAuditWriter.php',
    'app/Auth/UserMfa/PdoUserMfaPendingLoginPersistence.php',
    'app/Auth/UserMfa/PdoUserMfaEmailOtpPersistence.php',
    'app/Auth/UserMfa/UserMfaPhpMailerSender.php',
] as $file) {
    require_once dirname(__DIR__) . '/' . $file;
}

use OneId\App\Auth\UserMfa\LegacyUserMfaAuditWriter;
use OneId\App\Auth\UserMfa\PdoUserMfaEmailOtpPersistence;
use OneId\App\Auth\UserMfa\PdoUserMfaPendingLoginPersistence;
use OneId\App\Auth\UserMfa\UserLoginMfaPolicy;
use OneId\App\Auth\UserMfa\UserMfaEmailOtpService;
use OneId\App\Auth\UserMfa\UserMfaLoginFinalizerInterface;
use OneId\App\Auth\UserMfa\UserMfaPendingLoginCoordinator;
use OneId\App\Auth\UserMfa\UserMfaPhpMailerSender;

final class ControlledPilotFinalizer implements UserMfaLoginFinalizerInterface
{
    public function __construct(private readonly object $operation) {}
    public function prepare(string $userId, string $correlationId): array
    {
        $token = oneid_generate_sso_token();
        if ($this->operation->add_new_token(
            $token,
            $userId,
            'USER_MFA_U7_CONTROLLED_PILOT'
        ) !== 1) {
            throw new RuntimeException('CONTROLLED_PILOT_TOKEN_CREATE_FAILED');
        }
        return ['token' => $token, 'u_id' => $userId];
    }
    public function compensate(array $handle): void
    {
        if (isset($handle['token'], $handle['u_id'])) {
            $this->operation->update_specific_token_status(
                (string) $handle['u_id'],
                (string) $handle['token'],
                0
            );
        }
    }
}

$hiddenPrompt = static function (string $label): string {
    fwrite(STDOUT, $label);
    shell_exec('stty -echo');
    try {
        $value = fgets(STDIN);
    } finally {
        shell_exec('stty echo');
        fwrite(STDOUT, PHP_EOL);
    }
    return trim((string) $value);
};
$digestIds = static fn(array $rows): string => hash(
    'sha256',
    json_encode(array_values(array_map(
        static fn(array $row): string => (string) ($row['sp_id'] ?? ''),
        $rows
    )), JSON_THROW_ON_ERROR)
);

$loginAlias = '0530-09';
$password = $hiddenPrompt('OneID password (hidden): ');
$authenticated = $operation->func_authenticate3($loginAlias, $password);
unset($password);
if (!is_array($authenticated)
    || (int) ($authenticated['avail_status'] ?? 0) !== 1
    || filter_var(
        (string) ($authenticated['data5'] ?? ''),
        FILTER_VALIDATE_EMAIL
    ) === false
) {
    fwrite(STDERR, "FAIL CONTROLLED_PILOT_PRIMARY_AUTH_REJECTED\n");
    exit(1);
}
$userId = (string) $authenticated['u_id'];
unset($authenticated['u_password']);
$aclBefore = [
    'single' => $operation->specfic_user_get_sp_list_by_specific_sp($userId),
    'blacklist' => $operation->specfic_user_get_sp_blacklist($userId),
    'group' => $operation->specfic_user_get_sp_list_by_group($authenticated['u_category']),
];

$pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$audit = new LegacyUserMfaAuditWriter($operation);
$pendingPersistence = new PdoUserMfaPendingLoginPersistence($pdo, $audit);
$coordinator = new UserMfaPendingLoginCoordinator($pendingPersistence);
$sessionSeed = bin2hex(random_bytes(32));
$browser = 'OneID U7 Controlled Pilot CLI';
$ip = '127.0.0.1';
$policy = new UserLoginMfaPolicy(
    'PILOT_ENFORCED',
    'PASSWORD_ONLY',
    true,
    false,
    300,
    300,
    5,
    60,
    10
);
$pending = $coordinator->begin(
    $userId,
    'PASSWORD',
    $sessionSeed,
    $browser,
    $ip,
    $policy,
    true
);
$emailService = new UserMfaEmailOtpService(
    new PdoUserMfaEmailOtpPersistence($pdo, $audit),
    new UserMfaPhpMailerSender()
);
$challenge = $emailService->request(
    (string) $pending['transaction_id'],
    $userId,
    $sessionSeed,
    $browser,
    $ip,
    'ms'
);
fwrite(STDOUT, "OTP sent to registered e-mail.\n");
$otp = $hiddenPrompt('6-digit OTP (hidden): ');
$emailService->verify(
    (string) $pending['transaction_id'],
    (string) $challenge['challenge_id'],
    $otp,
    $sessionSeed,
    $browser,
    $ip
);
unset($otp);
$completed = $coordinator->finalize(
    (string) $pending['transaction_id'],
    $sessionSeed,
    $browser,
    $ip,
    new ControlledPilotFinalizer($operation)
);
$handle = $completed['completion_handle'] ?? [];
$token = is_array($handle) ? (string) ($handle['token'] ?? '') : '';
$tokenActive = $token !== '' && $operation->is_specific_token_active($userId, $token);
$aclAfter = [
    'single' => $operation->specfic_user_get_sp_list_by_specific_sp($userId),
    'blacklist' => $operation->specfic_user_get_sp_blacklist($userId),
    'group' => $operation->specfic_user_get_sp_list_by_group($authenticated['u_category']),
];
$aclParity = $digestIds($aclBefore['single']) === $digestIds($aclAfter['single'])
    && $digestIds($aclBefore['blacklist']) === $digestIds($aclAfter['blacklist'])
    && $digestIds($aclBefore['group']) === $digestIds($aclAfter['group']);
$revoked = $token !== ''
    && $operation->update_specific_token_status($userId, $token, 0) >= 1;
$tokenInactive = $token !== ''
    && !$operation->is_specific_token_active($userId, $token);
unset($token, $sessionSeed, $handle);

$passed = $completed['code'] === 'USER_MFA_LOGIN_AUTHORIZED'
    && $tokenActive
    && $aclParity
    && $revoked
    && $tokenInactive;
printf(
    "%s controlled password+email-MFA SSO/ACL token lifecycle canonical_id_output=0 password_output=0 otp_output=0 token_output=0\n",
    $passed ? 'PASS' : 'FAIL'
);
printf(
    "RESULT checks=1 failures=%d token_created_after_mfa=%s acl_parity=%s token_revoked=%s global_activation=0\n",
    $passed ? 0 : 1,
    $tokenActive ? 'yes' : 'no',
    $aclParity ? 'yes' : 'no',
    $tokenInactive ? 'yes' : 'no'
);
exit($passed ? 0 : 1);
