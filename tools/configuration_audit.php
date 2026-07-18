<?php

if (PHP_SAPI !== 'cli') {
    exit(2);
}

$root = dirname(__DIR__);
require_once $root . '/bootstrap/app.php';
require_once $root . '/bootstrap/sync_runtime.php';

use OneId\App\Sync\SyncFullConfig;
use OneId\App\Sync\SyncOperationalConfig;
use OneId\App\Sync\SyncPilotConfig;
use OneId\App\Sync\SyncRuntimeConfig;

$runtimeFile = oneid_runtime_file_path();
$checks = 0;
$failed = 0;
$report = static function (bool $passed, string $label) use (&$checks, &$failed): void {
    $checks++;
    if (!$passed) {
        $failed++;
    }
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $label);
};

$expectedKeys = [
    'ONEID_APP_URL', 'ONEID_ENVIRONMENT', 'ONEID_APP_DEBUG', 'ONEID_TIMEZONE',
    'ONEID_DB_CHARSET', 'ONEID_LEGACY_MD5_DEADLINE', 'ONEID_SSO_IDP_URL',
    'ONEID_SSO_DASHBOARD_URL', 'ONEID_SAMPLE_DATA_URL', 'ONEID_API_AUTH_MODE',
    'ONEID_API_RATE_LIMIT_PER_MINUTE', 'ONEID_API_CLIENTS_JSON', 'ONEID_DB_DSN',
    'ONEID_DB_USERNAME', 'ONEID_DB_PASSWORD', 'ONEID_SMTP_HOST', 'ONEID_SMTP_PORT',
    'ONEID_SMTP_ENCRYPTION', 'ONEID_SMTP_FROM_NAME', 'ONEID_SMTP_USERNAME',
    'ONEID_SMTP_PASSWORD', 'ONEID_SYNC_APPLY_ENABLED', 'ONEID_SYNC_ENGINE',
    'ONEID_SYNC_TRIGGERED_BY', 'ONEID_SYNC_OPERATIONAL_ENABLED',
    'ONEID_SYNC_OPERATIONAL_WARN_NEW', 'ONEID_SYNC_OPERATIONAL_WARN_UPDATE',
    'ONEID_SYNC_OPERATIONAL_WARN_REACTIVATE', 'ONEID_SYNC_OPERATIONAL_WARN_TOTAL',
    'ONEID_SYNC_OPERATIONAL_MAX_DEACTIVATE', 'ONEID_SYNC_FULL_ENABLED',
    'ONEID_SYNC_FULL_EXPECTED_NEW', 'ONEID_SYNC_FULL_EXPECTED_UPDATE',
    'ONEID_SYNC_FULL_EXPECTED_DEACTIVATE', 'ONEID_SYNC_FULL_EXPECTED_REACTIVATE',
    'ONEID_SYNC_FULL_EXPECTED_PLAN_HASH', 'ONEID_SYNC_PILOT_ENABLED',
    'ONEID_SYNC_PILOT_NEW_LIMIT', 'ONEID_SYNC_PILOT_UPDATE_LIMIT',
    'ONEID_SYNC_PILOT_DEACTIVATE_LIMIT', 'ONEID_SYNC_PILOT_REACTIVATE_LIMIT',
    'ONEID_REHEARSAL_ALLOWED_SERVER_HOSTNAME', 'ONEID_REHEARSAL_ALLOWED_SOURCE_DATABASE',
    'ONEID_STAFF_ODBC_DSN', 'ONEID_STAFF_ODBC_USERNAME', 'ONEID_STAFF_ODBC_PASSWORD',
    'ONEID_STUDENT_SYNC_ODBC_DSN', 'ONEID_STUDENT_SYNC_ODBC_USERNAME',
    'ONEID_STUDENT_SYNC_ODBC_PASSWORD', 'ONEID_STUDENT_LOOKUP_ODBC_DSN',
    'ONEID_STUDENT_LOOKUP_ODBC_USERNAME', 'ONEID_STUDENT_LOOKUP_ODBC_PASSWORD',
    'ONEID_SKP_ODBC_DSN', 'ONEID_SKP_ODBC_USERNAME', 'ONEID_SKP_ODBC_PASSWORD',
    'ONEID_IDMS_ODBC_CONNECTION', 'ONEID_IDMS_ODBC_USERNAME', 'ONEID_IDMS_ODBC_PASSWORD',
    'ONEID_DIAG_AGENT_TOKEN', 'ONEID_DIAG_MYSQL_DSN', 'ONEID_DIAG_MYSQL_USERNAME',
    'ONEID_DIAG_MYSQL_PASSWORD', 'ONEID_DIAG_SYBASE_ODBC_DSN',
    'ONEID_DIAG_SYBASE_USERNAME', 'ONEID_DIAG_SYBASE_PASSWORD', 'ONEID_DIAGNOSTIC_TOKEN',
];

