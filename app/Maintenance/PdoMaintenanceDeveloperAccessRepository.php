<?php

declare(strict_types=1);

namespace OneId\App\Maintenance;

use PDO;
use Throwable;

final class PdoMaintenanceDeveloperAccessRepository implements MaintenanceDeveloperAccessRepositoryInterface
{
    private const TABLES = [
        'maintenance_developer_access_grants',
        'maintenance_developer_access_history',
    ];

    public function __construct(private readonly PDO $pdo)
    {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    public function schemaStatus(): array
    {
        $statement = $this->pdo->prepare(
            'SELECT TABLE_NAME FROM information_schema.TABLES
              WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN (?,?)'
        );
        $statement->execute(self::TABLES);
        $tables = array_fill_keys(self::TABLES, false);
        foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $table) {
            $tables[(string) $table] = true;
        }
        return ['available' => !in_array(false, $tables, true), 'tables' => $tables];
    }

    public function transactional(callable $operation): mixed
    {
        if ($this->pdo->inTransaction()) {
            throw new MaintenanceDeveloperAccessException('MAINTENANCE_ACCESS_TRANSACTION_ACTIVE');
        }
        $this->pdo->beginTransaction();
        try {
            $result = $operation($this);
            $this->pdo->commit();
            return $result;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function searchCandidates(string $query): array
    {
        $statement = $this->pdo->prepare(
            "SELECT u.u_id,u.data1 display_name,u.data3 staff_number,
                    CASE
                      WHEN u.u_type<>0 THEN 'USER_TYPE_FORBIDDEN'
                      WHEN u.avail_status<>1 THEN 'ACCOUNT_INACTIVE'
                      WHEN EXISTS(
                        SELECT 1 FROM maintenance_developer_access_grants g
                         WHERE g.u_id=u.u_id AND g.grant_status='ACTIVE'
                      ) THEN 'GRANT_ALREADY_ACTIVE'
                      ELSE 'ELIGIBLE'
                    END eligibility
               FROM user_tbl u
              WHERE u.u_id LIKE :query_id OR u.data1 LIKE :query_name
                 OR u.data3 LIKE :query_identity OR u.data4 LIKE :query_alternate
              ORDER BY (u.u_id=:exact) DESC,u.data1,u.u_id LIMIT 10"
        );
        $like = '%' . $query . '%';
        $statement->execute([
            ':query_id' => $like, ':query_name' => $like,
            ':query_identity' => $like, ':query_alternate' => $like,
            ':exact' => $query,
        ]);
        return $statement->fetchAll();
    }

    public function listGrants(string $query): array
    {
        $sql = "SELECT g.grant_id,g.u_id,u.data1 display_name,u.data3 staff_number,g.grant_status,
                       g.valid_from,g.valid_until,g.approved_by,g.change_reason,
                       g.change_reference,g.configuration_version,g.revoked_by,
                       g.revoked_at,g.revoke_reason,g.created_at,g.updated_at
                  FROM maintenance_developer_access_grants g
                  JOIN user_tbl u ON u.u_id=g.u_id";
        $params = [];
        if ($query !== '') {
            $sql .= ' WHERE g.u_id LIKE :query_user OR u.data1 LIKE :query_name
                       OR g.change_reference LIKE :query_reference';
            $like = '%' . $query . '%';
            $params = [':query_user' => $like, ':query_name' => $like, ':query_reference' => $like];
        }
        $sql .= " ORDER BY (g.grant_status='ACTIVE') DESC,g.created_at DESC LIMIT 100";
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll();
    }

    public function account(string $userId, bool $forUpdate = false): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT u_id,u_type,avail_status FROM user_tbl WHERE u_id=:user LIMIT 1'
            . ($forUpdate ? ' FOR UPDATE' : '')
        );
        $statement->execute([':user' => $userId]);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    public function activeGrant(string $userId, bool $forUpdate = false): ?array
    {
        $statement = $this->pdo->prepare(
            "SELECT grant_id,u_id,grant_status,valid_from,valid_until,approved_by,
                    change_reason,change_reference,configuration_version,
                    correlation_id,revoked_by,revoked_at,revoke_reason,created_at,updated_at
               FROM maintenance_developer_access_grants
              WHERE u_id=:user AND grant_status='ACTIVE' LIMIT 1"
            . ($forUpdate ? ' FOR UPDATE' : '')
        );
        $statement->execute([':user' => $userId]);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    public function grant(int $grantId, bool $forUpdate = false): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT grant_id,u_id,grant_status,valid_from,valid_until,approved_by,
                    change_reason,change_reference,configuration_version,
                    correlation_id,revoked_by,revoked_at,revoke_reason,created_at,updated_at
               FROM maintenance_developer_access_grants WHERE grant_id=:grant LIMIT 1'
            . ($forUpdate ? ' FOR UPDATE' : '')
        );
        $statement->execute([':grant' => $grantId]);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    public function insertGrant(array $grant): int
    {
        $statement = $this->pdo->prepare(
            "INSERT INTO maintenance_developer_access_grants(
                u_id,grant_status,valid_from,valid_until,approved_by,
                change_reason,change_reference,configuration_version,correlation_id
             ) VALUES(:user,'ACTIVE',:valid_from,:valid_until,:approved_by,
                      :reason,:reference,1,:correlation)"
        );
        $statement->execute([
            ':user' => $grant['u_id'],
            ':valid_from' => $grant['valid_from'],
            ':valid_until' => $grant['valid_until'],
            ':approved_by' => $grant['approved_by'],
            ':reason' => $grant['change_reason'],
            ':reference' => $grant['change_reference'],
            ':correlation' => $grant['correlation_id'],
        ]);
        $id = (int) $this->pdo->lastInsertId();
        if ($statement->rowCount() !== 1 || $id < 1) {
            throw new MaintenanceDeveloperAccessException('MAINTENANCE_ACCESS_GRANT_NOT_INSERTED');
        }
        return $id;
    }

