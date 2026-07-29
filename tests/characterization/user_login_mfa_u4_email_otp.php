<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Auth/UserMfa/UserMfaOtp.php';
require_once dirname(__DIR__, 2) . '/app/Auth/UserMfa/UserMfaRateLimitConfig.php';
require_once dirname(__DIR__, 2) . '/app/Auth/UserMfa/UserMfaRequestBinding.php';
require_once dirname(__DIR__, 2) . '/app/Auth/UserMfa/UserMfaEmailSenderInterface.php';
require_once dirname(__DIR__, 2) . '/app/Auth/UserMfa/UserMfaEmailOtpException.php';
require_once dirname(__DIR__, 2) . '/app/Auth/UserMfa/UserMfaEmailOtpPersistenceInterface.php';
require_once dirname(__DIR__, 2) . '/app/Auth/UserMfa/UserMfaEmailOtpService.php';

use OneId\App\Auth\UserMfa\UserMfaEmailOtpException;
use OneId\App\Auth\UserMfa\UserMfaEmailOtpPersistenceInterface;
use OneId\App\Auth\UserMfa\UserMfaEmailOtpService;
use OneId\App\Auth\UserMfa\UserMfaEmailSenderInterface;
use OneId\App\Auth\UserMfa\UserMfaRateLimitConfig;

final class U4Sender implements UserMfaEmailSenderInterface
{
    public string $otp = '';
    public string $email = '';
    public bool $succeeds = true;
    public int $calls = 0;

    public function send(string $otp, string $email, string $displayName, string $locale): bool
    {
        $this->calls++;
        $this->otp = $otp;
        $this->email = $email;
        return $this->succeeds;
    }
}

final class U4Persistence implements UserMfaEmailOtpPersistenceInterface
{
    public array $transactions = [];
    public array $challenges = [];
    public array $audits = [];
    public array $stats = [
        'cooldown_seconds' => 0,
        'user_hour' => 0,
        'session_hour' => 0,
        'ip_hour' => 0,
        'destination_hour' => 0,
    ];
    private ?array $snapshot = null;

    public function addTransaction(
        string $id,
        string $userId,
        string $sessionId,
        string $browser,
        int $expiry
    ): void {
        $this->transactions[$id] = [
            'transaction_id' => $id,
            'u_id' => $userId,
            'transaction_status' => 'PENDING',
            'session_binding_hash' => hash('sha256', $sessionId),
            'browser_digest' => hash('sha256', $browser),
            'expires_at_epoch' => $expiry,
            'email_enabled' => 1,
            'email' => 'pilot@example.test',
            'display_name' => 'Pilot User',
            'max_attempts' => 5,
            'otp_ttl_seconds' => 300,
        ];
    }
    public function beginTransaction(): void
    {
        $this->snapshot = [$this->transactions, $this->challenges, $this->audits];
    }
    public function commit(): void
    {
        $this->snapshot = null;
    }
    public function rollback(): void
    {
        if ($this->snapshot !== null) {
            [$this->transactions, $this->challenges, $this->audits] = $this->snapshot;
        }
        $this->snapshot = null;
    }
    public function emailChallengeContextForUpdate(string $transactionId, string $userId): array|false
    {
        $row = $this->transactions[$transactionId] ?? false;
        return is_array($row) && $row['u_id'] === $userId ? $row : false;
    }
    public function emailRequestStatsForUpdate(
        string $userId,
        string $sessionHash,
        string $ipAddress,
        string $destinationHmac
    ): array {
        return $this->stats;
    }
    public function revokeOpenEmailChallenges(string $transactionId): int
    {
        $count = 0;
        foreach ($this->challenges as &$challenge) {
            if ($challenge['transaction_id'] === $transactionId
                && $challenge['consumed_at'] === null
                && $challenge['revoked_at'] === null
            ) {
                $challenge['revoked_at'] = 'now';
                $challenge['otp_hash'] = null;
                $count++;
            }
        }
        return $count;
    }
    public function createEmailChallenge(array $entry): int
    {
        $this->challenges[$entry['challenge_id']] = $entry + [
            'sent_at' => null,
            'attempts' => 0,
            'consumed_at' => null,
            'revoked_at' => null,
        ];
        return 1;
    }
    public function markChallengeSent(string $challengeId): int
    {
        if (!isset($this->challenges[$challengeId])
            || $this->challenges[$challengeId]['revoked_at'] !== null
        ) {
            return 0;
        }
        $this->challenges[$challengeId]['sent_at'] = 'now';
        return 1;
    }
    public function revokeChallenge(string $challengeId, string $reason): int
    {
        if (!isset($this->challenges[$challengeId])) {
            return 0;
        }
        $this->challenges[$challengeId]['revoked_at'] = $reason;
        $this->challenges[$challengeId]['otp_hash'] = null;
        return 1;
    }
    public function emailChallengeForUpdate(string $challengeId): array|false
    {
        if (!isset($this->challenges[$challengeId])) {
            return false;
        }
        $challenge = $this->challenges[$challengeId];
        $transaction = $this->transactions[$challenge['transaction_id']] ?? [];
        return $challenge + [
            'transaction_status' => $transaction['transaction_status'] ?? null,
            'session_binding_hash' => $transaction['session_binding_hash'] ?? null,
            'browser_digest' => $transaction['browser_digest'] ?? null,
        ];
    }
    public function recordFailedAttempt(string $challengeId): int
    {
        if (!isset($this->challenges[$challengeId])) {
            return 0;
        }
        $this->challenges[$challengeId]['attempts']++;
        if ($this->challenges[$challengeId]['attempts'] >= $this->challenges[$challengeId]['max_attempts']) {
            $this->challenges[$challengeId]['revoked_at'] = 'MAX_ATTEMPTS';
            $this->challenges[$challengeId]['otp_hash'] = null;
        }
        return 1;
    }
    public function consumeEmailChallenge(string $challengeId): int
    {
        if (!isset($this->challenges[$challengeId])
            || $this->challenges[$challengeId]['consumed_at'] !== null
            || $this->challenges[$challengeId]['revoked_at'] !== null
        ) {
            return 0;
        }
        $this->challenges[$challengeId]['consumed_at'] = 'now';
        $this->challenges[$challengeId]['otp_hash'] = null;
        return 1;
    }
    public function markPendingLoginVerified(string $transactionId, string $factorType): int
    {
        if (!isset($this->transactions[$transactionId])
            || $this->transactions[$transactionId]['transaction_status'] !== 'PENDING'
        ) {
            return 0;
        }
        $this->transactions[$transactionId]['transaction_status'] = 'VERIFIED';
        $this->transactions[$transactionId]['verified_factor'] = $factorType;
        return 1;
    }
    public function recordAudit(
        string $event,
        string $userId,
        string $outcome,
        string $reason,
        string $correlationId,
        string $ipAddress
    ): int {
        $this->audits[] = compact(
            'event',
            'userId',
            'outcome',
            'reason',
            'correlationId',
            'ipAddress'
        );
        return 1;
    }
}

