<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

define('ONEID_CONFIG_SKIP_DATABASE', true);
require_once dirname(__DIR__) . '/lib/config.php';

$checks = 0;
$failures = 0;
$observations = 0;
$report = static function (string|bool $status, string $label, string $detail = '') use (&$checks, &$failures, &$observations): void {
    if (is_bool($status)) {
        $status = $status ? 'PASS' : 'FAIL';
    }
    $checks++;
    if ($status === 'FAIL') {
        $failures++;
    } elseif ($status === 'OBSERVE') {
        $observations++;
    }
    printf("%s %s%s\n", $status, $label, $detail === '' ? '' : ' ' . $detail);
};

$runtimePath = oneid_runtime_file_path();
$runtime = is_readable($runtimePath) ? require $runtimePath : null;
$report(is_array($runtime) ? 'PASS' : 'FAIL', 'private runtime is readable');
if (!is_array($runtime)) {
    printf("RESULT checks=%d failures=%d observations=%d mutation_statements=0\n", $checks, $failures, $observations);
    exit(1);
}

$value = static fn(string $key, mixed $fallback = ''): string => trim((string) oneid_config($key, $fallback));
$bool = static fn(string $key, bool $fallback = false): bool => filter_var(
    oneid_config($key, $fallback ? 'true' : 'false'),
    FILTER_VALIDATE_BOOLEAN
);

$report(
    $value('ONEID_ENVIRONMENT') === 'production'
        && $value('ONEID_APP_URL') === 'https://oneid.upnm.edu.my'
        && !$bool('ONEID_APP_DEBUG'),
    'production identity, canonical URL and debug mode are correct'
);

$unsafeMutationFlags = [
    'ONEID_SYNC_OPERATIONAL_ENABLED',
    'ONEID_ODL_OPERATIONAL_APPLY_ENABLED',
    'ONEID_ODL_OPERATIONAL_ON_DEMAND_ENABLED',
];
$enabledUnsafeFlags = array_values(array_filter($unsafeMutationFlags, static fn(string $key): bool => $bool($key)));
$cronEnabled = $bool('ONEID_SYNC_CRON_ENABLED');
$cronDryRun = $bool('ONEID_SYNC_CRON_DRY_RUN', true);
$syncApply = $bool('ONEID_SYNC_APPLY_ENABLED');
$syncEngine = $value('ONEID_SYNC_ENGINE');
$cronAllowAll = $bool('ONEID_SYNC_CRON_ALLOW_ALL_SAFE_CHANGES');
$cronMaxDeactivate = filter_var(
    $value('ONEID_SYNC_CRON_MAX_DEACTIVATE', '0'),
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 0, 'max_range' => 50]]
);
$dormantSync = !$syncApply && $syncEngine === 'disabled' && (!$cronEnabled || $cronDryRun);
$controlledCronApply = $cronEnabled && !$cronDryRun
    && $syncApply && $syncEngine === 'safe'
    && !$cronAllowAll
    && !$bool('ONEID_SYNC_PILOT_ENABLED')
    && !$bool('ONEID_SYNC_FULL_ENABLED')
    && $cronMaxDeactivate !== false;
$unrestrictedCronApply = $cronEnabled && !$cronDryRun
    && $syncApply && $syncEngine === 'safe'
    && $cronAllowAll
    && !$bool('ONEID_SYNC_PILOT_ENABLED')
    && !$bool('ONEID_SYNC_FULL_ENABLED');
$syncPosture = $unrestrictedCronApply
    ? 'unrestricted-cron-apply'
    : ($controlledCronApply ? 'controlled-cron-apply' : 'dormant');
$report(
    $enabledUnsafeFlags === [] && ($dormantSync || $controlledCronApply || $unrestrictedCronApply),
    'sync mutation posture is explicitly configured',
    'mode=' . $syncPosture
);
if ($unrestrictedCronApply) {
    $report(
        'OBSERVE',
        'cron volume thresholds and warnings are bypassed by deployment configuration'
    );
}

