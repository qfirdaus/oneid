<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(2);

$root = dirname(__DIR__);
require_once $root . '/lib/config.php';
require_once $root . '/bootstrap/sync_runtime.php';

use OneId\App\Sync\Apel\ApelShadowPreviewConfig;
use OneId\App\Sync\Apel\ApelShadowPreviewService;
use OneId\App\Sync\Apel\ApelSourceConfig;
use OneId\App\Sync\Apel\ApelStudentSource;
use OneId\App\Sync\Odl\OdlShadowPreviewReader;
use OneId\App\Sync\SourceAware\SourceAwareSafetyPolicy;
use OneId\App\Sync\SourceAware\SourceAwareStudentPlanner;

try {
    $pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $service = new ApelShadowPreviewService(
        ApelShadowPreviewConfig::fromPrivateRuntime(),
        new ApelStudentSource(ApelSourceConfig::fromPrivateRuntime()),
        new OdlShadowPreviewReader($pdo),
        new SourceAwareStudentPlanner(new SourceAwareSafetyPolicy())
    );
    echo json_encode(
        $service->preview(),
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    ), "\n";
} catch (Throwable $exception) {
    $known = [
        'APEL_SHADOW_PREVIEW_DISABLED',
        'APEL_SHADOW_PREVIEW_FLAG_INVALID',
        'APEL_SHADOW_BASELINE_INVALID',
        'APEL_PDO_MYSQL_TLS_UNAVAILABLE',
        'APEL_SOURCE_CONNECTION_FAILED',
        'APEL_TLS_NOT_ACTIVE',
        'APEL_SOURCE_QUERY_FAILED',
        'APEL_SOURCE_EMPTY',
    ];
    $code = in_array($exception->getMessage(), $known, true)
        ? $exception->getMessage() : 'APEL_SHADOW_PREVIEW_FAILED';
    printf("RESULT ready=no error=%s can_apply=false mutation_statements=0\n", $code);
    exit(1);
}
