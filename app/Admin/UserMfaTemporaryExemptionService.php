<?php

declare(strict_types=1);

namespace OneId\App\Admin;

use PDO;
use Throwable;

final class UserMfaTemporaryExemptionService
{
    private const DURATIONS = [1, 4, 8, 24, 72];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function search(string $query = ''): array
    {
        $query = trim($query);
        if (strlen($query) > 100 || preg_match('/[\x00-\x1F\x7F]/', $query) === 1) {
            throw $this->error('USER_MFA_EXEMPTION_SEARCH_INVALID');
        }
        $sql = "SELECT e.exemption_id,e.u_id,
                       CASE WHEN e.exemption_status='ACTIVE' AND e.expires_at<=NOW(6)
                            THEN 'EXPIRED' ELSE e.exemption_status END exemption_status,
                       e.starts_at,e.expires_at,
                       e.approved_by,e.change_reason,e.change_reference,
                       e.compensating_control,e.revoked_by,e.revoked_at,e.revoke_reason,
                       e.correlation_id,e.created_at,u.data1 display_name
                  FROM user_login_mfa_exemptions e
                  JOIN user_tbl u ON u.u_id=e.u_id";
        $params = [];
        if ($query !== '') {
            $sql .= ' WHERE e.u_id LIKE :query OR u.data1 LIKE :query
                       OR e.change_reference LIKE :query';
            $params[':query'] = '%' . $query . '%';
        }
        $sql .= " ORDER BY (e.exemption_status='ACTIVE') DESC,e.created_at DESC LIMIT 100";
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $now = time();
        foreach ($rows as &$row) {
            $row['exemption_id'] = (int) $row['exemption_id'];
            $row['expires_soon'] = $row['exemption_status'] === 'ACTIVE'
                && strtotime((string) $row['expires_at']) <= $now + 14400;
        }
        return ['status' => 1, 'code' => 'USER_MFA_EXEMPTIONS_LOADED', 'data' => $rows];
    }