if ($bool('ONEID_SYNC_CRON_ENABLED')) {
    exec('systemctl is-enabled cron 2>/dev/null', $cronEnabledOutput, $cronEnabledCode);
    exec('systemctl is-active cron 2>/dev/null', $cronActiveOutput, $cronActiveCode);
    exec('crontab -l 2>/dev/null', $crontabOutput, $crontabCode);
    $crontab = implode("\n", $crontabOutput);
    $scheduleReady = $crontabCode === 0
        && preg_match(
            '#^10 \* \* \* \* .*/var/www/oneid/cron/run_conditional_external_sync\.php.*external-sync-cron\.log 2>&1$#m',
            $crontab
        ) === 1;
    $logrotatePath = '/etc/logrotate.d/oneid-external-sync';
    $logrotate = is_readable($logrotatePath) ? (string) file_get_contents($logrotatePath) : '';
    $report(
        $cronEnabledCode === 0 && $cronActiveCode === 0 && $scheduleReady,
        'production sync cron is installed and the cron service is active'
    );
    $report(
        str_contains($logrotate, '/var/www/oneid/storage/logs/external-sync-cron.log')
            && preg_match('/^\s*su\s+iqs\s+www-data\s*$/m', $logrotate) === 1,
        'production external-sync cron logrotate is installed'
    );
}

$clientId = $value('ONEID_API_INTERNAL_CLIENT_ID');
$clientSecret = $value('ONEID_API_INTERNAL_CLIENT_SECRET');
$clients = json_decode($value('ONEID_API_CLIENTS_JSON', '{}'), true);
$client = is_array($clients) && isset($clients[$clientId]) && is_array($clients[$clientId])
    ? $clients[$clientId]
    : null;
$internalClientReady = $clientId === 'oneid-internal-production'
    && $clientSecret !== ''
    && is_array($client)
    && ($client['status'] ?? '') === 'active'
    && hash_equals((string) ($client['secret_hash'] ?? ''), hash('sha256', $clientSecret))
    && in_array('sso_token', $client['endpoints'] ?? [], true)
    && in_array('sso:validate', $client['scopes'] ?? [], true)
    && in_array('172.16.2.109', $client['ip_allowlist'] ?? [], true);
$report($internalClientReady ? 'PASS' : 'FAIL', 'internal integration client is provisioned and source-restricted');