$report(is_file($runtimeFile) && is_readable($runtimeFile), 'runtime store exists and is readable');
if (!is_file($runtimeFile) || !is_readable($runtimeFile)) {
    printf("RESULT checks=%d failed=%d mutation_statements=0\n", $checks, $failed);
    exit(1);
}

$mode = fileperms($runtimeFile) & 0777;
$report(($mode & 0007) === 0 && ($mode & 0030) === 0, 'runtime permissions deny other access and group write/execute');

$source = (string) file_get_contents($runtimeFile);
preg_match_all('/[\'\"](ONEID_[A-Z0-9_]+)[\'\"]\s*=>/', $source, $matches);
$sourceKeys = $matches[1] ?? [];
$duplicates = array_keys(array_filter(array_count_values($sourceKeys), static fn (int $count): bool => $count > 1));
$report($duplicates === [], 'runtime source contains no duplicate keys');
if ($duplicates !== []) {
    printf("INFO duplicate_keys=%s\n", implode(',', $duplicates));
}

$config = require $runtimeFile;
$report(is_array($config), 'runtime store returns an array');
if (!is_array($config)) {
    printf("RESULT checks=%d failed=%d mutation_statements=0\n", $checks, $failed);
    exit(1);
}

$actualKeys = array_keys($config);
$missing = array_values(array_diff($expectedKeys, $actualKeys));
$unknown = array_values(array_diff($actualKeys, $expectedKeys));
$report($missing === [], 'runtime contains all 66 expected keys');
$report($unknown === [], 'runtime contains no unknown keys');
if ($missing !== []) {
    printf("INFO missing_keys=%s\n", implode(',', $missing));
}
if ($unknown !== []) {
    printf("INFO unknown_keys=%s\n", implode(',', $unknown));
}

$value = static fn (string $key): string => trim((string) ($config[$key] ?? ''));
$environment = strtolower($value('ONEID_ENVIRONMENT'));
$report(in_array($environment, ['local', 'staging', 'production'], true), 'environment uses an approved value');
$report(in_array($config['ONEID_APP_DEBUG'] ?? null, [false, true, 'false', 'true', '0', '1'], true), 'application debug flag is explicit');
$report(in_array($value('ONEID_TIMEZONE'), timezone_identifiers_list(), true), 'timezone identifier is valid');
$report(in_array(strtolower($value('ONEID_DB_CHARSET')), ['latin1', 'utf8', 'utf8mb4'], true), 'database charset is allowlisted');

$urlKeys = ['ONEID_APP_URL', 'ONEID_SSO_IDP_URL', 'ONEID_SSO_DASHBOARD_URL', 'ONEID_SAMPLE_DATA_URL'];
$urlsValid = true;
foreach ($urlKeys as $key) {
    $urlsValid = $urlsValid && filter_var($value($key), FILTER_VALIDATE_URL) !== false;
}
if (in_array($environment, ['staging', 'production'], true)) {
    foreach (['ONEID_APP_URL', 'ONEID_SSO_IDP_URL', 'ONEID_SSO_DASHBOARD_URL'] as $key) {
        $urlsValid = $urlsValid && strtolower((string) parse_url($value($key), PHP_URL_SCHEME)) === 'https';
    }
}
$report($urlsValid, 'application and SSO URLs are valid for the environment');

$deadlineValid = true;
try {
    new DateTimeImmutable($value('ONEID_LEGACY_MD5_DEADLINE'));
} catch (Throwable) {
    $deadlineValid = false;
}
$report($deadlineValid, 'legacy password deadline is parseable');

