<?php

declare(strict_types=1);

namespace OneId\App\Auth\MyDigitalId;

use PDO;

final class PdoMyDigitalIdAccountMatcher
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function matchByNric(string $normalizedNric): MyDigitalIdAccountMatch
    {
        $this->assertNric($normalizedNric);
        $rows = $this->rowsForNric($normalizedNric);
        $active = array_values(array_filter(
            $rows,
            static fn(array $row): bool => (int) $row['avail_status'] === 1
        ));
        if (count($active) > 1) {
            return MyDigitalIdAccountMatch::rejected('AMBIGUOUS');
        }
        if (count($active) === 1) {
            return MyDigitalIdAccountMatch::matched($active[0]);
        }
        if ($rows !== []) {
            return MyDigitalIdAccountMatch::rejected('INACTIVE');
        }
        return MyDigitalIdAccountMatch::rejected('NOT_FOUND');
    }

    public function matchLinkedUser(string $userId, string $normalizedNric): MyDigitalIdAccountMatch
    {
        $this->assertUserId($userId);
        $this->assertNric($normalizedNric);
        $statement = $this->pdo->prepare(
            "SELECT u_id,u_type,avail_status,password_change_required,data1,
                    CASE WHEN TRIM(COALESCE(data3,''))<>''
                         THEN REPLACE(REPLACE(TRIM(COALESCE(data4,'')),'-',''),' ','')
                         ELSE REPLACE(REPLACE(TRIM(COALESCE(data2,'')),'-',''),' ','')
                    END normalized_nric
             FROM user_tbl
             WHERE u_id=:u_id
             LIMIT 1
             FOR UPDATE"
        );
        $statement->execute([':u_id' => $userId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return MyDigitalIdAccountMatch::rejected('NOT_FOUND');
        }
        if ((int) $row['avail_status'] !== 1) {
            return MyDigitalIdAccountMatch::rejected('INACTIVE');
        }
        if (!hash_equals($normalizedNric, (string) $row['normalized_nric'])) {
            return MyDigitalIdAccountMatch::rejected('NOT_FOUND');
        }
        return MyDigitalIdAccountMatch::matched($row);
    }

    /** @return list<array<string,mixed>> */
    private function rowsForNric(string $normalizedNric): array
    {
        $statement = $this->pdo->prepare(
            "SELECT u_id,u_type,avail_status,password_change_required,data1,
                    CASE WHEN TRIM(COALESCE(data3,''))<>''
                         THEN REPLACE(REPLACE(TRIM(COALESCE(data4,'')),'-',''),' ','')
                         ELSE REPLACE(REPLACE(TRIM(COALESCE(data2,'')),'-',''),' ','')
                    END normalized_nric
             FROM user_tbl
             WHERE CASE WHEN TRIM(COALESCE(data3,''))<>''
                        THEN REPLACE(REPLACE(TRIM(COALESCE(data4,'')),'-',''),' ','')
                        ELSE REPLACE(REPLACE(TRIM(COALESCE(data2,'')),'-',''),' ','')
                   END=:nric
             ORDER BY u_id
             LIMIT 3
             FOR UPDATE"
        );
        $statement->execute([':nric' => $normalizedNric]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    private function assertNric(string $nric): void
    {
        if (preg_match('/^\d{12}$/D', $nric) !== 1) {
            throw new MyDigitalIdPersistenceException('MYDID_NRIC_INVALID');
        }
    }

    private function assertUserId(string $userId): void
    {
        if (preg_match('/^[A-Za-z0-9._@-]{1,20}$/D', $userId) !== 1) {
            throw new MyDigitalIdPersistenceException('MYDID_USER_ID_INVALID');
        }
    }
}
