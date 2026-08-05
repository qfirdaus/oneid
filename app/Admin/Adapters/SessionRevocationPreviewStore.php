<?php

namespace OneId\App\Admin\Adapters;

final class SessionRevocationPreviewStore
{
    private const KEY = 'oneid_active_session_revocation';

    public function __construct(private readonly int $ttlSeconds = 300)
    {
    }

    /** @param array<string,mixed> $target */
    public function issueTarget(string $actor, array $target): string
    {
        $this->prune();
        $id = bin2hex(random_bytes(24));
        $_SESSION[self::KEY]['targets'][$id] = ['actor' => $actor, 'expires' => time() + 600, 'target' => $target];
        return $id;
    }

    /** @return array<string,mixed>|null */
    public function consumeTarget(string $id, string $actor): ?array
    {
        $this->prune();
        $entry = $_SESSION[self::KEY]['targets'][$id] ?? null;
        unset($_SESSION[self::KEY]['targets'][$id]);
        return is_array($entry) && hash_equals((string)($entry['actor'] ?? ''), $actor)
            ? (is_array($entry['target'] ?? null) ? $entry['target'] : null) : null;
    }

    /** @param array<string,mixed> $approval */
    public function issueApproval(string $actor, array $approval): array
    {
        $this->prune();
        $id = bin2hex(random_bytes(32));
        $expires = time() + $this->ttlSeconds;
        $_SESSION[self::KEY]['approvals'][$id] = ['actor' => $actor, 'expires' => $expires, 'approval' => $approval];
        return ['id' => $id, 'expires_at' => date(DATE_ATOM, $expires)];
    }

    /** @return array<string,mixed>|null */
    public function consumeApproval(string $id, string $actor): ?array
    {
        $this->prune();
        $entry = $_SESSION[self::KEY]['approvals'][$id] ?? null;
        unset($_SESSION[self::KEY]['approvals'][$id]);
        return is_array($entry) && hash_equals((string)($entry['actor'] ?? ''), $actor)
            ? (is_array($entry['approval'] ?? null) ? $entry['approval'] : null) : null;
    }

    private function prune(): void
    {
        $now = time();
        foreach (['targets', 'approvals'] as $bucket) {
            foreach ((array)($_SESSION[self::KEY][$bucket] ?? []) as $id => $entry) {
                if (!is_array($entry) || (int)($entry['expires'] ?? 0) < $now) unset($_SESSION[self::KEY][$bucket][$id]);
            }
        }
    }
}
