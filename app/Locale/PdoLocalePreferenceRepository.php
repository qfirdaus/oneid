<?php
declare(strict_types=1);

namespace OneId\App\Locale;

use PDO;

final class PdoLocalePreferenceRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function find(string $userId): ?string
    {
        $statement = $this->pdo->prepare(
            'SELECT locale FROM user_locale_preference WHERE u_id=:u_id LIMIT 1'
        );
        $statement->execute([':u_id' => $userId]);
        $locale = $statement->fetchColumn();
        return $locale === false ? null : LocaleResolver::valid($locale);
    }

    public function save(string $userId, string $locale): void
    {
        $valid = LocaleResolver::valid($locale);
        if ($valid === null) {
            throw new \InvalidArgumentException('LOCALE_NOT_ALLOWED');
        }
        $statement = $this->pdo->prepare(
            'INSERT INTO user_locale_preference(u_id,locale,created_at,updated_at)
             VALUES(:u_id,:locale,NOW(),NOW())
             ON DUPLICATE KEY UPDATE locale=VALUES(locale),updated_at=NOW()'
        );
        $statement->execute([':u_id' => $userId, ':locale' => $valid]);
    }
}
