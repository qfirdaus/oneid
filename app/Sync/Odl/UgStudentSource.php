<?php
declare(strict_types=1);
namespace OneId\App\Sync\Odl;

use OneId\App\Sync\Contracts\ExternalUserSourceInterface;
use OneId\App\Sync\ExternalRowNormalizer;

final class UgStudentSource implements ExternalUserSourceInterface
{
    public const SOURCE_CODE = 'STUDENT_UG';
    public const FIXED_QUERY = 'SELECT nama AS data1,no_matrik AS data4,'
        . '"" AS data3,ISNULL(nokp,"") AS data2,ISNULL(email,"") AS data5,'
        . 'nama_ptj AS data6,program AS data7,"" AS data8,"" AS data9,'
        . '"" AS data10,"" AS data11,"" AS data12,'
        . '"Pelajar" AS ext_data_source_category FROM v210_sso_student_aktif';

    /** @var null|\Closure(string):array<int,array<string,mixed>> */
    private readonly ?\Closure $fetcher;

    /** @param null|\Closure(string):array<int,array<string,mixed>> $fetcher */
    public function __construct(?\Closure $fetcher = null)
    {
        $this->fetcher = $fetcher;
    }

    public function fetchAll(): array
    {
        if ($this->fetcher !== null) {
            $rows = ($this->fetcher)(self::FIXED_QUERY);
        } else {
            if (!function_exists('odbc_connect')) {
                throw new \RuntimeException('ODBC_EXTENSION_UNAVAILABLE');
            }
            $connection = odbc_connect(
                \oneid_secret('ONEID_STUDENT_SYNC_ODBC_DSN'),
                \oneid_secret('ONEID_STUDENT_SYNC_ODBC_USERNAME'),
                \oneid_secret('ONEID_STUDENT_SYNC_ODBC_PASSWORD')
            );
            if (!$connection) {
                throw new \RuntimeException('EXTERNAL_STUDENT_CONNECTION_FAILED');
            }
            try {
                $statement = \oneid_readonly_odbc_exec($connection, self::FIXED_QUERY);
                if ($statement === false) {
                    throw new \RuntimeException('EXTERNAL_STUDENT_QUERY_FAILED');
                }
                $rows = [];
                while ($row = odbc_fetch_array($statement)) {
                    $rows[] = $row;
                }
            } finally {
                odbc_close($connection);
            }
        }
        if ($rows === []) {
            throw new \RuntimeException('EXTERNAL_STUDENT_EMPTY');
        }
        return array_map(static function (array $row): array {
            $row = ExternalRowNormalizer::normalize($row);
            $row['source_code'] = self::SOURCE_CODE;
            return $row;
        }, $rows);
    }
}
