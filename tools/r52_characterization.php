<?php

/**
 * Read-only characterization test for R5.2 application-layer restructuring.
 *
 * Usage:
 *   php tools/r52_characterization.php https://oneid.local --insecure
 *   php tools/r52_characterization.php https://oneid-next.local
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(2);
}

$projectRoot = dirname(__DIR__);
$contracts = require $projectRoot . '/tests/characterization/r52_contracts.php';
$baseUrl = rtrim((string) ($argv[1] ?? ''), '/');
$insecure = in_array('--insecure', $argv, true);

if ($baseUrl === '' || filter_var($baseUrl, FILTER_VALIDATE_URL) === false) {
    fwrite(STDERR, "Usage: php tools/r52_characterization.php <base-url> [--insecure]\n");
    exit(2);
}

$failed = 0;
$checks = 0;

$report = static function (bool $ok, string $category, string $item, string $detail = '') use (&$failed, &$checks): void {
    $checks++;
    if (!$ok) {
        $failed++;
    }
    printf(
        "%s %-10s %-48s%s\n",
        $ok ? 'PASS' : 'FAIL',
        $category,
        $item,
        $detail === '' ? '' : ' ' . $detail
    );
};

$linted = [];
foreach ($contracts['wrappers'] as $wrapper) {
    $publicRelative = $wrapper['public'];
    $targetRelative = $wrapper['target'];
    $publicPath = $projectRoot . '/' . $publicRelative;
    $targetPath = $projectRoot . '/' . $targetRelative;

    $publicOk = is_file($publicPath) && !is_link($publicPath);
    $targetOk = is_file($targetPath) && !is_link($targetPath);
    $contents = $publicOk ? (string) file_get_contents($publicPath) : '';
    $fragmentOk = $contents !== '' && str_contains($contents, $wrapper['fragment']);
    $report($publicOk && $targetOk && $fragmentOk, 'wrapper', $publicRelative, 'target=' . $targetRelative);

    foreach ([$publicPath, $targetPath] as $lintPath) {
        if (isset($linted[$lintPath]) || !is_file($lintPath)) {
            continue;
        }
        $linted[$lintPath] = true;
        $output = [];
        $exitCode = 1;
        exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($lintPath) . ' 2>&1', $output, $exitCode);
        $report($exitCode === 0, 'php-lint', substr($lintPath, strlen($projectRoot) + 1));
    }
}

$symlinkCount = 0;
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($projectRoot . '/public', FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);
foreach ($iterator as $item) {
    if ($item->isLink()) {
        $symlinkCount++;
    }
}
$report($symlinkCount === 0, 'filesystem', 'public symlink count', 'actual=' . $symlinkCount . ' expected=0');

$request = static function (string $url) use ($insecure): array {
    $handle = curl_init($url);
    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYHOST => $insecure ? 0 : 2,
        CURLOPT_SSL_VERIFYPEER => !$insecure,
    ]);
    $raw = curl_exec($handle);
    $error = curl_error($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
    $contentType = strtolower((string) curl_getinfo($handle, CURLINFO_CONTENT_TYPE));
    $headerSize = (int) curl_getinfo($handle, CURLINFO_HEADER_SIZE);
    curl_close($handle);

    $headers = is_string($raw) ? substr($raw, 0, $headerSize) : '';
    $location = '';
    if (preg_match('/^Location:\s*(.+)$/mi', $headers, $matches) === 1) {
        $location = trim($matches[1]);
    }

    return compact('error', 'status', 'contentType', 'location');
};

foreach ($contracts['http'] as $contract) {
    $result = $request($baseUrl . $contract['path']);
    $statusOk = in_array($result['status'], $contract['statuses'], true);
    $typeOk = str_starts_with($result['contentType'], $contract['type']);
    $locationOk = true;
    if (isset($contract['location'])) {
        $expectedLocation = $baseUrl . $contract['location'];
        $locationOk = $result['location'] === $expectedLocation;
    }
    $ok = $result['error'] === '' && $statusOk && $typeOk && $locationOk;
    $report(
        $ok,
        'http',
        $contract['path'],
        sprintf('status=%d type=%s location=%s', $result['status'], $result['contentType'] ?: '-', $result['location'] ?: '-')
    );
}

foreach ($contracts['boundary'] as $path) {
    $result = $request($baseUrl . $path);
    $ok = $result['error'] === '' && $result['status'] === 404;
    $report($ok, 'boundary', $path, 'status=' . $result['status']);
}

printf("RESULT checks=%d failed=%d\n", $checks, $failed);
exit($failed === 0 ? 0 : 1);