$smtpPort = filter_var($value('ONEID_SMTP_PORT'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
$report($smtpPort !== false && in_array(strtolower($value('ONEID_SMTP_ENCRYPTION')), ['tls', 'ssl'], true), 'SMTP port and encryption are valid');
$report(in_array(strtolower($value('ONEID_API_AUTH_MODE')), ['observe', 'enforce'], true), 'API authentication mode is valid');
$rateLimit = filter_var($value('ONEID_API_RATE_LIMIT_PER_MINUTE'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10000]]);
$clients = json_decode($value('ONEID_API_CLIENTS_JSON'), true);
$report($rateLimit !== false && json_last_error() === JSON_ERROR_NONE && is_array($clients), 'API rate limit and client JSON are valid');

$requiredSecrets = [
    'ONEID_DB_DSN', 'ONEID_DB_USERNAME', 'ONEID_DB_PASSWORD',
    'ONEID_SMTP_USERNAME', 'ONEID_SMTP_PASSWORD',
    'ONEID_STAFF_ODBC_DSN', 'ONEID_STAFF_ODBC_USERNAME', 'ONEID_STAFF_ODBC_PASSWORD',
    'ONEID_STUDENT_SYNC_ODBC_DSN', 'ONEID_STUDENT_SYNC_ODBC_USERNAME', 'ONEID_STUDENT_SYNC_ODBC_PASSWORD',
    'ONEID_STUDENT_LOOKUP_ODBC_DSN', 'ONEID_STUDENT_LOOKUP_ODBC_USERNAME', 'ONEID_STUDENT_LOOKUP_ODBC_PASSWORD',
    'ONEID_SKP_ODBC_DSN', 'ONEID_SKP_ODBC_USERNAME', 'ONEID_SKP_ODBC_PASSWORD',
    'ONEID_IDMS_ODBC_CONNECTION', 'ONEID_IDMS_ODBC_USERNAME', 'ONEID_IDMS_ODBC_PASSWORD',
];
$report(array_reduce($requiredSecrets, static fn (bool $ok, string $key): bool => $ok && $value($key) !== '', true), 'required database and integration credentials are populated');

$syncValid = true;
try {
    $runtime = SyncRuntimeConfig::fromValues($value('ONEID_SYNC_APPLY_ENABLED'), $value('ONEID_SYNC_ENGINE'));
    $pilot = SyncPilotConfig::fromValues(
        $value('ONEID_SYNC_PILOT_ENABLED'), $value('ONEID_SYNC_PILOT_NEW_LIMIT'),
        $value('ONEID_SYNC_PILOT_UPDATE_LIMIT'), $value('ONEID_SYNC_PILOT_DEACTIVATE_LIMIT'),
        $value('ONEID_SYNC_PILOT_REACTIVATE_LIMIT')
    );
    $full = SyncFullConfig::fromValues(
        $value('ONEID_SYNC_FULL_ENABLED'), $value('ONEID_SYNC_FULL_EXPECTED_NEW'),
        $value('ONEID_SYNC_FULL_EXPECTED_UPDATE'), $value('ONEID_SYNC_FULL_EXPECTED_DEACTIVATE'),
        $value('ONEID_SYNC_FULL_EXPECTED_REACTIVATE'), $value('ONEID_SYNC_FULL_EXPECTED_PLAN_HASH')
    );
    $operational = SyncOperationalConfig::fromValues(
        $value('ONEID_SYNC_OPERATIONAL_ENABLED'), $value('ONEID_SYNC_OPERATIONAL_WARN_NEW'),
        $value('ONEID_SYNC_OPERATIONAL_WARN_UPDATE'), $value('ONEID_SYNC_OPERATIONAL_WARN_REACTIVATE'),
        $value('ONEID_SYNC_OPERATIONAL_WARN_TOTAL'), $value('ONEID_SYNC_OPERATIONAL_MAX_DEACTIVATE')
    );
    $modeCount = (int) $pilot->enabled + (int) $full->enabled + (int) $operational->enabled;
    $syncValid = $modeCount <= 1 && (!$runtime->canApply() || $modeCount === 1);
} catch (Throwable) {
    $syncValid = false;
}
$report($syncValid, 'Sync flags, limits and exclusive mode are valid');

printf("RESULT checks=%d failed=%d mutation_statements=0\n", $checks, $failed);
exit($failed === 0 ? 0 : 1);
