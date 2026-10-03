<?php
declare(strict_types=1);
namespace OneId\App\Auth\MobileOidc;

final class IdentityResolver
{
    public static function categories(array $row): array
    {
        $families = array_values(array_unique(array_intersect($row['families'] ?? [], ['staff', 'student'])));
        if ($families !== []) return $families;
        // Existing OneID category mapping; membership is not extra app registration.
        return match ((int) ($row['u_category'] ?? 0)) {
            2, 3 => ['staff'],
            10, 11, 12 => ['student'],
            default => [],
        };
    }

    public static function resolve(array $rows, string $identifier): ?array
    {
        $matches = [];
        foreach ($rows as $row) {
            foreach (self::categories($row) as $kind) {
                $number = trim((string) ($row[$kind === 'staff' ? 'data3' : 'data4'] ?? ''));
                if ($number === '' && $kind === 'student') $number = (string) $row['u_id'];
                if ($number !== '' && strcasecmp($number, $identifier) === 0) {
                    $row['account_type'] = $kind;
                    $row['login_number'] = $number;
                    $matches[(string) $row['u_id'] . ':' . $kind] = $row;
                }
            }
        }
        // Never pick LIMIT 1 or resolve collisions by trying each password.
        return count($matches) === 1 ? array_values($matches)[0] : null;
    }

    public static function context(array $row, string $kind): ?array
    {
        if (!in_array($kind, self::categories($row), true)) return null;
        $row['account_type'] = $kind;
        return $row;
    }
}
