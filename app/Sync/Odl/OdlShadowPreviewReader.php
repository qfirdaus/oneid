<?php
declare(strict_types=1);
namespace OneId\App\Sync\Odl;

final class OdlShadowPreviewReader
{
    /** @var null|\Closure():array<int,array<string,mixed>> */
    private readonly ?\Closure $usersReader;
    /** @var null|\Closure():array<int,array<string,mixed>> */
    private readonly ?\Closure $membershipsReader;

    public function __construct(
        private readonly ?\PDO $pdo = null,
        ?\Closure $usersReader = null,
        ?\Closure $membershipsReader = null
    ) {
        $this->usersReader = $usersReader;
        $this->membershipsReader = $membershipsReader;
        if ($pdo === null && ($usersReader === null || $membershipsReader === null)) {
            throw new \InvalidArgumentException('ODL_SHADOW_READER_INVALID');
        }
    }

    /** @return list<array<string,mixed>> */
    public function users(): array
    {
        if ($this->usersReader !== null) {
            return ($this->usersReader)();
        }
        return $this->pdo->query(
            'SELECT u_id,data2,avail_status,account_source,sync_protected
             FROM user_tbl'
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** @return list<array<string,mixed>> */
    public function memberships(): array
    {
        if ($this->membershipsReader !== null) {
            return ($this->membershipsReader)();
        }
        return $this->pdo->query(
            'SELECT u_id,source_code,external_user_id,source_active
             FROM user_external_identity'
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** @return list<array<string,mixed>> */
    public function legacyActiveUsers(): array
    {
        if ($this->usersReader !== null) {
            return array_values(array_filter(
                ($this->usersReader)(),
                static fn(array $user): bool =>
                    (int) ($user['avail_status'] ?? 1) === 1
            ));
        }
        return $this->pdo->query(
            "SELECT u_id,u_category,avail_status,
                    data1,data2,data3,data4,data5,data6,
                    data7,data8,data9,data10,data11,data12,
                    u_changes_hash,'1' AS source,
                    account_source,sync_protected
             FROM user_tbl
             WHERE avail_status=1"
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** @return list<string> */
    public function legacyInactiveUserIds(): array
    {
        if ($this->usersReader !== null) {
            return array_values(array_map(
                static fn(array $user): string => (string) ($user['u_id'] ?? ''),
                array_filter(
                    ($this->usersReader)(),
                    static fn(array $user): bool =>
                        (int) ($user['avail_status'] ?? 1) === 0
                )
            ));
        }
        $rows = $this->pdo->query(
            'SELECT u_id FROM user_tbl WHERE avail_status=0'
        )->fetchAll(\PDO::FETCH_COLUMN, 0);
        return $rows ?: [];
    }

    /** @return list<string> */
    public function activeUserIdsBySource(string $sourceCode): array
    {
        if (preg_match('/^[A-Z0-9_]{1,64}$/', $sourceCode) !== 1) {
            throw new \InvalidArgumentException('SYNC_SOURCE_INVALID');
        }
        if ($this->membershipsReader !== null) {
            return array_values(array_map(
                static fn(array $membership): string =>
                    (string) ($membership['u_id'] ?? ''),
                array_filter(
                    ($this->membershipsReader)(),
                    static fn(array $membership): bool =>
                        ($membership['source_code'] ?? '') === $sourceCode
                        && (int) ($membership['source_active'] ?? 0) === 1
                )
            ));
        }
        $query = $this->pdo->prepare(
            'SELECT u_id FROM user_external_identity
             WHERE source_code=:source_code AND source_active=1'
        );
        $query->execute([':source_code' => $sourceCode]);
        return $query->fetchAll(\PDO::FETCH_COLUMN, 0) ?: [];
    }
}
