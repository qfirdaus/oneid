<?php

declare(strict_types=1);

$source = (string) file_get_contents(dirname(__DIR__, 2) . '/tools/production_go_live_readiness.php');
$checks = [
    'tool is CLI-only' => str_contains($source, "PHP_SAPI !== 'cli'"),
    'tool skips application mutation bootstrap' => str_contains($source, "define('ONEID_CONFIG_SKIP_DATABASE', true)"),
    'tool checks dedicated production database' => str_contains($source, "'oneiddb_v2'"),
    'tool checks FPM session retention and warning activation' => str_contains($source, 'PHP-FPM retains session files')
        && str_contains($source, '$fpmSessionLifetime >= 28800')
        && str_contains($source, 'ONEID_USER_SESSION_WARNING_ENABLED'),
    'tool rejects the known staging server UUID' => str_contains($source, '683e6fb3-fbc1-11ef-9f5c-fefcfeb48ebf'),
    'tool checks DML-only grants' => str_contains($source, "'GRANT OPTION'") && str_contains($source, "'ALL PRIVILEGES'"),
    'tool checks every active application readiness' => str_contains($source, 'every active application has an approved production URL'),
    'tool classifies every approved sync posture explicitly' => str_contains($source, '$dormantSync')
        && str_contains($source, '$controlledCronApply')
        && str_contains($source, '$unrestrictedCronApply')
        && str_contains($source, "ONEID_SYNC_CRON_MAX_DEACTIVATE', '0'"),
    'tool exposes unrestricted cron apply as an observation' => str_contains($source, 'unrestricted-cron-apply')
        && str_contains($source, 'cron volume thresholds and warnings are bypassed'),
    'tool checks production cron installation and log rotation' => str_contains($source, 'systemctl is-active cron')
        && str_contains($source, '/etc/logrotate.d/oneid-external-sync')
        && str_contains($source, "su\\s+iqs\\s+www-data"),
    'tool checks production application TLS' => str_contains($source, 'all production-ready application URLs pass TLS verification'),
    'tool checks MyDigital ID collisions without raw identities' => str_contains($source, 'active MyDigital ID identity population has no collision'),
    'tool checks HSTS' => str_contains($source, 'strict-transport-security'),
    'tool checks backup checksum' => str_contains($source, "hash_file('sha256', \$dump)"),
    'tool declares zero mutation and authentication attempts' => str_contains($source, 'mutation_statements=0 authentication_attempts=0'),
    'tool contains no SQL mutation statement' => preg_match('/[\'\"](?:INSERT|UPDATE|DELETE|REPLACE|TRUNCATE|ALTER|DROP|CREATE)\s/i', $source) !== 1,
];

$failed = 0;
foreach ($checks as $label => $passed) {
    if (!$passed) {
        $failed++;
    }
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $label);
}

printf("RESULT checks=%d failures=%d network_calls=0 database_mutations=0\n", count($checks), $failed);
exit($failed === 0 ? 0 : 1);