    public function revokeGrantVersioned(
        int $grantId,
        int $expectedVersion,
        string $revokedBy,
        string $revokedAtUtc,
        string $reason
    ): int {
        $statement = $this->pdo->prepare(
            "UPDATE maintenance_developer_access_grants
                SET grant_status='REVOKED',revoked_by=:actor,revoked_at=:revoked_at,
                    revoke_reason=:reason,configuration_version=configuration_version+1,
                    updated_at=:revoked_at
              WHERE grant_id=:grant AND grant_status='ACTIVE'
                AND configuration_version=:version"
        );
        $statement->execute([
            ':actor' => $revokedBy,
            ':revoked_at' => $revokedAtUtc,
            ':reason' => $reason,
            ':grant' => $grantId,
            ':version' => $expectedVersion,
        ]);
        return $statement->rowCount();
    }

    public function expireGrantVersioned(int $grantId, int $expectedVersion, string $expiredAtUtc): int
    {
        $statement = $this->pdo->prepare(
            "UPDATE maintenance_developer_access_grants
                SET grant_status='EXPIRED',configuration_version=configuration_version+1,
                    updated_at=:expired_at
              WHERE grant_id=:grant AND grant_status='ACTIVE'
                AND valid_until<=:effective_at AND configuration_version=:version"
        );
        $statement->execute([
            ':expired_at' => $expiredAtUtc,
            ':effective_at' => $expiredAtUtc,
            ':grant' => $grantId,
            ':version' => $expectedVersion,
        ]);
        return $statement->rowCount();
    }

    public function recordHistory(array $event): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO maintenance_developer_access_history(
                grant_id,u_id,action_name,actor_user_id,
                configuration_version_before,configuration_version_after,
                change_reason,change_reference,correlation_id,source_ip
             ) VALUES(:grant,:user,:action,:actor,:before_version,:after_version,
                      :reason,:reference,:correlation,:ip)'
        );
        $statement->execute([
            ':grant' => $event['grant_id'],
            ':user' => $event['u_id'],
            ':action' => $event['action_name'],
            ':actor' => $event['actor_user_id'],
            ':before_version' => $event['configuration_version_before'],
            ':after_version' => $event['configuration_version_after'],
            ':reason' => $event['change_reason'],
            ':reference' => $event['change_reference'],
            ':correlation' => $event['correlation_id'],
            ':ip' => $event['source_ip'],
        ]);
        return $statement->rowCount();
    }
}
