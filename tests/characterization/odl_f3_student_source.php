<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

require_once dirname(__DIR__, 2)
    . '/app/Sync/Contracts/ExternalUserSourceInterface.php';
require_once dirname(__DIR__, 2) . '/app/Sync/ExternalRowNormalizer.php';
require_once dirname(__DIR__, 2) . '/app/Sync/Odl/OdlSourceConfig.php';
require_once dirname(__DIR__, 2) . '/app/Sync/Odl/OdlStudentSource.php';

use OneId\App\Sync\Odl\OdlSourceConfig;
use OneId\App\Sync\Odl\OdlStudentSource;

$checks = 0;
$failed = 0;
$report = static function (bool $ok, string $label) use (&$checks, &$failed): void {
    $checks++;
    if (!$ok) {
        $failed++;
    }
    printf("%s %s\n", $ok ? 'PASS' : 'FAIL', $label);
};

$caPath = tempnam(sys_get_temp_dir(), 'oneid-odl-ca-');
if ($caPath === false) {
    fwrite(STDERR, "FAIL temp CA fixture\n");
    exit(1);
}
file_put_contents($caPath, "test-ca-fixture\n");

try {
    $config = new OdlSourceConfig(
        '172.16.2.224',
        3308,
        'upnm',
        'viewer',
        'private-test-value',
        $caPath,
        5
    );
    $report($config->port === 3308 && $config->connectTimeout === 5, 'valid private config accepted');

    $capturedQuery = '';
    $source = new OdlStudentSource(
        $config,
        static function (OdlSourceConfig $_config, string $query) use (&$capturedQuery): array {
            $capturedQuery = $query;
            return [[
                'DATA1' => 'Student ODL',
                'DATA2' => '900101-01-1234',
                'DATA3' => '',
                'DATA4' => 'PG-001',
                'DATA5' => 'student@example.test',
                'DATA6' => 'Faculty',
                'DATA7' => 'Programme',
                'EXT_DATA_SOURCE_CATEGORY' => 'Pelajar',
            ]];
        }
    );
    $rows = $source->fetchAll();
    $report($capturedQuery === OdlStudentSource::FIXED_QUERY, 'adapter executes fixed query only');
    $report(count($rows) === 1, 'adapter returns one canonical row');
    $report($rows[0]['data2'] === '900101011234', 'ODL IC is canonicalized');
    $report($rows[0]['data4'] === 'PG001', 'ODL Matrik is canonicalized');
    $report($rows[0]['ext_data_source_category'] === 'Pelajar', 'ODL remains Pelajar');
    $report($rows[0]['source_code'] === 'STUDENT_ODL_PG', 'ODL source code is application-owned');

    $emptyRejected = false;
    try {
        (new OdlStudentSource($config, static fn(): array => []))->fetchAll();
    } catch (RuntimeException $exception) {
        $emptyRejected = $exception->getMessage() === 'ODL_SOURCE_EMPTY';
    }
    $report($emptyRejected, 'empty source fails closed');

    $invalidRowRejected = false;
    try {
        (new OdlStudentSource($config, static fn(): array => ['invalid']))->fetchAll();
    } catch (RuntimeException $exception) {
        $invalidRowRejected = $exception->getMessage() === 'ODL_SOURCE_ROW_INVALID';
    }
    $report($invalidRowRejected, 'non-array row fails closed');

    $invalidCases = [
        ['bad host!', 3308, 'upnm', 'viewer', 'x', $caPath, 5, 'ODL_CONFIG_HOST_INVALID'],
        ['127.0.0.1', 0, 'upnm', 'viewer', 'x', $caPath, 5, 'ODL_CONFIG_PORT_INVALID'],
        ['127.0.0.1', 3308, 'bad-db', 'viewer', 'x', $caPath, 5, 'ODL_CONFIG_DATABASE_INVALID'],
        ['127.0.0.1', 3308, 'upnm', '', 'x', $caPath, 5, 'ODL_CONFIG_CREDENTIAL_INVALID'],
        ['127.0.0.1', 3308, 'upnm', 'viewer', '', $caPath, 5, 'ODL_CONFIG_CREDENTIAL_INVALID'],
        ['127.0.0.1', 3308, 'upnm', 'viewer', 'x', $caPath, 0, 'ODL_CONFIG_TIMEOUT_INVALID'],
        ['127.0.0.1', 3308, 'upnm', 'viewer', 'x', $caPath . '.missing', 5, 'ODL_CONFIG_SSL_CA_INVALID'],
    ];
    foreach ($invalidCases as $case) {
        $rejected = false;
        try {
            new OdlSourceConfig(...array_slice($case, 0, 7));
        } catch (InvalidArgumentException $exception) {
            $rejected = $exception->getMessage() === $case[7];
        }
        $report($rejected, 'invalid config rejected: ' . $case[7]);
    }
} finally {
    unlink($caPath);
}

printf("RESULT checks=%d failed=%d\n", $checks, $failed);
exit($failed === 0 ? 0 : 1);
