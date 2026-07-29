<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}
$root = dirname(__DIR__);
$commands = [
    'U8 contract' => 'tools/user_login_mfa_u8_contract.php',
    'U0-U7 regression' => 'tools/user_login_mfa_u7_security_suite.php',
];
$failures = 0;
foreach ($commands as $label => $file) {
    $output = [];
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/' . $file), $output, $status);
    $passed = $status === 0
        && count(array_filter($output, static fn(string $line): bool => str_starts_with($line, 'FAIL '))) === 0;
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $label);
    $failures += $passed ? 0 : 1;
}
printf(
    "RESULT commands=%d failures=%d shared_database_mutations=0 staging_activation=0 push=0\n",
    count($commands),
    $failures
);
exit($failures === 0 ? 0 : 1);
