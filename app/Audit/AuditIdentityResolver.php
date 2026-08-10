<?php
declare(strict_types=1);

namespace OneId\App\Audit;

use PDO;

final class AuditIdentityResolver
{
    /** @var array<string,string> */
    private array $cache = [];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function resolve(string $identifier): string
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return '[ID_REDACTED]';
        }
        if (isset($this->cache[$identifier])) {
            return $this->cache[$identifier];
        }
        if ($this->isStaffNumber($identifier) || $this->isSafeMatric($identifier)) {
            return $this->cache[$identifier] = $identifier;
        }
        $statement = $this->pdo->prepare(
            'SELECT u_id,data2,data3,data4 FROM user_tbl
             WHERE u_id=:u_id OR data2=:data2 OR data3=:data3 OR data4=:data4
             ORDER BY (u_id=:preferred) DESC LIMIT 1'
        );
        $statement->execute([
            ':u_id' => $identifier,
            ':data2' => $identifier,
            ':data3' => $identifier,
            ':data4' => $identifier,
            ':preferred' => $identifier,
        ]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);
        if (is_array($user)) {
            $staff = trim((string) ($user['data3'] ?? ''));
            if ($this->isStaffNumber($staff)) {
                return $this->cache[$identifier] = $staff;
            }
            $matric = trim((string) ($user['data4'] ?? ''));
            if ($this->isSafeMatric($matric)) {
                return $this->cache[$identifier] = $matric;
            }
            $userId = trim((string) ($user['u_id'] ?? ''));
            if ($this->isSafeMatric($userId)) {
                return $this->cache[$identifier] = $userId;
            }
        }
        return $this->cache[$identifier] = $this->looksLikeIc($identifier)
            ? '[ID_REDACTED]'
            : $this->safeOpaque($identifier);
    }

    public function sanitizeDetail(string $detail): string
    {
        return preg_replace_callback(
            '/(?<!\d)(?:\d{6}-?\d{2}-?\d{4})(?!\d)/',
            fn(array $match): string => $this->resolve(str_replace('-', '', $match[0])),
            $detail
        ) ?? $detail;
    }

    private function isStaffNumber(string $value): bool
    {
        return preg_match('/\A\d{4}-\d{2}\z/', $value) === 1;
    }

    private function isSafeMatric(string $value): bool
    {
        return $value !== ''
            && strlen($value) <= 30
            && !$this->looksLikeIc($value)
            && preg_match('/\A[A-Za-z0-9._\/-]+\z/', $value) === 1;
    }

    private function looksLikeIc(string $value): bool
    {
        return preg_match('/\A\d{6}-?\d{2}-?\d{4}\z/', $value) === 1;
    }

    private function safeOpaque(string $value): string
    {
        if (strlen($value) > 30 || preg_match('/\A[A-Za-z0-9._@\/-]+\z/', $value) !== 1) {
            return '[ID_REDACTED]';
        }
        return $value;
    }
}
