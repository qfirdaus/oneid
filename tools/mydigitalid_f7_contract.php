<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

$root = dirname(__DIR__);
$read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);
$orchestrator = $read('app/Auth/MyDigitalId/MyDigitalIdCallbackOrchestrator.php');
$state = $read('app/Auth/MyDigitalId/MyDigitalIdRejectedLogoutState.php');
$endpoint = $read('app/Auth/MyDigitalId/MyDigitalIdSwitchAccountEndpoint.php');
$publicEndpoint = $read('public/auth/mydigitalid/switch-account.php');
$index = $read('index.php');
$ms = $read('config/locales/ms.php');
$en = $read('config/locales/en.php');
$runbook = $read('docs/MYDIGITALID_F7_REJECTION_UX_ACCOUNT_SWITCH_LOG_HARDENING.md');

$checks = [];
$checks['verified_rejection_state'] = str_contains(
    $orchestrator,
    'MyDigitalIdRejectedLogoutState::retain'
) && str_contains($state, 'TTL_SECONDS = 300')
    && str_contains($state, 'consume(');
$checks['one_use_no_database'] = str_contains($state, 'unset(')
    && !preg_match('/\\b(?:PDO|INSERT|UPDATE|DELETE)\\b/', $state . $endpoint);
$checks['post_csrf'] = str_contains($endpoint, "!== 'POST'")
    && str_contains($endpoint, 'oneid_request_csrf_token')
    && str_contains($endpoint, 'hash_equals(');
$checks['official_logout'] = str_contains($endpoint, 'MyDigitalIdLogoutUrl')
    && str_contains($endpoint, 'session_regenerate_id(true)')
    && str_contains($endpoint, 'Referrer-Policy: no-referrer');
$checks['thin_endpoint'] = str_contains(
    $publicEndpoint,
    'MyDigitalIdSwitchAccountEndpoint::run();'
);
$checks['generic_bilingual_ui'] = str_contains($index, 'mydigitalid-switch-account-form')
    && str_contains($index, 'login.mydigitalid.switch_account')
    && str_contains($index, 'login.mydigitalid.use_password')
    && str_contains($ms, "'login.mydigitalid.switch_account'")
    && str_contains($en, "'login.mydigitalid.switch_account'")
    && !str_contains($index, 'MYDID_USER_NOT_FOUND')
    && !str_contains($index, 'MYDID_IDENTITY_MISMATCH');
$checks['no_raw_token_output'] = !preg_match(
    '/(?:echo|printf|error_log)\\s*\\([^;]*(?:idToken|id_token)/i',
    $state . $endpoint . $orchestrator
);
$checks['nginx_hardening_runbook'] = str_contains($runbook, '$request_method $uri $server_protocol')
    && str_contains($runbook, 'log_format oneid_safe')
    && !str_contains($runbook, '"$http_referer"')
    && str_contains($runbook, 'sudo nginx -t')
    && str_contains($runbook, 'code=')
    && str_contains($runbook, 'Jangan memadam log secara ad hoc');

$failed = count(array_filter($checks, static fn(bool $passed): bool => !$passed));
foreach ($checks as $name => $passed) {
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $name);
}
printf(
    "RESULT checks=%d failures=%d feature_activation=0 database_mutations=0 raw_token_output=0\n",
    count($checks),
    $failed
);
exit($failed === 0 ? 0 : 1);