    public function create(
        string $userId,
        mixed $durationValue,
        string $reason,
        string $reference,
        string $control,
        string $typed,
        string $admin,
        string $ip
    ): array {
        $userId = trim($userId);
        $duration = filter_var($durationValue, FILTER_VALIDATE_INT);
        $this->validateCommon($userId, $reason, $reference, $admin, $ip);
        $control = trim($control);
        if (!in_array($duration, self::DURATIONS, true)
            || strlen($control) < 10 || strlen($control) > 500
            || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $control) === 1
            || !hash_equals('ADD USER 2FA EXEMPTION ' . $userId, trim($typed))
        ) {
            throw $this->error('USER_MFA_EXEMPTION_INPUT_INVALID');
        }
        $correlation = bin2hex(random_bytes(16));
        try {
            $this->pdo->beginTransaction();
            $user = $this->pdo->prepare(
                'SELECT u_id,u_type,avail_status FROM user_tbl WHERE u_id=:user FOR UPDATE'
            );
            $user->execute([':user' => $userId]);
            $account = $user->fetch(PDO::FETCH_ASSOC);
            if (!is_array($account) || (int) $account['avail_status'] !== 1) {
                throw $this->error('USER_MFA_EXEMPTION_USER_INELIGIBLE', $correlation);
            }
            if ((int) $account['u_type'] === 1) {
                throw $this->error('USER_MFA_EXEMPTION_ADMIN_FORBIDDEN', $correlation);
            }
            $expired = $this->expireDue($userId);
            if ($expired > 0) {
                $this->audit(
                    $admin, $userId, 'auto_expire', $reference, $correlation,
                    $ip, 0, 0
                );
            }
            $active = $this->pdo->prepare(
                "SELECT exemption_id FROM user_login_mfa_exemptions
                  WHERE u_id=:user AND exemption_status='ACTIVE' FOR UPDATE"
            );
            $active->execute([':user' => $userId]);
            if ($active->fetchColumn() !== false) {
                throw $this->error('USER_MFA_EXEMPTION_ALREADY_ACTIVE', $correlation);
            }
            $insert = $this->pdo->prepare(
                "INSERT INTO user_login_mfa_exemptions(
                    u_id,exemption_status,starts_at,expires_at,approved_by,
                    change_reason,change_reference,compensating_control,correlation_id
                 ) VALUES(:user,'ACTIVE',NOW(6),DATE_ADD(NOW(6),INTERVAL :hours HOUR),
                    :admin,:reason,:reference,:control,:correlation)"
            );
            $insert->execute([
                ':user' => $userId, ':hours' => $duration, ':admin' => $admin,
                ':reason' => trim($reason), ':reference' => trim($reference),
                ':control' => $control, ':correlation' => $correlation,
            ]);
            $exemptionId = (int) $this->pdo->lastInsertId();
            $pendingChallenges = $this->pdo->prepare(
                'UPDATE user_login_mfa_challenges
                    SET revoked_at=NOW(6),otp_hash=NULL
                  WHERE u_id=:user AND consumed_at IS NULL AND revoked_at IS NULL'
            );
            $pendingChallenges->execute([':user' => $userId]);
            $pendingTransactions = $this->pdo->prepare(
                "UPDATE user_login_mfa_transactions
                    SET transaction_status='REVOKED',revoked_at=NOW(6)
                  WHERE u_id=:user AND transaction_status IN ('PENDING','VERIFIED')"
            );
            $pendingTransactions->execute([':user' => $userId]);
            $this->audit(
                $admin, $userId, 'create', $reference, $correlation, $ip,
                $pendingTransactions->rowCount(), $pendingChallenges->rowCount()
            );
            $this->pdo->commit();
            return [
                'status' => 1, 'code' => 'USER_MFA_EXEMPTION_CREATED',
                'exemption_id' => $exemptionId,
                'correlation_id' => $correlation,
            ];
        } catch (SsoConfigurationException $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        } catch (Throwable) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('User MFA exemption create failed correlation=' . $correlation);
            throw $this->error('USER_MFA_EXEMPTION_CREATE_FAILED', $correlation);
        }
    }

    public function revoke(
        mixed $exemptionIdValue,
        string $reason,
        string $typed,
        string $admin,
        string $ip
    ): array {
        $id = filter_var($exemptionIdValue, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $reason = trim($reason);
        if ($id === false || strlen($reason) < 10 || strlen($reason) > 500
            || !hash_equals('REVOKE USER 2FA EXEMPTION ' . (string) $id, trim($typed))
            || preg_match('/\A[A-Za-z0-9._@-]{1,20}\z/', $admin) !== 1
            || filter_var($ip, FILTER_VALIDATE_IP) === false
        ) {
            throw $this->error('USER_MFA_EXEMPTION_REVOKE_INPUT_INVALID');
        }
        $correlation = bin2hex(random_bytes(16));
        try {
            $this->pdo->beginTransaction();
            $select = $this->pdo->prepare(
                'SELECT * FROM user_login_mfa_exemptions
                  WHERE exemption_id=:id FOR UPDATE'
            );
            $select->execute([':id' => $id]);
            $row = $select->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row) || $row['exemption_status'] !== 'ACTIVE'
                || strtotime((string) $row['expires_at']) <= time()
            ) {
                throw $this->error('USER_MFA_EXEMPTION_NOT_ACTIVE', $correlation);
            }
            $update = $this->pdo->prepare(
                "UPDATE user_login_mfa_exemptions
                    SET exemption_status='REVOKED',revoked_by=:admin,
                        revoked_at=NOW(6),revoke_reason=:reason
                  WHERE exemption_id=:id AND exemption_status='ACTIVE'"
            );
            $update->execute([':admin' => $admin, ':reason' => $reason, ':id' => $id]);
            $this->audit(
                $admin, (string) $row['u_id'], 'revoke',
                (string) $row['change_reference'], $correlation, $ip, 0, 0
            );
            if ($update->rowCount() !== 1) {
                throw $this->error('USER_MFA_EXEMPTION_REVOKE_FAILED', $correlation);
            }
            $this->pdo->commit();
            return [
                'status' => 1, 'code' => 'USER_MFA_EXEMPTION_REVOKED',
                'correlation_id' => $correlation,
            ];
        } catch (SsoConfigurationException $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        } catch (Throwable) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $this->error('USER_MFA_EXEMPTION_REVOKE_FAILED', $correlation);
        }
    }

    private function expireDue(?string $userId = null): int
    {
        $sql = "UPDATE user_login_mfa_exemptions
                   SET exemption_status='EXPIRED'
                 WHERE exemption_status='ACTIVE' AND expires_at<=NOW(6)";
        $params = [];
        if ($userId !== null) {
            $sql .= ' AND u_id=:user';
            $params[':user'] = $userId;
        }
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        return $statement->rowCount();
    }

    private function validateCommon(
        string $userId,
        string $reason,
        string $reference,
        string $admin,
        string $ip
    ): void {
        $reason = trim($reason);
        $reference = trim($reference);
        if (preg_match('/\A[A-Za-z0-9_.@-]{1,20}\z/', $userId) !== 1
            || strlen($reason) < 10 || strlen($reason) > 500
            || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $reason) === 1
            || preg_match('/\A[A-Za-z0-9._-]{8,100}\z/', $reference) !== 1
            || preg_match('/\A[A-Za-z0-9._@-]{1,20}\z/', $admin) !== 1
            || filter_var($ip, FILTER_VALIDATE_IP) === false
        ) {
            throw $this->error('USER_MFA_EXEMPTION_INPUT_INVALID');
        }
    }

    private function audit(
        string $admin,
        string $user,
        string $action,
        string $reference,
        string $correlation,
        string $ip,
        int $transactions,
        int $challenges
    ): void {
        $detail = sprintf(
            'admin=%s action=user_mfa_exemption_%s user=%s reference=%s pending_transactions=%d pending_challenges=%d correlation=%s',
            $admin, $action, $user, $reference, $transactions, $challenges, $correlation
        );
        $audit = $this->pdo->prepare(
            'INSERT INTO syslog(log_type,log_detail,ip_addr,datetime)
             VALUES(64,:detail,:ip,NOW())'
        );
        $audit->execute([':detail' => $detail, ':ip' => $ip]);
        if ($audit->rowCount() !== 1) {
            throw $this->error('USER_MFA_EXEMPTION_AUDIT_FAILED', $correlation);
        }
    }

    private function error(string $reason, ?string $correlation = null): SsoConfigurationException
    {
        return new SsoConfigurationException($reason, $correlation ?? bin2hex(random_bytes(8)));
    }
}
