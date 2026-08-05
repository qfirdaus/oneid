<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(2);
}

define('ONEID_CONFIG_SKIP_DATABASE', true);
require_once dirname(__DIR__) . '/lib/config.php';
require_once dirname(__DIR__) . '/bootstrap/sync_runtime.php';

use OneId\App\Sync\ConditionalSyncCronRunner;
use OneId\App\Sync\SyncCronConfig;
use OneId\App\Sync\SyncRuntimeConfig;

$emit = static function (array $result): void {
    $counts = is_array($result['counts'] ?? null) ? $result['counts'] : [];
    printf(
        '%s source=%s source_rows=%d new=%d update=%d deactivate=%d reactivate=%d header=%d correlation=%s code=%s%s',
        (string) ($result['outcome'] ?? 'FAILED'),
        preg_replace('/[^A-Z0-9_]/', '', (string) ($result['source'] ?? 'NONE')),
        (int) ($result['source_rows'] ?? 0),
        (int) ($counts['New'] ?? 0),
        (int) ($counts['Update'] ?? 0),
        (int) ($counts['Deactivate'] ?? 0),
        (int) ($counts['Reactivate'] ?? 0),
        (int) ($result['header_id'] ?? 0),
        preg_replace('/[^a-f0-9]/', '', (string) ($result['correlation_id'] ?? '')),
        preg_replace('/[^A-Z0-9_]/', '', (string) ($result['code'] ?? 'NONE')),
        PHP_EOL
    );
};

try {
    $config = SyncCronConfig::fromEnvironment();
    if (!$config->enabled) {
        $emit(['outcome' => 'SYNC_CRON_DISABLED']);
        exit(0);
    }
    require_once dirname(__DIR__) . '/lib/readonly_odbc.php';
    $operation = new Database();
    $runner = new ConditionalSyncCronRunner(
        $operation,
        $config,
        SyncRuntimeConfig::fromEnvironment()
    );
    foreach ($config->sources as $sourceCode) {
        $result = $runner->runSource($sourceCode);
        $emit($result);
        if (in_array($result['outcome'], [
            'BLOCKED_REQUIRES_ADMIN',
            'BLOCKED_PLAN_DRIFT',
            'APPLIED_AUDIT_WARNING',
        ], true)) {
            exit(match ($result['outcome']) {
                'BLOCKED_REQUIRES_ADMIN' => 20,
                'BLOCKED_PLAN_DRIFT' => 21,
                default => 22,
            });
        }
    }
    exit(0);
} catch (Throwable $exception) {
    $correlation = bin2hex(random_bytes(8));
    $known = [
        'SYNC_CRON_DISABLED', 'SYNC_CRON_FLAG_INVALID',
        'SYNC_CRON_DEACTIVATE_LIMIT_INVALID', 'SYNC_CRON_SOURCES_INVALID',
        'SYNC_CRON_IDENTITY_INVALID', 'SYNC_CRON_THRESHOLD_INVALID',
        'SYNC_CRON_COUNTS_INVALID', 'SYNC_CRON_SOURCE_NOT_ENABLED',
        'SYNC_CRON_STAFF_PROVENANCE_REQUIRED', 'SYNC_CRON_APPLY_DISABLED',
        'SYNC_APPLY_DISABLED', 'SYNC_APPLY_FLAG_INVALID', 'SYNC_ENGINE_INVALID',
        'SYNC_FLAG_COMBINATION_INVALID', 'SYNC_SOURCE_INVALID',
        'SYNC_SOURCE_BASELINE_INVALID', 'SYNC_ALREADY_RUNNING',
        'SYNC_APPROVAL_PLAN_MISMATCH', 'SYNC_SAFETY_BLOCKED',
        'SYNC_RECONCILIATION_MISMATCH', 'ODBC_EXTENSION_UNAVAILABLE',
        'EXTERNAL_STAFF_CONNECTION_FAILED', 'EXTERNAL_STAFF_QUERY_FAILED',
        'EXTERNAL_STAFF_EMPTY', 'EXTERNAL_STUDENT_CONNECTION_FAILED',
        'EXTERNAL_STUDENT_QUERY_FAILED', 'EXTERNAL_STUDENT_EMPTY',
        'ODL_SOURCE_CONNECTION_FAILED', 'ODL_SOURCE_QUERY_FAILED',
        'ODL_SOURCE_EMPTY', 'ODL_TLS_NOT_ACTIVE',
    ];
    $code = in_array($exception->getMessage(), $known, true)
        ? $exception->getMessage()
        : 'SYNC_CRON_UNEXPECTED_FAILURE';
    error_log(sprintf(
        '[ONEID_SYNC_CRON] correlation=%s exception=%s code=%s',
        $correlation,
        get_class($exception),
        $code
    ));
    $emit([
        'outcome' => 'FAILED',
        'correlation_id' => $correlation,
        'code' => $code,
    ]);
    exit(1);
}
