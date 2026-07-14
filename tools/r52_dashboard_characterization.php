<?php

/**
 * Static dashboard seam characterization for R5.2D0.
 * No page is executed and no database/session/network is used.
 *
 * Usage:
 *   php tools/r52_dashboard_characterization.php
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(2);
}

$projectRoot = dirname(__DIR__);
$contracts = require $projectRoot . '/tests/characterization/r52_dashboard_contracts.php';
$checks = 0;
$failed = 0;
$report = static function (bool $ok, string $item, string $detail = '') use (&$checks, &$failed): void {
    $checks++;
    if (!$ok) {
        $failed++;
    }
    printf("%s %-48s%s\n", $ok ? 'PASS' : 'FAIL', $item, $detail === '' ? '' : ' ' . $detail);
};

foreach ($contracts as $contract) {
    $relative = $contract['file'];
    $path = $projectRoot . '/' . $relative;
    $contents = is_file($path) ? (string) file_get_contents($path) : '';
    $report($contents !== '', $contract['name'] . ': source available');

    $output = [];
    $exitCode = 1;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1', $output, $exitCode);
    $report($exitCode === 0, $contract['name'] . ': PHP lint');

    $loc = $contents === '' ? 0 : substr_count($contents, "\n");
    $report($loc === $contract['loc'], $contract['name'] . ': LOC', 'actual=' . $loc);

    $qFuncCalls = preg_match_all('/url:\s*[\'\"]\.\.\/lib\/q_func[\'\"]/', $contents);
    $report(
        $qFuncCalls === $contract['q_func_calls'],
        $contract['name'] . ': q_func AJAX seams',
        'actual=' . $qFuncCalls
    );

    $functions = preg_match_all('/^\s*function\s+[A-Za-z_][A-Za-z0-9_]*\s*\(/m', $contents);
    $report(
        $functions === $contract['function_declarations'],
        $contract['name'] . ': inline functions',
        'actual=' . $functions
    );

    $scripts = preg_match_all('/<script\b/i', $contents);
    $report($scripts === $contract['script_tags'], $contract['name'] . ': script tags', 'actual=' . $scripts);

    $missing = [];
    foreach ($contract['fragments'] as $fragment) {
        if (!str_contains($contents, $fragment)) {
            $missing[] = $fragment;
        }
    }
    $report(
        $missing === [],
        $contract['name'] . ': required seams',
        $missing === [] ? '' : 'missing=' . implode(',', $missing)
    );
}

printf("RESULT checks=%d failed=%d\n", $checks, $failed);
exit($failed === 0 ? 0 : 1);
