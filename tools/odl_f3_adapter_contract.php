<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

$root = dirname(__DIR__);
$config = (string) file_get_contents($root . '/app/Sync/Odl/OdlSourceConfig.php');
$source = (string) file_get_contents($root . '/app/Sync/Odl/OdlStudentSource.php');
$bootstrap = (string) file_get_contents($root . '/bootstrap/sync_runtime.php');
$example = (string) file_get_contents($root . '/docs/examples/oneid-secrets.example.php');
$runtime = '';
foreach ([
    'lib/q_func.php',
    'admin/dashboard.php',
    'app/Sync/SyncEngineFactory.php',
] as $file) {
    $runtime .= (string) file_get_contents($root . '/' . $file);
}

$checks = 0;
$failed = 0;
$report = static function (bool $ok, string $label) use (&$checks, &$failed): void {
    $checks++;
    if (!$ok) {
        $failed++;
    }
    printf("%s %s\n", $ok ? 'PASS' : 'FAIL', $label);
};

$report(
    str_contains($source, 'implements ExternalUserSourceInterface'),
    'adapter implements external source contract'
);
$report(
    str_contains($source, 'FROM student_basic_info')
        && substr_count($source, 'FROM student_basic_info') === 1
        && !preg_match(
            '/\b(?:INSERT|UPDATE|DELETE|REPLACE|ALTER|CREATE|DROP|TRUNCATE)\b/i',
            OdlStudentSourceQuery::value($source)
        ),
    'adapter owns one fixed SELECT query'
);
$report(
    str_contains($source, 'PDO::MYSQL_ATTR_SSL_CA')
        && str_contains($source, 'PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')
        && str_contains($source, 'ODL_TLS_NOT_ACTIVE'),
    'adapter requires verified TLS and active cipher'
);
$report(
    str_contains($source, 'PDO::ATTR_PERSISTENT => false')
        && str_contains($source, 'PDO::ATTR_EMULATE_PREPARES => false')
        && str_contains($source, 'charset=utf8mb4'),
    'PDO connection is nonpersistent strict and utf8mb4'
);
$report(
    str_contains($source, "public const SOURCE_CODE = 'STUDENT_ODL_PG'")
        && str_contains($source, "\$canonical['source_code'] = self::SOURCE_CODE"),
    'source code is application-owned and canonical'
);
$report(
    str_contains($source, 'ODL_SOURCE_CONNECTION_FAILED')
        && str_contains($source, 'ODL_SOURCE_QUERY_FAILED')
        && str_contains($source, 'ODL_SOURCE_EMPTY'),
    'connection query and empty failures are distinct'
);
$report(
    str_contains($config, "oneid_secret('ONEID_ODL_MYSQL_PASSWORD')")
        && str_contains($config, "oneid_secret('ONEID_ODL_MYSQL_SSL_CA')")
        && !str_contains($config, '172.16.2.224'),
    'runtime config reads private values without committed endpoint'
);
$requiredKeys = [
    'ONEID_ODL_MYSQL_HOST',
    'ONEID_ODL_MYSQL_PORT',
    'ONEID_ODL_MYSQL_DATABASE',
    'ONEID_ODL_MYSQL_USERNAME',
    'ONEID_ODL_MYSQL_PASSWORD',
    'ONEID_ODL_MYSQL_SSL_CA',
    'ONEID_ODL_MYSQL_CONNECT_TIMEOUT',
];
$keysPresent = true;
foreach ($requiredKeys as $key) {
    $keysPresent = $keysPresent && str_contains($example, "'{$key}' => ''");
}
$report($keysPresent, 'example declares blank ODL private keys');
$report(
    str_contains($bootstrap, 'app/Sync/Odl/OdlSourceConfig.php')
        && str_contains($bootstrap, 'app/Sync/Odl/OdlStudentSource.php'),
    'bootstrap loads definitions without constructing adapter'
);
$report(
    !str_contains($runtime, 'OdlStudentSource')
        && !str_contains($runtime, 'STUDENT_ODL_PG'),
    'adapter remains outside Preview Apply and dashboard wiring'
);

$output = [];
$exitCode = 1;
exec(
    escapeshellarg(PHP_BINARY) . ' '
        . escapeshellarg($root . '/tests/characterization/odl_f3_student_source.php')
        . ' 2>&1',
    $output,
    $exitCode
);
$resultLine = '';
foreach ($output as $line) {
    if (str_starts_with($line, 'RESULT ')) {
        $resultLine = $line;
    }
}
$report(
    $exitCode === 0 && $resultLine === 'RESULT checks=16 failed=0',
    'adapter characterization passes'
);

printf("RESULT checks=%d failed=%d\n", $checks, $failed);
exit($failed === 0 ? 0 : 1);

final class OdlStudentSourceQuery
{
    public static function value(string $source): string
    {
        if (preg_match(
            "/public const FIXED_QUERY = <<<'SQL'\\R(.*?)\\RSQL;/s",
            $source,
            $match
        ) !== 1) {
            return '';
        }

        return $match[1];
    }
}
