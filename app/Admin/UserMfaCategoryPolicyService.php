<?php

declare(strict_types=1);

namespace OneId\App\Admin;

use PDO;
use Throwable;

final class UserMfaCategoryPolicyService
{
    private const CATEGORIES = ['STAFF','STUDENT'];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function read(): array
    {
        $rows = $this->pdo->query(
            "SELECT category_code,enforcement_enabled,configuration_version,
                    change_reference,updated_at
               FROM user_login_mfa_category_policy
              WHERE category_code IN ('STAFF','STUDENT')
              ORDER BY category_code"
        )->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) !== 2) {
            throw new SsoConfigurationException(
                'USER_MFA_CATEGORY_POLICY_UNAVAILABLE',
                bin2hex(random_bytes(8))
            );
        }
        $counts = ['STAFF' => 0, 'STUDENT' => 0];
        foreach ($this->pdo->query(
            "SELECT UPPER(s.source_family) category_code,COUNT(DISTINCT i.u_id) users
               FROM user_external_identity i
               JOIN external_source s ON s.source_code=i.source_code
              WHERE i.source_active=1 AND s.source_family IN ('staff','student')
              GROUP BY s.source_family"
        ) as $row) {
            $counts[(string) $row['category_code']] = (int) $row['users'];
        }
        $data = [];
        foreach ($rows as $row) {
            $category = (string) $row['category_code'];
            $data[$category] = [
                'enabled' => (int) $row['enforcement_enabled'] === 1,
                'configuration_version' => (int) $row['configuration_version'],
                'users' => $counts[$category],
                'change_reference' => $row['change_reference'],
                'updated_at' => (string) $row['updated_at'],
            ];
        }
        return ['status' => 1, 'code' => 'USER_MFA_CATEGORY_POLICY_LOADED', 'data' => $data];
    }

    public function update(
        string $category,
        mixed $enabledValue,
        mixed $versionValue,
        string $reason,
        string $reference,
        string $typed,
        string $admin,
        string $ip
    ): array {
        $category = strtoupper(trim($category));
        $enabled = $this->flag($enabledValue);
        $version = filter_var($versionValue, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $reason = trim($reason);
        $reference = trim($reference);
        $expected = ($enabled ? 'ENABLE USER 2FA ' : 'DISABLE USER 2FA ') . $category;
        if (!in_array($category, self::CATEGORIES, true)
            || $version === false
            || strlen($reason) < 10 || strlen($reason) > 500
            || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $reason) === 1
            || preg_match('/\A[A-Za-z0-9._-]{8,100}\z/', $reference) !== 1
            || !hash_equals($expected, $typed)
            || preg_match('/\A[A-Za-z0-9._@-]{1,20}\z/', $admin) !== 1
            || filter_var($ip, FILTER_VALIDATE_IP) === false
        ) {
            throw new SsoConfigurationException(
                'USER_MFA_CATEGORY_POLICY_INPUT_INVALID',
                bin2hex(random_bytes(8))
            );
        }
        $correlation = bin2hex(random_bytes(16));
        try {
            $this->pdo->beginTransaction();
            $select = $this->pdo->prepare(
                'SELECT * FROM user_login_mfa_category_policy
                  WHERE category_code=:category FOR UPDATE'
            );
            $select->execute([':category' => $category]);
            $before = $select->fetch(PDO::FETCH_ASSOC);
            if (!is_array($before) || (int) $before['configuration_version'] !== (int) $version) {
                throw new SsoConfigurationException('USER_MFA_CATEGORY_POLICY_STALE', $correlation);
            }
            if (((int) $before['enforcement_enabled'] === 1) === $enabled) {
                $this->pdo->commit();
                return $this->read() + ['changed' => false, 'correlation_id' => $correlation];
            }
            $next = (int) $version + 1;
            $update = $this->pdo->prepare(
                'UPDATE user_login_mfa_category_policy
                    SET enforcement_enabled=:enabled,configuration_version=:next,
                        change_reference=:reference,updated_by=:admin
                  WHERE category_code=:category AND configuration_version=:version'
            );
            $update->execute([
                ':enabled' => $enabled ? 1 : 0, ':next' => $next,
                ':reference' => $reference, ':admin' => $admin,
                ':category' => $category, ':version' => (int) $version,
            ]);
            $history = $this->pdo->prepare(
                'INSERT INTO user_login_mfa_category_policy_history(
                    category_code,configuration_version,previous_enabled,
                    resulting_enabled,changed_by,change_reason,change_reference,
                    correlation_id
                 ) VALUES(:category,:version,:before,:after,:admin,:reason,:reference,:correlation)'
            );
            $history->execute([
                ':category' => $category, ':version' => $next,
                ':before' => (int) $before['enforcement_enabled'],
                ':after' => $enabled ? 1 : 0, ':admin' => $admin,
                ':reason' => $reason, ':reference' => $reference,
                ':correlation' => $correlation,
            ]);
            $detail = sprintf(
                'admin=%s action=user_mfa_category_policy category=%s from=%d to=%d reference=%s correlation=%s',
                $admin, $category, (int) $before['enforcement_enabled'],
                $enabled ? 1 : 0, $reference, $correlation
            );
            $audit = $this->pdo->prepare(
                'INSERT INTO syslog(log_type,log_detail,ip_addr,datetime)
                 VALUES(64,:detail,:ip,NOW())'
            );
            $audit->execute([':detail' => $detail, ':ip' => $ip]);
            if ($update->rowCount() !== 1 || $history->rowCount() !== 1 || $audit->rowCount() !== 1) {
                throw new SsoConfigurationException('USER_MFA_CATEGORY_POLICY_AUDIT_FAILED', $correlation);
            }
            $this->pdo->commit();
            return [
                'status' => 1,
                'code' => 'USER_MFA_CATEGORY_POLICY_UPDATED',
                'changed' => true,
                'data' => [
                    'category' => $category,
                    'enabled' => $enabled,
                    'configuration_version' => $next,
                ],
                'correlation_id' => $correlation,
            ];
        } catch (SsoConfigurationException $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('User MFA category policy failed correlation=' . $correlation);
            throw new SsoConfigurationException('USER_MFA_CATEGORY_POLICY_FAILED', $correlation);
        }
    }

    private function flag(mixed $value): bool
    {
        if (!is_scalar($value) || !in_array(trim((string) $value), ['0','1'], true)) {
            throw new SsoConfigurationException(
                'USER_MFA_CATEGORY_POLICY_FLAG_INVALID',
                bin2hex(random_bytes(8))
            );
        }
        return trim((string) $value) === '1';
    }
}
