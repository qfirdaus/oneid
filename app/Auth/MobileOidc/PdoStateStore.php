<?php
declare(strict_types=1);
namespace OneId\App\Auth\MobileOidc;

use PDO;
use RuntimeException;
use Throwable;

final class PdoStateStore implements StateStore
{
    public function __construct(private readonly PDO $pdo) {}

    public function transaction(callable $work): mixed
    {
        if ($this->pdo->inTransaction()) throw new RuntimeException('MOBILE_NESTED_TRANSACTION');
        $this->pdo->beginTransaction();
        try {
            // Deliberately serialized dormant adapter; no race on absent rate/subject keys.
            if ($this->pdo->query('SELECT singleton_id FROM mobile_oidc_mutex WHERE singleton_id=1 FOR UPDATE')->fetchColumn() === false) {
                throw new RuntimeException('MOBILE_SCHEMA_UNAVAILABLE');
            }
            $result = $work();
            $this->pdo->commit();
            return $result;
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
    }

    public function get(string $key): ?array
    {
        $this->assertTransaction();
        $q = $this->pdo->prepare('SELECT record_json FROM mobile_oidc_records WHERE record_key=:k FOR UPDATE');
        $q->execute(['k' => $key]);
        $value = $q->fetchColumn();
        return $value === false ? null : json_decode((string) $value, true, 64, JSON_THROW_ON_ERROR);
    }

    public function put(string $key, array $value): void
    {
        $this->assertTransaction();
        $q = $this->pdo->prepare('INSERT INTO mobile_oidc_records(record_key,record_json) VALUES(:k,:v) ON DUPLICATE KEY UPDATE record_json=VALUES(record_json)');
        $q->execute(['k' => $key, 'v' => json_encode($value, JSON_THROW_ON_ERROR)]);
    }

    private function assertTransaction(): void
    {
        if (!$this->pdo->inTransaction()) throw new RuntimeException('MOBILE_TRANSACTION_REQUIRED');
    }
}
