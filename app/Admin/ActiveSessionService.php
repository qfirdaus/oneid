<?php

namespace OneId\App\Admin;

use InvalidArgumentException;

final class ActiveSessionService
{
    private const STATUSES = ['all', 'current', 'active', 'refresh', 'grace', 'due', 'expired'];
    private const PAGE_SIZES = [10, 25, 50];

    public function __construct(private readonly object $operation)
    {
    }

    /** @param array<string, mixed> $input */
    public function list(array $input, string $currentUserId, string $currentToken, float $lifetimeHours): array
    {
        $allowed = ['admin_get_all_token_for_all_active_user', 'page', 'page_size', 'query', 'status'];
        if (array_diff(array_keys($input), $allowed) !== []) {
            throw new InvalidArgumentException('AS0_UNEXPECTED_FIELD');
        }
        $page = $this->positiveInteger($input['page'] ?? '1', 'AS0_PAGE_INVALID');
        $pageSize = $this->positiveInteger($input['page_size'] ?? '25', 'AS0_PAGE_SIZE_INVALID');
        if (!in_array($pageSize, self::PAGE_SIZES, true)) {
            throw new InvalidArgumentException('AS0_PAGE_SIZE_INVALID');
        }

        $status = strtolower(trim((string) ($input['status'] ?? 'all')));
        if (!in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException('AS0_STATUS_INVALID');
        }

        $query = trim((string) ($input['query'] ?? ''));
        if (strlen($query) > 80 || preg_match('/[\x00-\x1F\x7F]/', $query) === 1) {
            throw new InvalidArgumentException('AS0_QUERY_INVALID');
        }
        if ($lifetimeHours <= 0 || $lifetimeHours > 168) {
            throw new InvalidArgumentException('AS0_LIFETIME_INVALID');
        }
        if (strlen($currentToken) > 256) {
            $currentToken = '';
        }

        $now = date('Y-m-d H:i:s');
        $activeCutoff = date('Y-m-d H:i:s', time() - (int) round($lifetimeHours * 3600));
        $refreshCutoff = date('Y-m-d H:i:s', time() - (int) round($lifetimeHours * 3600) - 3600);
        $tokenHash = $currentToken === '' ? '' : oneid_token_hash($currentToken);
        $result = $this->operation->admin_list_active_sessions([
            'page' => $page,
            'page_size' => $pageSize,
            'offset' => ($page - 1) * $pageSize,
            'query' => $query,
            'status' => $status,
            'now' => $now,
            'active_cutoff' => $activeCutoff,
            'refresh_cutoff' => $refreshCutoff,
            'current_user_id' => trim($currentUserId),
            'current_token' => $currentToken,
            'current_token_hash' => $tokenHash,
        ]);
        if (!is_array($result) || !isset($result['rows'], $result['total'], $result['metrics']) || !is_array($result['rows']) || !is_array($result['metrics'])) {
            throw new InvalidArgumentException('AS0_RESULT_INVALID');
        }

        $rows = [];
        foreach ($result['rows'] as $row) {
            $state = (string) ($row['lifecycle_status'] ?? '');
            if (!in_array($state, array_slice(self::STATUSES, 1), true)) {
                throw new InvalidArgumentException('AS0_RESULT_INVALID');
            }
            $revokeAt = $row['revoke_at'] ?? null;
            $rows[] = [
                'user_id' => (string) ($row['user_id'] ?? ''),
                'name' => trim((string) ($row['name'] ?? '')) ?: 'Unknown user',
                'device_info' => $this->normalizeDevice($row['device_info'] ?? ''),
                'issued_at' => (string) ($row['issued_at'] ?? ''),
                'last_activity_at' => (string) ($row['last_activity_at'] ?? ''),
                'revoke_at' => $revokeAt === null ? null : (string) $revokeAt,
                'status' => $state,
            ];
        }

        $total = max(0, (int) $result['total']);
        $pages = max(1, (int) ceil($total / $pageSize));
        $metrics = [];
        foreach (array_slice(self::STATUSES, 1) as $state) {
            $metrics[$state] = max(0, (int) ($result['metrics'][$state] ?? 0));
        }
        return [
            'status' => 1,
            'code' => 'AS0_SESSIONS_LOADED',
            'data' => $rows,
            'meta' => [
                'page' => $page,
                'page_size' => $pageSize,
                'total' => $total,
                'total_pages' => $pages,
                'query' => $query,
                'status' => $status,
                'metrics' => $metrics,
            ],
        ];
    }

    private function positiveInteger(mixed $value, string $reason): int
    {
        $normalized = trim((string) $value);
        if (preg_match('/^[1-9][0-9]{0,5}$/', $normalized) !== 1) {
            throw new InvalidArgumentException($reason);
        }
        return (int) $normalized;
    }

    private function normalizeDevice(mixed $value): string
    {
        if (function_exists('oneid_normalize_device_info')) {
            return \oneid_normalize_device_info($value);
        }
        $normalized = trim((string) preg_replace('/\s*\(\s*\)/', '', trim((string) $value)));
        return $normalized === '' ? 'Unknown device' : ucfirst($normalized);
    }
}
