<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

$root = dirname(__DIR__);
$checks = 0;
$failures = 0;
$observations = 0;

$report = static function (string $status, string $label) use (&$checks, &$failures, &$observations): void {
    $checks++;
    if ($status === 'FAIL') {
        $failures++;
    } elseif ($status === 'OBSERVE') {
        $observations++;
    }
    printf("%s %s\n", $status, $label);
};

$command = static function (string $command): string {
    $output = shell_exec($command);
    return is_string($output) ? trim($output) : '';
};

$runtimePath = $root . '/.private/runtime.php';
$runtime = is_file($runtimePath) ? require $runtimePath : null;
$report(is_array($runtime) ? 'PASS' : 'FAIL', 'private runtime is readable PHP configuration');

require_once $root . '/config/application.php';
$report(ONEID_APP_VERSION === '2.10.2' ? 'PASS' : 'FAIL', 'application version is approved v2.10.2');
$report(
    is_array($runtime) && ($runtime['ONEID_ENVIRONMENT'] ?? null) === 'production' ? 'PASS' : 'FAIL',
    'runtime environment is production'
);

foreach (['nginx', 'php8.3-fpm'] as $service) {
    $active = $command('systemctl is-active ' . escapeshellarg($service) . ' 2>/dev/null');
    $enabled = $command('systemctl is-enabled ' . escapeshellarg($service) . ' 2>/dev/null');
    $report($active === 'active' && $enabled === 'enabled' ? 'PASS' : 'FAIL', $service . ' is enabled and active');
}

$totalDisk = disk_total_space('/');
$freeDisk = disk_free_space('/');
$usedPercent = is_float($totalDisk) && $totalDisk > 0 && is_float($freeDisk)
    ? (int) round((1 - ($freeDisk / $totalDisk)) * 100)
    : 100;
$report($usedPercent < 80 ? 'PASS' : 'FAIL', 'root filesystem usage is ' . $usedPercent . '% (threshold <80%)');

$meminfo = (string) @file_get_contents('/proc/meminfo');
preg_match('/^MemAvailable:\s+(\d+)\s+kB$/m', $meminfo, $memoryMatch);
$availableMemoryMb = isset($memoryMatch[1]) ? (int) floor(((int) $memoryMatch[1]) / 1024) : 0;
$report($availableMemoryMb >= 1024 ? 'PASS' : 'FAIL', 'available memory is ' . $availableMemoryMb . ' MiB (threshold >=1024 MiB)');

$manifestPath = $root . '/.private/production-config-manifest-20260809.sha256';
$manifestLines = is_readable($manifestPath)
    ? file($manifestPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)
    : false;
$manifestValid = is_array($manifestLines) && $manifestLines !== [];
if (is_array($manifestLines)) {
    foreach ($manifestLines as $line) {
        if (preg_match('/^([a-f0-9]{64})  (\/.+)$/D', $line, $match) !== 1
            || !is_file($match[2])
            || !hash_equals($match[1], (string) hash_file('sha256', $match[2]))
        ) {
            $manifestValid = false;
            break;
        }
    }
}
$report($manifestValid ? 'PASS' : 'FAIL', 'private production configuration manifest matches');

$context = stream_context_create([
    'ssl' => [
        'peer_name' => 'oneid.upnm.edu.my',
        'SNI_enabled' => true,
        'verify_peer' => true,
        'verify_peer_name' => true,
        'capture_peer_cert' => true,
    ],
]);
$socket = @stream_socket_client(
    'ssl://oneid.upnm.edu.my:443',
    $socketError,
    $socketErrorMessage,
    10,
    STREAM_CLIENT_CONNECT,
    $context
);
$certificateDays = -1;
if (is_resource($socket)) {
    $parameters = stream_context_get_params($socket);
    $certificate = $parameters['options']['ssl']['peer_certificate'] ?? null;
    $parsedCertificate = $certificate !== null ? openssl_x509_parse($certificate) : false;
    if (is_array($parsedCertificate) && isset($parsedCertificate['validTo_time_t'])) {
        $certificateDays = (int) floor(((int) $parsedCertificate['validTo_time_t'] - time()) / 86400);
    }
    fclose($socket);
}
$report($certificateDays >= 90 ? 'PASS' : 'FAIL', 'TLS certificate has ' . $certificateDays . ' days remaining (threshold >=90)');

$curl = curl_init('https://oneid.upnm.edu.my/');
curl_setopt_array($curl, [
    CURLOPT_NOBODY => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
]);
$httpResult = curl_exec($curl);
$httpStatus = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
$curlError = curl_error($curl);
curl_close($curl);
$report($httpResult !== false && $httpStatus === 200 ? 'PASS' : 'FAIL', 'HTTPS root returns 200' . ($curlError !== '' ? ' (' . $curlError . ')' : ''));

$myDigitalIdReady = is_array($runtime)
    && in_array(strtolower(trim((string) ($runtime['ONEID_MYDID_ENABLED'] ?? 'false'))), ['true', '1'], true)
    && ($runtime['ONEID_MYDID_REDIRECT_URI'] ?? null)
        === 'https://oneid.upnm.edu.my/auth/mydigitalid/callback.php'
    && !empty($runtime['ONEID_MYDID_CLIENT_SECRET'])
    && !empty($runtime['ONEID_MYDID_IDENTITY_HMAC_KEY_BASE64']);
$report($myDigitalIdReady ? 'PASS' : 'FAIL', 'MyDigital ID runtime activation and production callback are ready');

$rotationFiles = glob($root . '/storage/logs/php-error.log.*') ?: [];
$report(
    $rotationFiles !== [] ? 'PASS' : 'OBSERVE',
    $rotationFiles !== []
        ? 'application log has completed at least one filesystem rotation'
        : 'first automatic application-log rotation is still pending observation'
);

$report(
    is_file('/etc/logrotate.d/oneid-app') ? 'PASS' : 'FAIL',
    'OneID application logrotate configuration exists'
);

printf(
    "RESULT checks=%d failures=%d observations=%d mutation_statements=0 authentication_attempts=0\n",
    $checks,
    $failures,
    $observations
);
exit($failures === 0 ? 0 : 1);
