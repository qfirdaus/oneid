<?php

declare(strict_types=1);

namespace OneId\App\Auth\UserMfa;

use InvalidArgumentException;

final class LegacyUserMfaAuditWriter implements UserMfaAuditWriterInterface
{
    private const EVENT_IDS = [
        'USER_MFA_PRIMARY_AUTH_PENDING' => 55,
        'USER_MFA_EMAIL_CHALLENGE' => 56,
        'USER_MFA_EMAIL_VERIFY' => 57,
        'USER_MFA_TOTP_VERIFY' => 58,
        'USER_MFA_LOGIN_COMPLETE' => 59,
        'USER_MFA_FACTOR_ENROLL' => 60,
        'USER_MFA_FACTOR_REVOKE' => 61,
        'USER_MFA_PREFERENCE_CHANGE' => 62,
        'USER_MFA_FACTOR_PREFERENCE' => 62,
        'USER_MFA_ADMIN_RECOVERY' => 63,
        'USER_MFA_POLICY_CHANGE' => 64,
        'USER_MFA_RETENTION_PURGE' => 65,
    ];

    public function __construct(private readonly object $operation)
    {
    }

    public function write(
        string $event,
        string $targetUserId,
        string $actorUserId,
        string $outcome,
        string $reason,
        string $reference,
        string $correlationId,
        string $ipAddress
    ): int {
        if (!isset(self::EVENT_IDS[$event])
            || !method_exists($this->operation, 'syslog_record')
            || filter_var($ipAddress, FILTER_VALIDATE_IP) === false
        ) {
            throw new InvalidArgumentException('USER_MFA_AUDIT_INPUT_INVALID');
        }
        $safe = static fn(string $value, int $limit): string => substr(
            preg_replace('/[^A-Za-z0-9_.:@ -]/', '', $value) ?? '',
            0,
            $limit
        );
        $detail = sprintf(
            'event=%s target=%s actor=%s outcome=%s reason=%s reference=%s correlation=%s',
            $event,
            $safe($targetUserId, 20),
            $safe($actorUserId, 20),
            $safe($outcome, 30),
            $safe($reason, 100),
            $safe($reference, 100),
            $safe($correlationId, 32)
        );
        return (int) $this->operation->syslog_record(
            self::EVENT_IDS[$event],
            $detail,
            $ipAddress
        );
    }
}
