<?php

declare(strict_types=1);

namespace OneId\App\Auth\UserMfa;

use OneId\App\Audit\AuditIdentityResolver;
use PDO;
use Throwable;

final class UserMfaTotpEmailRecoveryService
{
    private const PURPOSE = 'TOTP_RECOVERY';
    private const TTL_SECONDS = 300;
    private const MAX_ATTEMPTS = 5;
    private const COOLDOWN_SECONDS = 60;
    private const HOURLY_USER_LIMIT = 10;
    private const HOURLY_IP_LIMIT = 50;

    public function __construct(
        private readonly PDO $pdo,
        private readonly UserMfaRecoveryEmailSender $sender
    ) {
    }

    /** @return array<string,mixed> */
    public function request(
        string $userId,
        string $currentPassword,
        string $sessionId,
        string $userAgent,
        string $ipAddress,
        string $locale
    ): array {
        $correlation = bin2hex(random_bytes(16));
        $userId = $this->userId($userId, $correlation);
        $binding = UserMfaRequestBinding::fromRequest($sessionId, $userAgent, $ipAddress);
        $locale = in_array($locale, ['ms', 'en'], true) ? $locale : 'ms';
        $challengeId = bin2hex(random_bytes(32));
        $otp = UserMfaOtp::generate();
        $created = false;
        try {
            $this->pdo->beginTransaction();
            $context = $this->contextForUpdate($userId);
            if (!is_array($context)
                || (int) $context['u_type'] !== 0
                || (int) $context['avail_status'] !== 1
                || (int) $context['email_enabled'] !== 1
                || (int) $context['active_totp'] !== 1
                || filter_var((string) $context['email'], FILTER_VALIDATE_EMAIL) === false
            ) {
                throw $this->error('USER_MFA_RECOVERY_UNAVAILABLE', $correlation);
            }
            $passwordFailures = $this->pdo->prepare(
                "SELECT COUNT(*) FROM syslog
                  WHERE log_type=61 AND ip_addr=:ip
                    AND datetime>=DATE_SUB(NOW(),INTERVAL 15 MINUTE)
                    AND log_detail LIKE :detail"
            );
            $publicUserId = (new AuditIdentityResolver($this->pdo))->resolve($userId);
            $passwordFailures->execute([
                ':ip' => $ipAddress,
                ':detail' => 'event=USER_MFA_FACTOR_REVOKE target=' . $publicUserId
                    . ' actor=' . $publicUserId
                    . ' outcome=rejected reason=RECOVERY_PASSWORD_INVALID%',
            ]);
            if ((int) $passwordFailures->fetchColumn() >= 5) {
                throw $this->error('USER_MFA_RATE_LIMITED', $correlation);
            }
            if ($currentPassword === ''
                || !oneid_password_verify($currentPassword, (string) $context['u_password'])
            ) {
                $this->audit($userId, 'rejected', 'RECOVERY_PASSWORD_INVALID', $correlation, $ipAddress);
                $this->pdo->commit();
                throw $this->error('USER_MFA_RECOVERY_PASSWORD_INVALID', $correlation);
            }
            $email = trim((string) $context['email']);
            $destinationHmac = hash_hmac('sha256', strtolower($email), $binding['session_hash']);
            $stats = $this->requestStatsForUpdate(
                $userId,
                $binding['session_hash'],
                $ipAddress,
                $destinationHmac
            );
            if ((int) $stats['cooldown_seconds'] > 0) {
                throw $this->error('USER_MFA_RESEND_COOLDOWN', $correlation);
            }
            if ((int) $stats['user_hour'] >= self::HOURLY_USER_LIMIT
                || (int) $stats['session_hour'] >= self::HOURLY_USER_LIMIT
                || (int) $stats['destination_hour'] >= self::HOURLY_USER_LIMIT
                || (int) $stats['ip_hour'] >= self::HOURLY_IP_LIMIT
            ) {
                throw $this->error('USER_MFA_RATE_LIMITED', $correlation);
            }
            $revoke = $this->pdo->prepare(
                "UPDATE user_mfa_recovery_challenges
                    SET revoked_at=NOW(6),otp_hash=''
                  WHERE u_id=:user AND purpose=:purpose
                    AND consumed_at IS NULL AND revoked_at IS NULL"
            );
            $revoke->execute([':user' => $userId, ':purpose' => self::PURPOSE]);
            $insert = $this->pdo->prepare(
                'INSERT INTO user_mfa_recovery_challenges(
                    challenge_id,u_id,purpose,otp_hash,destination_hmac,
                    session_binding_hash,browser_digest,requesting_ip,
                    max_attempts,expires_at,correlation_id
                 ) VALUES(
                    :challenge,:user,:purpose,:otp_hash,:destination,
                    :session_hash,:browser_digest,:ip,:max_attempts,
                    DATE_ADD(NOW(6),INTERVAL 5 MINUTE),:correlation
                 )'
            );
            $insert->execute([
                ':challenge' => $challengeId,
                ':user' => $userId,
                ':purpose' => self::PURPOSE,
                ':otp_hash' => UserMfaOtp::hash($otp),
                ':destination' => $destinationHmac,
                ':session_hash' => $binding['session_hash'],
                ':browser_digest' => $binding['browser_digest'],
                ':ip' => $binding['ip_address'],
                ':max_attempts' => self::MAX_ATTEMPTS,
                ':correlation' => $correlation,
            ]);
            $this->audit($userId, 'requested', 'TOTP_RECOVERY_OTP_REQUESTED', $correlation, $ipAddress);
            $this->pdo->commit();
            $created = true;

            $sent = false;
            try {
                $sent = $this->sender->sendOtp(
                    $otp,
                    $email,
                    (string) $context['display_name'],
                    $locale
                );
            } finally {
                unset($otp);
            }
            if (!$sent) {
                $this->revokeChallenge($challengeId);
                throw $this->error('USER_MFA_DELIVERY_FAILED', $correlation);
            }
            $sentUpdate = $this->pdo->prepare(
                'UPDATE user_mfa_recovery_challenges SET sent_at=NOW(6)
                  WHERE challenge_id=:challenge AND sent_at IS NULL
                    AND consumed_at IS NULL AND revoked_at IS NULL'
            );
            $sentUpdate->execute([':challenge' => $challengeId]);
            if ($sentUpdate->rowCount() !== 1) {
                $this->revokeChallenge($challengeId);
                throw $this->error('USER_MFA_RECOVERY_CHALLENGE_INVALID', $correlation);
            }
            return [
                'status' => 1,
                'code' => 'USER_MFA_RECOVERY_OTP_SENT',
                'challenge_id' => $challengeId,
                'masked_email' => $this->maskEmail($email),
                'expires_in_seconds' => self::TTL_SECONDS,
                'resend_after_seconds' => self::COOLDOWN_SECONDS,
                'correlation_id' => $correlation,
            ];
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($created && !($exception instanceof UserMfaEmailOtpException
                && $exception->reason === 'USER_MFA_DELIVERY_FAILED')) {
                $this->revokeChallenge($challengeId);
            }
            if ($exception instanceof UserMfaEmailOtpException) {
                throw $exception;
            }
            error_log('User MFA recovery request failed correlation=' . $correlation);
            throw $this->error('USER_MFA_RECOVERY_REQUEST_FAILED', $correlation);
        }
    }

    /** @return array<string,mixed> */
    public function verifyAndRevoke(
        string $userId,
        string $challengeId,
        string $submittedOtp,
        string $sessionId,
        string $userAgent,
        string $ipAddress,
        string $locale
    ): array {
        $correlation = bin2hex(random_bytes(16));
        $userId = $this->userId($userId, $correlation);
        if (preg_match('/\A[a-f0-9]{64}\z/', $challengeId) !== 1) {
            throw $this->error('USER_MFA_RECOVERY_CHALLENGE_INVALID', $correlation);
        }
        $binding = UserMfaRequestBinding::fromRequest($sessionId, $userAgent, $ipAddress);
        $locale = in_array($locale, ['ms', 'en'], true) ? $locale : 'ms';
        $notice = null;
        try {
            $this->pdo->beginTransaction();
            $select = $this->pdo->prepare(
                'SELECT c.*,u.data5 email,u.data1 display_name
                   FROM user_mfa_recovery_challenges c
                   JOIN user_tbl u ON u.u_id=c.u_id
                  WHERE c.challenge_id=:challenge AND c.u_id=:user
                    AND c.purpose=:purpose
                  LIMIT 1 FOR UPDATE'
            );
            $select->execute([
                ':challenge' => $challengeId,
                ':user' => $userId,
                ':purpose' => self::PURPOSE,
            ]);
            $challenge = $select->fetch(PDO::FETCH_ASSOC);
            if (!is_array($challenge)
                || $challenge['sent_at'] === null
                || $challenge['consumed_at'] !== null
                || $challenge['revoked_at'] !== null
                || !hash_equals((string) $challenge['session_binding_hash'], $binding['session_hash'])
                || !hash_equals((string) $challenge['browser_digest'], $binding['browser_digest'])
            ) {
                throw $this->error('USER_MFA_RECOVERY_CHALLENGE_INVALID', $correlation);
            }
            if (strtotime((string) $challenge['expires_at']) < time()) {
                $this->terminalChallenge($challengeId, false);
                $this->audit($userId, 'expired', 'TOTP_RECOVERY_OTP_EXPIRED', $correlation, $ipAddress);
                $this->pdo->commit();
                throw $this->error('USER_MFA_CHALLENGE_EXPIRED', $correlation);
            }
            if (!UserMfaOtp::verify($submittedOtp, (string) $challenge['otp_hash'])) {
                $failed = $this->pdo->prepare(
                    'UPDATE user_mfa_recovery_challenges
                        SET attempts=attempts+1,
                            revoked_at=CASE WHEN attempts+1>=max_attempts THEN NOW(6) ELSE revoked_at END,
                            otp_hash=CASE WHEN attempts+1>=max_attempts THEN \'\' ELSE otp_hash END
                      WHERE challenge_id=:challenge AND attempts<max_attempts
                        AND consumed_at IS NULL AND revoked_at IS NULL'
                );
                $failed->execute([':challenge' => $challengeId]);
                $this->audit($userId, 'rejected', 'TOTP_RECOVERY_OTP_INVALID', $correlation, $ipAddress);
                $this->pdo->commit();
                throw $this->error('USER_MFA_VERIFICATION_FAILED', $correlation);
            }
            $this->terminalChallenge($challengeId, true);
            $factor = $this->pdo->prepare(
                "UPDATE user_mfa_factors
                    SET factor_status='REVOKED',revoked_at=NOW(6)
                  WHERE u_id=:user AND factor_type='TOTP'
                    AND factor_status IN('PENDING','ACTIVE')"
            );
            $factor->execute([':user' => $userId]);
            if ($factor->rowCount() < 1) {
                throw $this->error('USER_MFA_RECOVERY_FACTOR_UNAVAILABLE', $correlation);
            }
            $preference = $this->pdo->prepare(
                "INSERT INTO user_mfa_preferences(
                    u_id,preferred_factor,configuration_version,correlation_id
                 ) VALUES(:user,'EMAIL_OTP',1,:correlation)
                 ON DUPLICATE KEY UPDATE preferred_factor='EMAIL_OTP',
                    configuration_version=configuration_version+1,
                    correlation_id=VALUES(correlation_id)"
            );
            $preference->execute([':user' => $userId, ':correlation' => $correlation]);
            $pendingChallenges = $this->pdo->prepare(
                'UPDATE user_login_mfa_challenges
                    SET revoked_at=NOW(6),otp_hash=NULL
                  WHERE u_id=:user AND consumed_at IS NULL AND revoked_at IS NULL'
            );
            $pendingChallenges->execute([':user' => $userId]);
            $pendingTransactions = $this->pdo->prepare(
                "UPDATE user_login_mfa_transactions
                    SET transaction_status='REVOKED',revoked_at=NOW(6)
                  WHERE u_id=:user AND transaction_status IN('PENDING','VERIFIED')"
            );
            $pendingTransactions->execute([':user' => $userId]);
            $sessions = $this->pdo->prepare(
                'UPDATE token_tbl SET status=0 WHERE user_id=:user'
            );
            $sessions->execute([':user' => $userId]);
            $this->audit($userId, 'revoked', 'EMAIL_OTP_SELF_RECOVERY', $correlation, $ipAddress);
            $this->pdo->commit();
            $notice = [
                'email' => (string) $challenge['email'],
                'display_name' => (string) $challenge['display_name'],
            ];
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($exception instanceof UserMfaEmailOtpException) {
                throw $exception;
            }
            error_log('User MFA recovery verify failed correlation=' . $correlation);
            throw $this->error('USER_MFA_RECOVERY_VERIFY_FAILED', $correlation);
        }
        $noticeSent = $this->sender->sendRevokedNotice(
            (string) $notice['email'],
            (string) $notice['display_name'],
            $locale
        );
        return [
            'status' => 1,
            'code' => 'USER_MFA_TOTP_REVOKED',
            'reauthentication_required' => true,
            'notification_sent' => $noticeSent,
            'correlation_id' => $correlation,
        ];
    }

    private function contextForUpdate(string $userId): array|false
    {
        $statement = $this->pdo->prepare(
            "SELECT u.u_id,u.u_type,u.avail_status,u.u_password,
                    u.data5 email,u.data1 display_name,p.email_enabled,
                    EXISTS(
                      SELECT 1 FROM user_mfa_factors f
                       WHERE f.u_id=u.u_id AND f.factor_type='TOTP'
                         AND f.factor_status='ACTIVE'
                    ) active_totp
               FROM user_tbl u
               JOIN user_login_mfa_policy p ON p.singleton_key=1
              WHERE u.u_id=:user LIMIT 1 FOR UPDATE"
        );
        $statement->execute([':user' => $userId]);
        return $statement->fetch(PDO::FETCH_ASSOC);
    }

    /** @return array<string,int> */
    private function requestStatsForUpdate(
        string $userId,
        string $sessionHash,
        string $ip,
        string $destination
    ): array {
        $this->pdo->query(
            'SELECT singleton_key FROM user_login_mfa_policy
              WHERE singleton_key=1 FOR UPDATE'
        )->fetchColumn();
        $statement = $this->pdo->prepare(
            'SELECT
                SUM(u_id=:user) user_hour,
                SUM(session_binding_hash=:session_hash) session_hour,
                SUM(requesting_ip=:ip) ip_hour,
                SUM(destination_hmac=:destination) destination_hour,
                GREATEST(0,60-TIMESTAMPDIFF(
                  SECOND,MAX(CASE WHEN destination_hmac=:destination_2
                                  THEN created_at END),NOW()
                )) cooldown_seconds
               FROM user_mfa_recovery_challenges
              WHERE created_at>=DATE_SUB(NOW(),INTERVAL 1 HOUR)'
        );
        $statement->execute([
            ':user' => $userId,
            ':session_hash' => $sessionHash,
            ':ip' => $ip,
            ':destination' => $destination,
            ':destination_2' => $destination,
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC) ?: [];
        return array_map('intval', $row);
    }

    private function terminalChallenge(string $challengeId, bool $consumed): void
    {
        $sql = $consumed
            ? 'UPDATE user_mfa_recovery_challenges
                  SET consumed_at=NOW(6),otp_hash=\'\'
                WHERE challenge_id=:challenge AND consumed_at IS NULL AND revoked_at IS NULL'
            : 'UPDATE user_mfa_recovery_challenges
                  SET revoked_at=NOW(6),otp_hash=\'\'
                WHERE challenge_id=:challenge AND consumed_at IS NULL AND revoked_at IS NULL';
        $statement = $this->pdo->prepare($sql);
        $statement->execute([':challenge' => $challengeId]);
        if ($statement->rowCount() !== 1) {
            throw $this->error('USER_MFA_RECOVERY_CHALLENGE_INVALID');
        }
    }

    private function revokeChallenge(string $challengeId): void
    {
        $statement = $this->pdo->prepare(
            "UPDATE user_mfa_recovery_challenges
                SET revoked_at=COALESCE(revoked_at,NOW(6)),otp_hash=''
              WHERE challenge_id=:challenge AND consumed_at IS NULL"
        );
        $statement->execute([':challenge' => $challengeId]);
    }

    private function audit(
        string $userId,
        string $outcome,
        string $reason,
        string $correlation,
        string $ip
    ): void {
        $publicUserId = (new AuditIdentityResolver($this->pdo))->resolve($userId);
        $detail = sprintf(
            'event=USER_MFA_FACTOR_REVOKE target=%s actor=%s outcome=%s reason=%s reference= correlation=%s',
            $publicUserId,
            $publicUserId,
            $outcome,
            $reason,
            $correlation
        );
        $statement = $this->pdo->prepare(
            'INSERT INTO syslog(log_type,log_detail,ip_addr,datetime)
             VALUES(61,:detail,:ip,NOW())'
        );
        $statement->execute([':detail' => $detail, ':ip' => $ip]);
        if ($statement->rowCount() !== 1) {
            throw $this->error('USER_MFA_RECOVERY_AUDIT_FAILED', $correlation);
        }
    }

    private function userId(string $value, string $correlation): string
    {
        $value = trim($value);
        if (preg_match('/\A[A-Za-z0-9_.@-]{1,20}\z/', $value) !== 1) {
            throw $this->error('USER_MFA_RECOVERY_CONTEXT_INVALID', $correlation);
        }
        return $value;
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
        return substr($local, 0, min(2, strlen($local)))
            . str_repeat('*', max(2, strlen($local) - 2)) . '@' . $domain;
    }

    private function error(string $reason, ?string $correlation = null): UserMfaEmailOtpException
    {
        return new UserMfaEmailOtpException(
            $reason,
            $correlation ?? bin2hex(random_bytes(16))
        );
    }
}