$checks = 0;
$failed = 0;
$report = static function (bool $passed, string $label) use (&$checks, &$failed): void {
    $checks++;
    if (!$passed) {
        $failed++;
    }
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $label);
};
$reason = static function (callable $call): string {
    try {
        $call();
        return 'NONE';
    } catch (UserMfaEmailOtpException $exception) {
        return $exception->reason;
    }
};

$clock = 1_800_000_000;
$transactionId = str_repeat('a', 64);
$persistence = new U4Persistence();
$persistence->addTransaction($transactionId, 'USER1', 'session-1', 'Browser/1', $clock + 300);
$sender = new U4Sender();
$service = new UserMfaEmailOtpService(
    $persistence,
    $sender,
    new UserMfaRateLimitConfig(),
    static fn(): int => $clock
);

$request = $service->request(
    $transactionId,
    'USER1',
    'session-1',
    'Browser/1',
    '127.0.0.1',
    'ms'
);
$challenge = $persistence->challenges[$request['challenge_id']];
$report(
    $request['code'] === 'USER_MFA_CHALLENGE_SENT'
    && !array_key_exists('otp', $request)
    && str_contains($request['masked_email'], '@example.test')
    && !str_contains($request['masked_email'], 'pilot@'),
    'request returns opaque challenge metadata and masked destination'
);
$report(
    $sender->otp !== ''
    && $challenge['otp_hash'] !== $sender->otp
    && password_verify($sender->otp, $challenge['otp_hash'])
    && !array_key_exists('email', $challenge),
    'raw OTP reaches sender only while persistence is hash and destination-HMAC only'
);

$firstChallengeId = $request['challenge_id'];
$firstOtp = $sender->otp;
$second = $service->request(
    $transactionId,
    'USER1',
    'session-1',
    'Browser/1',
    '127.0.0.1',
    'en'
);
$report(
    $persistence->challenges[$firstChallengeId]['revoked_at'] !== null
    && $persistence->challenges[$firstChallengeId]['otp_hash'] === null
    && $second['challenge_id'] !== $firstChallengeId
    && $sender->otp !== $firstOtp,
    'resend revokes prior challenge and erases its OTP hash'
);

$wrongOtp = $sender->otp === '000000' ? '000001' : '000000';
$report(
    $reason(fn() => $service->verify(
        $transactionId,
        $second['challenge_id'],
        $wrongOtp,
        'session-1',
        'Browser/1',
        '127.0.0.1'
    )) === 'USER_MFA_VERIFICATION_FAILED'
    && $persistence->challenges[$second['challenge_id']]['attempts'] === 1
    && $persistence->transactions[$transactionId]['transaction_status'] === 'PENDING',
    'wrong OTP increments attempt without verifying pending login'
);

$report(
    $reason(fn() => $service->verify(
        $transactionId,
        $second['challenge_id'],
        $sender->otp,
        'wrong-session',
        'Browser/1',
        '127.0.0.1'
    )) === 'USER_MFA_BINDING_MISMATCH'
    && $persistence->transactions[$transactionId]['transaction_status'] === 'PENDING',
    'cross-session verification is rejected'
);

