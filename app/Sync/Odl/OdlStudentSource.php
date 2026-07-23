<?php

declare(strict_types=1);

namespace OneId\App\Sync\Odl;

use OneId\App\Sync\Contracts\ExternalUserSourceInterface;
use OneId\App\Sync\ExternalRowNormalizer;

final class OdlStudentSource implements ExternalUserSourceInterface
{
    public const SOURCE_CODE = 'STUDENT_ODL_PG';

    public const FIXED_QUERY = <<<'SQL'
SELECT
    nama AS data1,
    no_kad_pengenalan AS data2,
    '' AS data3,
    no_matrik AS data4,
    COALESCE(emel_alfateh, '') AS data5,
    fakulti AS data6,
    program AS data7,
    '' AS data8,
    '' AS data9,
    '' AS data10,
    '' AS data11,
    '' AS data12,
    'Pelajar' AS ext_data_source_category
FROM student_basic_info
SQL;

    /** @var null|\Closure(OdlSourceConfig,string):array<int,array<string,mixed>> */
    private readonly ?\Closure $fetcher;

    /**
     * @param null|\Closure(OdlSourceConfig,string):array<int,array<string,mixed>> $fetcher
     */
    public function __construct(
        private readonly OdlSourceConfig $config,
        ?\Closure $fetcher = null
    ) {
        $this->fetcher = $fetcher;
    }

    /** @return list<array<string,mixed>> */
    public function fetchAll(): array
    {
        $rows = $this->fetcher !== null
            ? ($this->fetcher)($this->config, self::FIXED_QUERY)
            : $this->fetchFromMySql();
        if ($rows === []) {
            throw new \RuntimeException('ODL_SOURCE_EMPTY');
        }

        $normalized = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \RuntimeException('ODL_SOURCE_ROW_INVALID');
            }
            $canonical = ExternalRowNormalizer::normalize($row);
            $canonical['source_code'] = self::SOURCE_CODE;
            $normalized[] = $canonical;
        }

        return $normalized;
    }

    /** @return list<array<string,mixed>> */
    private function fetchFromMySql(): array
    {
        if (!defined('PDO::MYSQL_ATTR_SSL_CA')
            || !defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')
        ) {
            throw new \RuntimeException('ODL_PDO_MYSQL_TLS_UNAVAILABLE');
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $this->config->host,
            $this->config->port,
            $this->config->database
        );
        $options = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
            \PDO::ATTR_PERSISTENT => false,
            \PDO::ATTR_TIMEOUT => $this->config->connectTimeout,
            \PDO::MYSQL_ATTR_SSL_CA => $this->config->sslCaPath,
            \PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT =>
                $this->config->sslCaPath !== '',
        ];

        try {
            $pdo = new \PDO(
                $dsn,
                $this->config->username,
                $this->config->password,
                $options
            );
        } catch (\PDOException $exception) {
            throw new \RuntimeException(
                'ODL_SOURCE_CONNECTION_FAILED',
                0,
                $exception
            );
        }

        try {
            $tls = $pdo->query(
                "SHOW SESSION STATUS
                 WHERE Variable_name IN ('Ssl_version','Ssl_cipher')"
            )->fetchAll(\PDO::FETCH_KEY_PAIR);
            if (trim((string) ($tls['Ssl_version'] ?? '')) === ''
                || trim((string) ($tls['Ssl_cipher'] ?? '')) === ''
            ) {
                throw new \RuntimeException('ODL_TLS_NOT_ACTIVE');
            }
            $rows = $pdo->query(self::FIXED_QUERY)->fetchAll();
        } catch (\RuntimeException $exception) {
            throw $exception;
        } catch (\PDOException $exception) {
            throw new \RuntimeException('ODL_SOURCE_QUERY_FAILED', 0, $exception);
        } finally {
            unset($pdo);
        }

        return $rows;
    }
}
