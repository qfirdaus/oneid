<?php

declare(strict_types=1);

namespace OneId\App\Admin;

final class UserCategoryReportReference
{
    private const SESSION_KEY = 'oneid_user_category_report_refs';
    private const TTL_SECONDS = 900;
    private const MAX_REFERENCES = 50;

    /** @param array<string,mixed> $session */
    public static function issue(array &$session, string $adminId, int $categoryId, ?int $now = null): string
    {
        $adminId = trim($adminId);
        if ($adminId === '' || $categoryId < 0) {
            throw new \InvalidArgumentException('USER_CATEGORY_REPORT_REFERENCE_INPUT_INVALID');
        }

        $now ??= time();
        $references = self::activeReferences($session, $now);
        $token = bin2hex(random_bytes(32));
        $references[$token] = [
            'admin_id' => $adminId,
            'category_id' => $categoryId,
            'expires_at' => $now + self::TTL_SECONDS,
        ];

        if (count($references) > self::MAX_REFERENCES) {
            uasort($references, static fn(array $left, array $right): int =>
                ((int) ($left['expires_at'] ?? 0)) <=> ((int) ($right['expires_at'] ?? 0))
            );
            $references = array_slice($references, -self::MAX_REFERENCES, null, true);
        }

        $session[self::SESSION_KEY] = $references;
        return $token;
    }

    /** @param array<string,mixed> $session */
    public static function resolve(array &$session, string $token, string $adminId, ?int $now = null): int
    {
        if (preg_match('/\A[a-f0-9]{64}\z/', $token) !== 1) {
            throw new \InvalidArgumentException('USER_CATEGORY_REPORT_REFERENCE_MALFORMED');
        }

        $now ??= time();
        $references = self::activeReferences($session, $now);
        $session[self::SESSION_KEY] = $references;
        $reference = $references[$token] ?? null;
        if (!is_array($reference)) {
            throw new \RuntimeException('USER_CATEGORY_REPORT_REFERENCE_EXPIRED');
        }
        if (!hash_equals((string) ($reference['admin_id'] ?? ''), trim($adminId))) {
            throw new \RuntimeException('USER_CATEGORY_REPORT_REFERENCE_FORBIDDEN');
        }

        $categoryId = filter_var($reference['category_id'] ?? null, FILTER_VALIDATE_INT);
        if ($categoryId === false || $categoryId < 0) {
            unset($session[self::SESSION_KEY][$token]);
            throw new \RuntimeException('USER_CATEGORY_REPORT_REFERENCE_INVALID');
        }
        return $categoryId;
    }

    /** @param array<string,mixed> $session @return array<string,array<string,mixed>> */
    private static function activeReferences(array $session, int $now): array
    {
        $stored = $session[self::SESSION_KEY] ?? [];
        if (!is_array($stored)) {
            return [];
        }

        return array_filter(
            $stored,
            static fn(mixed $reference): bool => is_array($reference)
                && (int) ($reference['expires_at'] ?? 0) >= $now
        );
    }
}
