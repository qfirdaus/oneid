<?php

namespace OneId\App\Sync;

/** Convert driver-specific ODBC column labels into the canonical sync schema. */
final class ExternalRowNormalizer
{
    /** @param array<string, mixed> $row @return array<string, string> */
    public static function normalize(array $row): array
    {
        $row = array_change_key_case($row, CASE_LOWER);
        $aliases = [
            'nama' => 'data1',
            'idpekerja' => 'data2',
            'nopekerja' => 'data3',
            'no_matrik' => 'data4',
            'nama_ptj' => 'data6',
            'program' => 'data7',
            'jenis' => 'ext_data_source_category',
        ];
        foreach ($aliases as $source => $target) {
            if (!array_key_exists($target, $row) && array_key_exists($source, $row)) {
                $row[$target] = $row[$source];
            }
        }

        $normalized = [];
        for ($index = 1; $index <= 12; $index++) {
            $key = 'data' . $index;
            $normalized[$key] = (string) ($row[$key] ?? '');
        }
        $normalized['ext_data_source_category'] = (string) ($row['ext_data_source_category'] ?? '');

        return $normalized;
    }
}
