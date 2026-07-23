<?php
declare(strict_types=1);
namespace OneId\App\Sync\Odl;

use OneId\App\Sync\Contracts\ExternalUserSourceInterface;
use OneId\App\Sync\ExternalRowNormalizer;

final class StaffSource implements ExternalUserSourceInterface
{
    public const SOURCE_CODE = 'STAFF_HR';
    public const FIXED_QUERY = 'SELECT (gelaran + " " + nama) AS data1,'
        . 'idpekerja AS data2,nopekerja AS data3,ISNULL(nokp,"") AS data4,'
        . 'ISNULL(email,"") AS data5,ISNULL(jabatansemasa,"") AS data6,'
        . 'ISNULL(jawatansemasa,"") AS data7,"" AS data8,"" AS data9,'
        . '"" AS data10,"" AS data11,"" AS data12,'
        . 'jenis AS ext_data_source_category FROM ehrmdb.dbo.SSO_Staf_Aktif';

    public function fetchAll(): array
    {
        if (!function_exists('odbc_connect')) {
            throw new \RuntimeException('ODBC_EXTENSION_UNAVAILABLE');
        }
        $connection = odbc_connect(
            \oneid_secret('ONEID_STAFF_ODBC_DSN'),
            \oneid_secret('ONEID_STAFF_ODBC_USERNAME'),
            \oneid_secret('ONEID_STAFF_ODBC_PASSWORD')
        );
        if (!$connection) {
            throw new \RuntimeException('EXTERNAL_STAFF_CONNECTION_FAILED');
        }
        try {
            $statement = \oneid_readonly_odbc_exec($connection, self::FIXED_QUERY);
            if ($statement === false) {
                throw new \RuntimeException('EXTERNAL_STAFF_QUERY_FAILED');
            }
            $rows = [];
            while ($row = odbc_fetch_array($statement)) {
                $row = ExternalRowNormalizer::normalize($row);
                $row['source_code'] = self::SOURCE_CODE;
                $rows[] = $row;
            }
        } finally {
            odbc_close($connection);
        }
        if ($rows === []) {
            throw new \RuntimeException('EXTERNAL_STAFF_EMPTY');
        }
        return $rows;
    }
}
