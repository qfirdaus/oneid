<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

$root = dirname(__DIR__);
$checks = 0;
$failures = 0;
$check = static function (bool $passed, string $description) use (&$checks, &$failures): void {
    $checks++;
    if (!$passed) {
        $failures++;
    }
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $description);
};

$read = static function (string $path) use ($root): string {
    $content = file_get_contents($root . '/' . $path);
    return $content === false ? '' : $content;
};

$login = $read('index.php');
$publicIndex = $read('public/index.php');
$runtime = $read('config/runtime.php');
$secrets = $read('lib/secrets.php');
$session = $read('lib/session_security.php');
$database = $read('lib/Database.php');
$staffSource = $read('lib/external_data_source_API.php');
$loginContract = $read('tools/login_mydigitalid_logo_contract.php');

$check(
    PHP_VERSION_ID >= 80300,
    'runtime is PHP 8.3 or newer'
);
$check(
    str_contains($publicIndex, "require dirname(__DIR__) . '/index.php';"),
    'public login entry point remains a thin wrapper'
);
$check(
    str_contains($login, 'mydigitalid-preview')
        && str_contains($login, 'img/mydigitalid_logo_colored.svg')
        && !str_contains($login, 'mydigitalid_auth')
        && !str_contains($login, 'action_mydigitalid'),
    'MyDigital ID remains a non-authenticating preview at F0'
);
$check(
    str_contains($runtime, "'ONEID_ENVIRONMENT' => ''")
        && (
            !str_contains($runtime, 'ONEID_MYDID_ENABLED')
            || str_contains($runtime, "'ONEID_MYDID_ENABLED' => 'false'")
        ),
    'MyDigital ID runtime activation is absent or remains fail-closed'
);
$check(
    str_contains($secrets, 'oneid_runtime_file_path()')
        && str_contains($secrets, "getenv(\$key)"),
    'OneID has an environment/private-file secret boundary'
);
$check(
    str_contains($session, 'session_regenerate_id(true)')
        && str_contains($session, 'oneid_establish_authenticated_session'),
    'authenticated OneID session lifecycle regenerates the session identifier'
);
$check(
    str_contains($database, "authenticateByField('u_id'")
        && str_contains($database, 'oneid_password_verify')
        && str_contains($database, 'update_whole_token_status'),
    'password authentication and local token policy remain present'
);
$check(
    str_contains($staffSource, 'nopekerja as data3')
        && str_contains($staffSource, 'nokp,"") as data4')
        && str_contains($staffSource, 'ISNULL(nokp,"") as data2')
        && str_contains($staffSource, 'no_matrik as data4'),
    'source mapping keeps staff NRIC in data4 and student NRIC in data2'
);
$check(
    str_contains($loginContract, 'no MyDigital ID authentication action or endpoint is introduced'),
    'existing characterization explicitly records the disabled integration boundary'
);
$check(
    !is_dir($root . '/public/resources/references')
        && (
            !is_dir($root . '/public/auth/mydigitalid')
            || (
                is_file($root . '/public/auth/mydigitalid/login.php')
                && is_file($root . '/public/auth/mydigitalid/callback.php')
            )
        ),
    'reference material is absent from public root and any later-phase endpoints are explicitly bounded'
);

printf("RESULT checks=%d failures=%d mutation_statements=0\n", $checks, $failures);
exit($failures === 0 ? 0 : 1);
