<?php
declare(strict_types=1);

namespace OneId\App\Admin;

final class ReportColumnLayout
{
    /**
     * @param list<string> $columns
     * @param list<array<string,mixed>> $rows
     * @return array{table_width:int,widths:list<int>,types:list<string>}
     */
    public static function calculate(array $columns, array $rows): array
    {
        $widths = [];
        $types = [];

        foreach ($columns as $index => $column) {
            $values = [];
            foreach ($rows as $row) {
                $value = trim((string) ($row[$column] ?? ''));
                if ($value !== '' && $value !== '—') {
                    $values[] = $value;
                }
            }

            if ($index === 0) {
                $type = 'sequence';
                $width = 52;
            } elseif ($values !== [] && self::allMatch($values, '/^\d{2}\/\d{2}\/\d{4}\s+\d{2}:\d{2}(?::\d{2})?$/')) {
                $type = 'datetime';
                $width = 155;
            } elseif ($values !== [] && self::allMatch($values, '/^\d{2}\/\d{2}\/\d{4}$/')) {
                $type = 'date';
                $width = 115;
            } elseif ($values !== [] && self::allMatch($values, '/^-?(?:\d+|\d{1,3}(?:,\d{3})+)(?:\.\d+)?$/')) {
                $type = 'numeric';
                $width = 115;
            } elseif ($values !== [] && self::allMatch($values, '~^(?:https?://|www\.)~i')) {
                $type = 'url';
                $width = self::contentWidth($column, $values, 180, 340, 6.2);
            } else {
                $type = 'text';
                $width = self::contentWidth($column, $values, 105, 360, 6.4);
            }

            $types[] = $type;
            $widths[] = $width;
        }

        return [
            'table_width' => array_sum($widths),
            'widths' => $widths,
            'types' => $types,
        ];
    }

    /** @param list<string> $values */
    private static function allMatch(array $values, string $pattern): bool
    {
        foreach ($values as $value) {
            if (preg_match($pattern, $value) !== 1) {
                return false;
            }
        }
        return true;
    }

    /** @param list<string> $values */
    private static function contentWidth(string $column, array $values, int $minimum, int $maximum, float $characterWidth): int
    {
        $length = self::length($column);
        foreach ($values as $value) {
            $length = max($length, self::length($value));
        }
        return (int) max($minimum, min($maximum, ceil(($length * $characterWidth) + 28)));
    }

    private static function length(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }
}