try {
    $pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $identity = $pdo->query(
        'SELECT DATABASE() database_name,@@hostname server_hostname,@@server_uuid server_uuid,
                USER() connection_user,CURRENT_USER() matched_account'
    )->fetch();
    $databaseReady = ($identity['database_name'] ?? '') === 'oneiddb_v2'
        && ($identity['server_uuid'] ?? '') !== '683e6fb3-fbc1-11ef-9f5c-fefcfeb48ebf';
    $report(
        $databaseReady ? 'PASS' : 'FAIL',
        'application is connected to the dedicated production database',
        sprintf(
            'database=%s server=%s account=%s',
            (string) ($identity['database_name'] ?? 'unknown'),
            (string) ($identity['server_hostname'] ?? 'unknown'),
            (string) ($identity['matched_account'] ?? 'unknown')
        )
    );

    $grants = array_map(
        static fn(array $row): string => (string) array_values($row)[0],
        $pdo->query('SHOW GRANTS')->fetchAll()
    );
    $grantText = strtoupper(implode("\n", $grants));
    $leastPrivilege = !str_contains($grantText, 'ALL PRIVILEGES')
        && !str_contains($grantText, 'GRANT OPTION')
        && str_contains($grantText, 'SELECT')
        && str_contains($grantText, 'INSERT')
        && str_contains($grantText, 'UPDATE')
        && str_contains($grantText, 'DELETE');
    $report($leastPrivilege ? 'PASS' : 'FAIL', 'database runtime account uses DML-only least privilege');

    $maintenance = $pdo->query(
        'SELECT maintenance_mode FROM sys_config WHERE singleton_key=1'
    )->fetchColumn();
    $report($maintenance === 'OFF' ? 'PASS' : 'FAIL', 'maintenance mode is OFF');

    $staleActiveTokens = (int) $pdo->query(
        'SELECT COUNT(*) FROM token_tbl
          WHERE status=1
            AND COALESCE(token_issued_at,token_datetime)<NOW()-INTERVAL 8 HOUR'
    )->fetchColumn();
    $report(
        $staleActiveTokens === 0 ? 'PASS' : 'FAIL',
        'no active session older than the absolute eight-hour boundary',
        'rows=' . $staleActiveTokens
    );

    $appSummary = $pdo->query(
        'SELECT COUNT(*) total,
                SUM(avail_status=1) active,
                SUM(avail_status=1 AND production_ready=1
                    AND COALESCE(TRIM(production_domain),\'\')<>\'\') ready
           FROM sp_list'
    )->fetch();
    $allAppsReady = (int) ($appSummary['active'] ?? 0) > 0
        && (int) ($appSummary['active'] ?? 0) === (int) ($appSummary['ready'] ?? -1);
    $report(
        $allAppsReady ? 'PASS' : 'FAIL',
        'every active application has an approved production URL',
        sprintf('active=%d ready=%d', (int) ($appSummary['active'] ?? 0), (int) ($appSummary['ready'] ?? 0))
    );

    $activeIdentitySql = <<<'SQL'
WITH identities AS (
    SELECT REPLACE(REPLACE(TRIM(data4), '-', ''), ' ', '') identity_value
      FROM user_tbl
     WHERE avail_status=1 AND TRIM(COALESCE(data3, ''))<>''
       AND REPLACE(REPLACE(TRIM(COALESCE(data4, '')), '-', ''), ' ', '') REGEXP '^[0-9]{12}$'
    UNION ALL
    SELECT REPLACE(REPLACE(TRIM(data2), '-', ''), ' ', '') identity_value
      FROM user_tbl
     WHERE avail_status=1 AND TRIM(COALESCE(data3, ''))=''
       AND REPLACE(REPLACE(TRIM(COALESCE(data2, '')), '-', ''), ' ', '') REGEXP '^[0-9]{12}$'
)
SELECT COUNT(*) identity_rows,COUNT(DISTINCT identity_value) unique_identities FROM identities
SQL;
    $identitySummary = $pdo->query($activeIdentitySql)->fetch();
    $identityRows = (int) ($identitySummary['identity_rows'] ?? 0);
    $identityUnique = (int) ($identitySummary['unique_identities'] ?? 0);
    $report(
        $identityRows > 0 && $identityRows === $identityUnique ? 'PASS' : 'FAIL',
        'active MyDigital ID identity population has no collision',
        sprintf('rows=%d unique=%d', $identityRows, $identityUnique)
    );

    $readyApps = $pdo->query(
        'SELECT sp_id,production_domain FROM sp_list
          WHERE avail_status=1 AND production_ready=1
            AND COALESCE(TRIM(production_domain),\'\')<>\'\''
    )->fetchAll();
    $tlsFailures = [];
    foreach ($readyApps as $app) {
        $curl = curl_init((string) $app['production_domain']);
        curl_setopt_array($curl, [
            CURLOPT_NOBODY => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $result = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        if ($result === false || $status === 0) {
            $tlsFailures[] = (string) $app['sp_id'];
        }
    }
    $report(
        $tlsFailures === [] ? 'PASS' : 'FAIL',
        'all production-ready application URLs pass TLS verification',
        $tlsFailures === [] ? '' : 'failed=' . implode(',', $tlsFailures)
    );
} catch (Throwable $exception) {
    $report('FAIL', 'production database readiness checks complete', 'reason=' . get_class($exception));
}

$curl = curl_init('https://oneid.upnm.edu.my/');
curl_setopt_array($curl, [
    CURLOPT_NOBODY => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER => true,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
]);
$headers = curl_exec($curl);
$httpStatus = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
$tlsResult = (int) curl_getinfo($curl, CURLINFO_SSL_VERIFYRESULT);
curl_close($curl);
$hstsReady = is_string($headers)
    && preg_match('/^strict-transport-security:\s*max-age=31536000\s*$/mi', $headers) === 1;
$report(
    $httpStatus === 200 && $tlsResult === 0 && $hstsReady ? 'PASS' : 'FAIL',
    'canonical HTTPS endpoint has valid TLS, HTTP 200 and one-year HSTS'
);

$backupDirectories = glob(dirname(__DIR__) . '/storage/backups/PROD-PRECUTOVER-*', GLOB_ONLYDIR) ?: [];
$backupReady = false;
foreach ($backupDirectories as $directory) {
    $checksumFile = $directory . '/SHA256SUMS';
    $lines = is_readable($checksumFile) ? file($checksumFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : false;
    if (!is_array($lines) || count($lines) !== 1
        || preg_match('/^([a-f0-9]{64})  ([A-Za-z0-9_.-]+)$/D', $lines[0], $match) !== 1
    ) {
        continue;
    }
    $dump = $directory . '/' . $match[2];
    if (is_file($dump) && hash_equals($match[1], (string) hash_file('sha256', $dump))) {
        $backupReady = true;
        break;
    }
}
$report($backupReady ? 'PASS' : 'FAIL', 'checksummed pre-cutover database backup exists');

$apiMode = $value('ONEID_API_AUTH_MODE', 'observe');
$report(
    $apiMode === 'enforce' ? 'PASS' : 'OBSERVE',
    'integration API enforcement state',
    'mode=' . $apiMode
);

printf(
    "RESULT checks=%d failures=%d observations=%d mutation_statements=0 authentication_attempts=0\n",
    $checks,
    $failures,
    $observations
);
exit($failures === 0 ? 0 : 1);
