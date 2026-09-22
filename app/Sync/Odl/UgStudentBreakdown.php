<?php
declare(strict_types=1);

namespace OneId\App\Sync\Odl;

final class UgStudentBreakdown
{
    /**
     * @param list<array<string,mixed>> $rows
     * @return array{asasi:int,diploma:int,degree:int,nieed:int,other:int,total:int}
     */
    public static function fromRows(array $rows): array
    {
        $counts = [
            'asasi' => 0,
            'diploma' => 0,
            'degree' => 0,
            'nieed' => 0,
            'other' => 0,
            'total' => count($rows),
        ];

        foreach ($rows as $row) {
            $matric = trim((string) ($row['data4'] ?? $row['no_matrik'] ?? ''));
            $key = match (substr($matric, 0, 1)) {
                '1' => 'asasi',
                '7' => 'diploma',
                '2' => 'degree',
                '8' => 'nieed',
                default => 'other',
            };
            $counts[$key]++;
        }

        return $counts;
    }
}
