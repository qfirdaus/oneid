<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/config.php';
require_once dirname(__DIR__) . '/app/Locale/ApiResponseLocalizer.php';

use OneId\App\Locale\ApiResponseLocalizer;

$root = dirname(__DIR__);
$files = array_merge(
    [$root . '/lib/q_func.php'],
    glob($root . '/app/Admin/*.php') ?: [],
    glob($root . '/app/User/*.php') ?: []
);
$codes = [];
foreach ($files as $file) {
    $source = (string) file_get_contents($file);
    preg_match_all(
        '/[\'"]code[\'"]\s*=>\s*[\'"]([A-Z][A-Z0-9_]{3,})[\'"]/',
        $source,
        $responseMatches
    );
    preg_match_all(
        '/(?:Exception|RuntimeException)\([\'"]([A-Z][A-Z0-9_]{3,})[\'"]/',
        $source,
        $exceptionMatches
    );
    foreach (array_merge($responseMatches[1], $exceptionMatches[1]) as $code) {
        $codes[$code] = true;
    }
}
ksort($codes);

$mapped = [];
$excluded = [];
$unresolved = [];
foreach (array_keys($codes) as $code) {
    $key = ApiResponseLocalizer::translationKeyFor($code);
    if ($key !== null) {
        $mapped[$code] = $key;
    } elseif (ApiResponseLocalizer::isExcludedCode($code)) {
        $excluded[] = $code;
    } else {
        $unresolved[] = $code;
    }
}

$result = [
    'mode' => 'ml6_active_response_inventory',
    'files_scanned' => count($files),
    'active_codes' => count($codes),
    'mapped_codes' => count($mapped),
    'excluded_boundary_codes' => count($excluded),
    'unresolved_codes' => count($unresolved),
    'unresolved' => $unresolved,
    'mapping_digest' => hash('sha256', json_encode($mapped, JSON_THROW_ON_ERROR)),
    'mutation_statements' => 0,
];
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
exit($unresolved === [] ? 0 : 1);
