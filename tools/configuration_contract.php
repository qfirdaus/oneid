<?php

if (PHP_SAPI !== 'cli') {
    exit(2);
}

$root = dirname(__DIR__);
$checks = 0;
$failed = 0;
$report = static function (bool $passed, string $label) use (&$checks, &$failed): void {
    $checks++;
    if (!$passed) {
        $failed++;
    }
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $label);
};

$files = [
    'bootstrap/runtime_file.php', 'config/runtime.php', 'lib/secrets.php',
    'docs/examples/oneid-secrets.example.php', 'tools/configuration_audit.php',
];
foreach ($files as $file) {
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($root . '/' . $file), $output, $code);
    $report($code === 0, 'PHP lint ' . $file);
    $output = [];
}

$resolver = (string) file_get_contents($root . '/bootstrap/runtime_file.php');
$runtime = (string) file_get_contents($root . '/config/runtime.php');
$secrets = (string) file_get_contents($root . '/lib/secrets.php');
$audit = (string) file_get_contents($root . '/tools/configuration_audit.php');
$template = (string) file_get_contents($root . '/docs/examples/oneid-secrets.example.php');

$report(
    str_contains($resolver, "getenv('ONEID_RUNTIME_FILE')")
        && str_contains($resolver, "getenv('ONEID_SECRETS_FILE')")
        && str_contains($resolver, 'must reference the same file'),
    'one resolver supports the primary path and rejects a conflicting legacy path'
);
$report(
    str_contains($runtime, 'oneid_runtime_file_path()')
        && str_contains($secrets, 'oneid_runtime_file_path()'),
    'configuration and secrets use the same runtime resolver'
);

$command = sprintf(
    '%s -r %s',
    escapeshellarg(PHP_BINARY),
    escapeshellarg(
        "putenv('ONEID_RUNTIME_FILE=/tmp/oneid-primary.php');"
        . "putenv('ONEID_SECRETS_FILE=/tmp/oneid-legacy.php');"
        . "require " . var_export($root . '/bootstrap/runtime_file.php', true) . ";"
        . "try { oneid_runtime_file_path(); echo 'not-blocked'; }"
        . " catch (RuntimeException \$e) { echo \$e->getMessage(); }"
    )
);
exec($command, $output, $code);
$report($code === 0 && implode("\n", $output) === 'ONEID_RUNTIME_FILE and ONEID_SECRETS_FILE must reference the same file.', 'conflicting runtime paths fail closed');

preg_match_all('/[\'\"](ONEID_[A-Z0-9_]+)[\'\"]\s*=>/', $template, $matches);
$templateKeys = $matches[1] ?? [];
$report(count($templateKeys) === 66 && count(array_unique($templateKeys)) === 66, 'committed grouped template contains 66 unique keys');
$report(
    str_contains($template, 'ONEID_SYNC_OPERATIONAL_WARN_NEW')
        && str_contains($template, 'ONEID_SYNC_FULL_EXPECTED_PLAN_HASH')
        && str_contains($template, 'ONEID_REHEARSAL_ALLOWED_SERVER_HOSTNAME'),
    'template covers current Operational, Full and rehearsal configuration'
);
$report(
    str_contains($audit, 'runtime source contains no duplicate keys')
        && str_contains($audit, 'runtime contains all 66 expected keys')
        && str_contains($audit, 'mutation_statements=0')
        && !preg_match('/\b(?:INSERT|UPDATE|DELETE|REPLACE|TRUNCATE)\b/i', $audit),
    'configuration audit checks source structure without mutation statements'
);
$report(
    str_contains((string) file_get_contents($root . '/deployment/nginx/oneid-staging.conf'), 'root /var/www/oneid-uat/public;')
        && str_contains((string) file_get_contents($root . '/deployment/php-fpm/oneid-uat.pool.conf'), '/var/www/oneid-uat/.private/runtime.php'),
    'committed staging templates match the active project path'
);

exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/tools/configuration_audit.php'), $output, $code);
$report($code === 0 && in_array('RESULT checks=17 failed=0 mutation_statements=0', $output, true), 'local configuration audit passes read-only');

printf("RESULT checks=%d failed=%d\n", $checks, $failed);
exit($failed === 0 ? 0 : 1);
