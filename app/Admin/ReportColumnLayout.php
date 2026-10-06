<?php
declare(strict_types=1);

namespace OneId\App\Admin;

final class ReportColumnLayout
{
    private const PANEL_WIDTH = 1160;
    /**
     * @param list<string> $columns
     * @param list<array<string,mixed>> $rows
     * @return array{minimum_width:int,widths:list<int>,types:list<string>,flex_index:int}
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

        self::shrinkVariableColumnsToPanel($widths, $types);

        $flexCandidates = [];
        foreach ($types as $index => $type) {
            if ($type === 'text' || $type === 'url') {
                $flexCandidates[$index] = $widths[$index];
            }
        }
        $flexIndex = $flexCandidates === [] ? max(0, count($columns) - 1) : (int) array_search(max($flexCandidates), $flexCandidates, true);

        return [
            'minimum_width' => array_sum($widths),
            'widths' => $widths,
            'types' => $types,
            'flex_index' => $flexIndex,
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

    /** @param list<int> $widths @param list<string> $types */
    private static function shrinkVariableColumnsToPanel(array &$widths, array $types): void
    {
        $overflow = array_sum($widths) - self::PANEL_WIDTH;
        while ($overflow > 0) {
            $candidates = [];
            foreach ($types as $index => $type) {
                $minimum = $type === 'url' ? 180 : 105;
                if (($type === 'text' || $type === 'url') && $widths[$index] > $minimum) {
                    $candidates[$index] = $minimum;
                }
            }
            if ($candidates === []) {
                break;
            }
            $share = (int) ceil($overflow / count($candidates));
            foreach ($candidates as $index => $minimum) {
                $reduction = min($share, $widths[$index] - $minimum, $overflow);
                $widths[$index] -= $reduction;
                $overflow -= $reduction;
                if ($overflow <= 0) {
                    break;
                }
            }
        }
    }
}
