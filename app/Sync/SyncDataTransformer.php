<?php

namespace OneId\App\Sync;

final class SyncDataTransformer
{
    public static function computeHash(
        $d1,
        $d2,
        $d3,
        $d4,
        $d5,
        $d6,
        $d7,
        $d8,
        $d9,
        $d10,
        $d11,
        $d12,
        $category = ''
    ): string {
        return hash(
            'sha256',
            trim($d1) . trim($d2) . trim($d3) . trim($d4)
            . trim($d5) . trim($d6) . trim($d7) . trim($d8)
            . trim($d9) . trim($d10) . trim($d11) . trim($d12)
            . trim($category)
        );
    }

    public static function logFieldNames(): array
    {
        return ['data1', 'data2', 'data3', 'data4', 'data5', 'data6', 'data7', 'ext_data_source_category'];
    }

    public static function buildLogSnapshot($row): array
    {
        $snapshot = [];
        foreach (self::logFieldNames() as $field) {
            $snapshot[$field] = isset($row[$field]) ? trim((string) $row[$field]) : '';
        }
        return $snapshot;
    }

    public static function pickLogFields($row, $fields): array
    {
        $picked = [];
        foreach ($fields as $field) {
            $picked[$field] = isset($row[$field]) ? trim((string) $row[$field]) : '';
        }
        if (isset($row['ext_data_source_category']) && !in_array('ext_data_source_category', $fields, true)) {
            $picked['ext_data_source_category'] = trim((string) $row['ext_data_source_category']);
        }
        return $picked;
    }

    public static function getChangedFields($old, $new): string
    {
        $changed = [];
        foreach (['data1', 'data2', 'data3', 'data4', 'data5', 'data6', 'data7'] as $field) {
            $oldValue = isset($old[$field]) ? trim((string) $old[$field]) : '';
            $newValue = isset($new[$field]) ? trim((string) $new[$field]) : '';
            if ($oldValue !== $newValue) {
                $changed[] = $field;
            }
        }
        return implode(',', $changed);
    }

    public static function removeDuplicateKeys($rows): array
    {
        $result = [];
        foreach ($rows as $row) {
            if (!empty($result[$row['u_changes_hash']])) {
                unset($result[$row['u_changes_hash']]);
            } else {
                $result[$row['u_changes_hash']] = $row;
            }
        }
        return array_values($result);
    }
}
