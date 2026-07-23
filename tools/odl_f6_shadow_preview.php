<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(2);

require_once dirname(__DIR__) . '/lib/config.php';
require_once dirname(__DIR__) . '/lib/readonly_odbc.php';
require_once dirname(__DIR__) . '/bootstrap/sync_runtime.php';

use OneId\App\Sync\Odl\OdlShadowPreviewConfig;
use OneId\App\Sync\Odl\OdlShadowPreviewReader;
use OneId\App\Sync\Odl\OdlShadowPreviewService;
use OneId\App\Sync\Odl\OdlSourceConfig;
use OneId\App\Sync\Odl\OdlStudentSource;
use OneId\App\Sync\Odl\StaffSource;
use OneId\App\Sync\Odl\UgStudentSource;
use OneId\App\Sync\SourceAware\SourceAwareSafetyPolicy;
use OneId\App\Sync\SourceAware\SourceAwareStudentPlanner;

try {
    $pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $service = new OdlShadowPreviewService(
        OdlShadowPreviewConfig::fromPrivateRuntime(),
        new StaffSource(),
        new OdlStudentSource(OdlSourceConfig::fromPrivateRuntime()),
        new UgStudentSource(),
        new OdlShadowPreviewReader($pdo),
        new SourceAwareStudentPlanner(new SourceAwareSafetyPolicy())
    );
    echo json_encode(
        $service->preview(),
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    ), "\n";
} catch (Throwable $exception) {
    $allowed = [
        'ODL_SHADOW_PREVIEW_DISABLED',
        'ODL_SHADOW_PREVIEW_FLAG_INVALID',
        'ODL_SHADOW_BASELINE_INVALID',
        'ODL_PDO_MYSQL_TLS_UNAVAILABLE',
        'ODL_SOURCE_CONNECTION_FAILED',
        'ODL_TLS_NOT_ACTIVE',
        'ODL_SOURCE_QUERY_FAILED',
        'ODBC_EXTENSION_UNAVAILABLE',
        'EXTERNAL_STUDENT_CONNECTION_FAILED',
        'EXTERNAL_STUDENT_QUERY_FAILED',
    ];
    $code = in_array($exception->getMessage(), $allowed, true)
        ? $exception->getMessage() : 'ODL_SHADOW_PREVIEW_FAILED';
    printf(
        "RESULT ready=no error=%s can_apply=false mutation_statements=0\n",
        $code
    );
    exit(1);
}
