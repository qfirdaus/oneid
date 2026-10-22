<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$checks = [];
$read = static fn (string $path): string => (string) file_get_contents($root . '/' . $path);

$activeFiles = [
    'tools/release/deploy-production-code-staged.py',
    'deployment/README.md',
    'deployment/nginx/oneid-production.conf',
    'deployment/nginx/oneid-staging.conf',
    'deployment/php-fpm/oneid-uat.pool.conf',
];

foreach ($activeFiles as $file) {
    $checks["{$file} has no active PHP 8.3 runtime reference"] = preg_match(
        '/php8\.3|php\/8\.3|8\.3-fpm/i',
        $read($file)
    ) !== 1;
}

$deploy = $read('tools/release/deploy-production-code-staged.py');
$checks['production deploy requires the PHP 8.4 CLI'] = str_contains(
    $deploy,
    '/usr/bin/php8.4'
);
$checks['production deploy requires the approved PHP 8.4 web pool'] = str_contains(
    $deploy,
    'oneid-web-prod84.sock'
);
$checks['production deploy requires an explicit candidate and full commit'] =
    str_contains($deploy, '"--candidate", required=True')
    && str_contains($deploy, '"--expected-commit", required=True')
    && str_contains($deploy, 'candidate commit does not match --expected-commit');
$checks['production deploy verifies every committed candidate file digest'] =
    str_contains($deploy, 'validate_candidate_files(candidate, manifest)')
    && str_contains($deploy, 'candidate file digest mismatch')
    && str_contains($deploy, 'candidate contains an unexpected file');
$checks['production deploy preserves legacy dependencies and diagnostics'] =
    str_contains($deploy, '"vendors/"')
    && str_contains($deploy, '"public/diagnostic/"')
    && str_contains($deploy, 'live Git tree contains changes outside public/diagnostic');

$builder = $read('tools/release/build-package.py');
$checks['release builder requires clean pushed main'] =
    str_contains($builder, 'Refusing to package a dirty worktree')
    && str_contains($builder, 'commit != remote_commit')
    && str_contains($builder, '"git_pushed": True');

$archive = 'tools/release/archive/php83-to-php84';
foreach ([
    'prepare-production-php84.py',
    'cutover-production-web84.py',
    'prepare-production-cli-cron84.py',
    'cutover-production-cli-cron84.py',
] as $file) {
    $checks["completed migration script {$file} is archived"] = is_file(
        $root . '/' . $archive . '/' . $file
    );
}

$failures = 0;
foreach ($checks as $description => $passed) {
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $description);
    $failures += $passed ? 0 : 1;
}
printf(
    "RESULT checks=%d failures=%d mutations=0 service_actions=0\n",
    count($checks),
    $failures
);
exit($failures === 0 ? 0 : 1);
