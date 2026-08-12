<?php

declare(strict_types=1);

namespace OneId\App\Maintenance;

final class MaintenancePolicy
{
    public static function evaluate(array $stored, ?int $now = null): array
    {
        $now ??= time();
        $mode = strtoupper(trim((string)($stored['maintenance_mode'] ?? $stored['mode'] ?? 'OFF')));
        if (!in_array($mode, ['OFF', 'SCHEDULED', 'INDEFINITE'], true)) {
            $mode = 'OFF';
        }
        $start = self::timestamp($stored['maintenance_starts_at'] ?? $stored['starts_at'] ?? null);
        $end = self::timestamp($stored['maintenance_ends_at'] ?? $stored['ends_at'] ?? null);
        $active = $mode === 'INDEFINITE'
            ? ($start === null || $now >= $start)
            : ($mode === 'SCHEDULED' && $start !== null && $end !== null && $now >= $start && $now < $end);
        $state = $active ? 'ACTIVE' : ($mode === 'OFF' ? 'OFF' : ($start !== null && $now < $start ? 'SCHEDULED' : 'COMPLETED'));
        return [
            'mode'=>$mode,'state'=>$state,'active'=>$active,
            'starts_at'=>$stored['maintenance_starts_at'] ?? $stored['starts_at'] ?? null,
            'ends_at'=>$stored['maintenance_ends_at'] ?? $stored['ends_at'] ?? null,
            'retry_after_seconds'=>$active && $end !== null ? max(1, $end - $now) : null,
            'title_ms'=>(string)($stored['maintenance_title_ms'] ?? $stored['title_ms'] ?? 'Sistem OneID sedang diselenggara'),
            'title_en'=>(string)($stored['maintenance_title_en'] ?? $stored['title_en'] ?? 'OneID is under maintenance'),
            'message_ms'=>(string)($stored['maintenance_message_ms'] ?? $stored['message_ms'] ?? 'Perkhidmatan tidak tersedia buat sementara waktu.'),
            'message_en'=>(string)($stored['maintenance_message_en'] ?? $stored['message_en'] ?? 'The service is temporarily unavailable.'),
            'configuration_version'=>(int)($stored['configuration_version'] ?? 0),
        ];
    }

    private static function timestamp(mixed $value): ?int
    {
        $value = trim((string)$value);
        if ($value === '') return null;
        $parsed = strtotime($value . ' UTC');
        return $parsed === false ? null : $parsed;
    }
}