$verified = $service->verify(
    $transactionId,
    $second['challenge_id'],
    $sender->otp,
    'session-1',
    'Browser/1',
    '127.0.0.1'
);
$report(
    $verified['code'] === 'USER_MFA_FACTOR_VERIFIED'
    && $persistence->transactions[$transactionId]['transaction_status'] === 'VERIFIED'
    && $persistence->transactions[$transactionId]['verified_factor'] === 'EMAIL_OTP'
    && $persistence->challenges[$second['challenge_id']]['otp_hash'] === null,
    'valid OTP atomically consumes challenge and verifies pending login'
);
$report(
    $reason(fn() => $service->verify(
        $transactionId,
        $second['challenge_id'],
        $sender->otp,
        'session-1',
        'Browser/1',
        '127.0.0.1'
    )) === 'USER_MFA_CHALLENGE_REPLAYED',
    'consumed challenge cannot be replayed'
);

$limitedPersistence = new U4Persistence();
$limitedPersistence->addTransaction(
    str_repeat('b', 64),
    'USER2',
    'session-2',
    'Browser/2',
    $clock + 300
);
$limitedPersistence->stats['user_hour'] = 10;
$limitedSender = new U4Sender();
$limitedService = new UserMfaEmailOtpService(
    $limitedPersistence,
    $limitedSender,
    new UserMfaRateLimitConfig(),
    static fn(): int => $clock
);
$report(
    $reason(fn() => $limitedService->request(
        str_repeat('b', 64),
        'USER2',
        'session-2',
        'Browser/2',
        '127.0.0.2'
    )) === 'USER_MFA_RATE_LIMITED'
    && $limitedPersistence->challenges === []
    && $limitedSender->calls === 0,
    'rate limit rejects before challenge creation or delivery'
);
$limitedPersistence->stats['user_hour'] = 0;
$limitedPersistence->stats['cooldown_seconds'] = 60;
$report(
    $reason(fn() => $limitedService->request(
        str_repeat('b', 64),
        'USER2',
        'session-2',
        'Browser/2',
        '127.0.0.2'
    )) === 'USER_MFA_RESEND_COOLDOWN'
    && $limitedPersistence->challenges === [],
    'cooldown rejects before challenge mutation'
);

$deliveryPersistence = new U4Persistence();
$deliveryPersistence->addTransaction(
    str_repeat('c', 64),
    'USER3',
    'session-3',
    'Browser/3',
    $clock + 300
);
$deliverySender = new U4Sender();
$deliverySender->succeeds = false;
$deliveryService = new UserMfaEmailOtpService(
    $deliveryPersistence,
    $deliverySender,
    new UserMfaRateLimitConfig(),
    static fn(): int => $clock
);
$report(
    $reason(fn() => $deliveryService->request(
        str_repeat('c', 64),
        'USER3',
        'session-3',
        'Browser/3',
        '127.0.0.3'
    )) === 'USER_MFA_DELIVERY_FAILED'
    && array_values($deliveryPersistence->challenges)[0]['revoked_at'] === 'DELIVERY_FAILED'
    && array_values($deliveryPersistence->challenges)[0]['otp_hash'] === null,
    'delivery failure revokes challenge and erases OTP hash'
);

$expiredPersistence = new U4Persistence();
$expiredPersistence->addTransaction(
    str_repeat('d', 64),
    'USER4',
    'session-4',
    'Browser/4',
    $clock + 300
);
$expiredSender = new U4Sender();
$expiredService = new UserMfaEmailOtpService(
    $expiredPersistence,
    $expiredSender,
    new UserMfaRateLimitConfig(),
    static fn(): int => $clock
);
$expiredRequest = $expiredService->request(
    str_repeat('d', 64),
    'USER4',
    'session-4',
    'Browser/4',
    '127.0.0.4'
);
$expiredPersistence->challenges[$expiredRequest['challenge_id']]['expires_at_epoch'] = $clock - 1;
$report(
    $reason(fn() => $expiredService->verify(
        str_repeat('d', 64),
        $expiredRequest['challenge_id'],
        $expiredSender->otp,
        'session-4',
        'Browser/4',
        '127.0.0.4'
    )) === 'USER_MFA_CHALLENGE_EXPIRED'
    && $expiredPersistence->challenges[$expiredRequest['challenge_id']]['revoked_at'] === 'EXPIRED',
    'expired challenge is durably revoked'
);

$auditJson = json_encode([
    $persistence->audits,
    $limitedPersistence->audits,
    $deliveryPersistence->audits,
    $expiredPersistence->audits,
]);
$report(
    is_string($auditJson)
    && !str_contains($auditJson, $sender->otp)
    && !str_contains($auditJson, 'pilot@example.test')
    && !str_contains($auditJson, 'session-1'),
    'audit contains no raw OTP email or session'
);

printf(
    "RESULT checks=%d failures=%d emails_sent_to_fake=%d network_calls=0 live_database_mutations=0 runtime_activation=0 raw_otp_output=0\n",
    $checks,
    $failed,
    $sender->calls
);
exit($failed === 0 ? 0 : 1);
