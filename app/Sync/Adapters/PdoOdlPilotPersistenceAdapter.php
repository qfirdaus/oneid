<?php
declare(strict_types=1);
namespace OneId\App\Sync\Adapters;

use OneId\App\Sync\Contracts\OdlPilotPersistenceInterface;
use PDO;

final class PdoOdlPilotPersistenceAdapter implements OdlPilotPersistenceInterface
{
    private const LOCK_NAME = 'oneid:odl:pilot';

    public function __construct(private readonly PDO $pdo) {}

    public function acquireLock(): bool
    {
        $query = $this->pdo->prepare('SELECT GET_LOCK(:name,0)');
        $query->execute([':name' => self::LOCK_NAME]);
        return (int) $query->fetchColumn() === 1;
    }

    public function releaseLock(): void
    {
        $query = $this->pdo->prepare('SELECT RELEASE_LOCK(:name)');
        $query->execute([':name' => self::LOCK_NAME]);
    }

    public function begin(): void { $this->pdo->beginTransaction(); }
    public function commit(): void { $this->pdo->commit(); }
    public function rollback(): void {
        if ($this->pdo->inTransaction()) $this->pdo->rollBack();
    }

    public function insertStudent(array $row, string $passwordHash, string $changeHash): void
    {
        $fields = [];
        for ($index = 1; $index <= 12; $index++) {
            $fields[':data' . $index] = (string) ($row['data' . $index] ?? '');
        }
        $query = $this->pdo->prepare(
            "INSERT INTO user_tbl(
                u_id,u_category,u_password,password_change_required,u_type,
                avail_status,account_source,sync_protected,
                data1,data2,data3,data4,data5,data6,data7,data8,data9,data10,
                data11,data12,u_update_datetime,u_changes_hash
             ) VALUES(
                :u_id,10,:password,1,0,1,'external',0,
                :data1,:data2,:data3,:data4,:data5,:data6,:data7,:data8,
                :data9,:data10,:data11,:data12,NOW(),:change_hash
             )"
        );
        $query->execute($fields + [
            ':u_id' => (string) $row['data4'],
            ':password' => $passwordHash,
            ':change_hash' => $changeHash,
        ]);
        if ($query->rowCount() !== 1) {
            throw new \RuntimeException('ODL_PILOT_USER_INSERT_FAILED');
        }
    }

    public function insertMembership(
        string $userId,
        string $externalUserId,
        string $sourceHash
    ): void {
        $query = $this->pdo->prepare(
            "INSERT INTO user_external_identity(
                u_id,source_code,external_user_id,source_active,source_hash,
                first_seen_at,last_seen_at,last_sync_at
             ) VALUES(
                :u_id,'STUDENT_ODL_PG',:external_user_id,1,:source_hash,
                NOW(),NOW(),NOW()
             )"
        );
        $query->execute([
            ':u_id' => $userId,
            ':external_user_id' => $externalUserId,
            ':source_hash' => $sourceHash,
        ]);
        if ($query->rowCount() !== 1) {
            throw new \RuntimeException('ODL_PILOT_MEMBERSHIP_INSERT_FAILED');
        }
    }

    public function appendEvent(
        string $correlationId,
        string $userId,
        string $externalUserId,
        string $eventType
    ): void {
        $query = $this->pdo->prepare(
            "INSERT INTO user_external_identity_event(
                correlation_id,source_code,u_id,external_user_id,event_type
             ) VALUES(
                :correlation,'STUDENT_ODL_PG',:u_id,:external_user_id,:event_type
             )"
        );
        $query->execute([
            ':correlation' => $correlationId,
            ':u_id' => $userId,
            ':external_user_id' => $externalUserId,
            ':event_type' => $eventType,
        ]);
        if ($query->rowCount() !== 1) {
            throw new \RuntimeException('ODL_PILOT_EVENT_INSERT_FAILED');
        }
    }

    public function reconciliation(string $correlationId): array
    {
        $query = $this->pdo->prepare(
            "SELECT
                COUNT(DISTINCT u.u_id) users,
                COUNT(DISTINCT i.id) memberships,
                COUNT(DISTINCT e.event_id) events
             FROM user_external_identity_event e
             LEFT JOIN user_tbl u ON u.u_id=e.u_id
             LEFT JOIN user_external_identity i
               ON i.u_id=e.u_id AND i.source_code=e.source_code
             WHERE e.correlation_id=:correlation
               AND e.source_code='STUDENT_ODL_PG'
               AND e.event_type='PILOT_NEW'"
        );
        $query->execute([':correlation' => $correlationId]);
        $row = $query->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'users' => (int) ($row['users'] ?? 0),
            'memberships' => (int) ($row['memberships'] ?? 0),
            'events' => (int) ($row['events'] ?? 0),
        ];
    }

    public function rollbackCorrelation(string $correlationId): int
    {
        $query = $this->pdo->prepare(
            "SELECT u_id,external_user_id
             FROM user_external_identity_event
             WHERE correlation_id=:correlation
               AND source_code='STUDENT_ODL_PG'
               AND event_type='PILOT_NEW'
             FOR UPDATE"
        );
        $query->execute([':correlation' => $correlationId]);
        $rows = $query->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) !== 3) {
            throw new \RuntimeException('ODL_PILOT_ROLLBACK_SCOPE_INVALID');
        }
        foreach ($rows as $row) {
            $guard = $this->pdo->prepare(
                "SELECT COUNT(*)
                 FROM user_tbl u
                 JOIN user_external_identity i ON i.u_id=u.u_id
                 WHERE u.u_id=:u_id AND u.u_category=10
                   AND u.account_source='external' AND u.sync_protected=0
                   AND i.source_code='STUDENT_ODL_PG'
                   AND i.external_user_id=:external_user_id
                   AND (SELECT COUNT(*) FROM user_external_identity x
                        WHERE x.u_id=u.u_id)=1"
            );
            $guard->execute([
                ':u_id' => $row['u_id'],
                ':external_user_id' => $row['external_user_id'],
            ]);
            if ((int) $guard->fetchColumn() !== 1) {
                throw new \RuntimeException('ODL_PILOT_ROLLBACK_GUARD_REJECTED');
            }
            $deleteMembership = $this->pdo->prepare(
                "DELETE FROM user_external_identity
                 WHERE u_id=:u_id AND source_code='STUDENT_ODL_PG'
                   AND external_user_id=:external_user_id"
            );
            $deleteMembership->execute([
                ':u_id' => $row['u_id'],
                ':external_user_id' => $row['external_user_id'],
            ]);
            $deleteUser = $this->pdo->prepare(
                "DELETE FROM user_tbl
                 WHERE u_id=:u_id AND u_category=10
                   AND account_source='external' AND sync_protected=0"
            );
            $deleteUser->execute([':u_id' => $row['u_id']]);
            if ($deleteMembership->rowCount() !== 1 || $deleteUser->rowCount() !== 1) {
                throw new \RuntimeException('ODL_PILOT_ROLLBACK_DELETE_MISMATCH');
            }
        }
        $update = $this->pdo->prepare(
            "UPDATE user_external_identity_event
             SET event_type='PILOT_ROLLED_BACK',rolled_back_at=NOW()
             WHERE correlation_id=:correlation AND event_type='PILOT_NEW'"
        );
        $update->execute([':correlation' => $correlationId]);
        if ($update->rowCount() !== 3) {
            throw new \RuntimeException('ODL_PILOT_ROLLBACK_EVENT_MISMATCH');
        }
        return 3;
    }
}
