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

$up = $read('docs/migrations/20260726_mydigitalid_f2_identity_audit_up.sql');
$down = $read('docs/migrations/20260726_mydigitalid_f2_identity_audit_down.sql');
$protector = $read('app/Auth/MyDigitalId/MyDigitalIdIdentityProtector.php');
$repository = $read('app/Auth/MyDigitalId/PdoMyDigitalIdIdentityRepository.php');
$runtime = $read('config/runtime.php');
$template = $read('docs/examples/oneid-secrets.example.php');
$login = $read('index.php');
$publicEndpoints = glob($root . '/public/auth/mydigitalid/*') ?: [];
$liveSchemaTool = $root . '/tools/mydigitalid_f2_schema_migrate.php';

$check(
    str_contains($up, 'CREATE TABLE user_federated_identity')
        && str_contains($up, 'CREATE TABLE federated_auth_event')
        && !preg_match('/ALTER\\s+TABLE\\s+user_tbl/i', $up),
    'migration is additive and does not alter user_tbl'
);
$check(
    str_contains($up, 'subject_hmac CHAR(64)')
        && str_contains($up, 'nric_hmac CHAR(64)')
        && str_contains($up, 'hmac_key_id')
        && !preg_match('/\\b(nric|nama|name|access_token|refresh_token|id_token|client_secret)\\s+(?:VAR)?CHAR/i', $up),
    'schema stores keyed digests and excludes raw identity/token fields'
);
$check(
    str_contains($up, 'uq_federated_provider_subject')
        && str_contains($up, 'uq_federated_provider_user')
        && str_contains($up, 'uq_federated_event_correlation')
        && substr_count($up, 'FOREIGN KEY') === 3,
    'schema pins subject, user, event uniqueness and three foreign keys'
);
$check(
    str_contains($up, "CHECK (identity_status IN ('ACTIVE', 'REVOKED'))")
        && str_contains($up, "CHECK (outcome IN ('SUCCESS', 'REJECTED', 'ERROR'))")
        && str_contains($down, 'DROP TABLE federated_auth_event;')
        && str_contains($down, 'DROP TABLE user_federated_identity;'),
    'status/outcome checks and dependency-ordered rollback exist'
);
$check(
    str_contains($protector, "hash_hmac('sha256'")
        && str_contains($protector, "strlen(\$key) !== 32")
        && str_contains($protector, "preg_match('/^\\d{12}\$/D'")
        && !str_contains($protector, 'ONEID_MYDID_CLIENT_SECRET'),
    'identity protector uses a separate 256-bit keyed HMAC and strict NRIC shape'
);
$check(
    str_contains($runtime, "'ONEID_MYDID_IDENTITY_HMAC_KEY_ID' => ''")
        && !str_contains($runtime, 'ONEID_MYDID_IDENTITY_HMAC_KEY_BASE64')
        && str_contains($template, "'ONEID_MYDID_IDENTITY_HMAC_KEY_BASE64' => ''"),
    'committed runtime has no HMAC key and private template has an empty placeholder'
);
$check(
    str_contains($repository, 'findActiveBySubject')
        && str_contains($repository, 'createActiveLink')
        && str_contains($repository, 'touchSuccessfulLogin')
        && str_contains($repository, 'recordEvent')
        && str_contains($repository, 'transactional')
        && str_contains($repository, 'MYDID_ACTIVE_LINK_MISMATCH'),
    'dormant repository covers transaction, lookup, link, successful touch, event and mismatch rejection'
);
$check(
    (
        !is_file($liveSchemaTool)
        || (
            str_contains($read('tools/mydigitalid_f2_schema_migrate.php'), 'MYDID_EXPLICIT_APPLY_ARGUMENT_REQUIRED')
            && str_contains($read('tools/mydigitalid_f2_schema_migrate.php'), 'MYDID_SCHEMA_APPLY_NOT_APPROVED')
            && str_contains($read('tools/mydigitalid_f2_schema_migrate.php'), 'MYDID_STAGING_ENVIRONMENT_REQUIRED')
        )
    )
        && (
            $publicEndpoints === []
            || (
                is_file($root . '/public/auth/mydigitalid/login.php')
                && is_file($root . '/public/auth/mydigitalid/callback.php')
            )
        )
        && !str_contains($login, 'action_mydigitalid')
        && str_contains($runtime, "'ONEID_MYDID_ENABLED' => 'false'"),
    'any later deployment runner is explicitly gated and runtime authentication remains dormant'
);
$check(
    str_contains($read('tools/mydigitalid_f2_isolated_schema_rehearsal.php'), 'oneid_mydid_f2_rehearsal_')
        && str_contains($read('tools/mydigitalid_f2_isolated_schema_rehearsal.php'), 'DROP DATABASE')
        && str_contains($read('tools/mydigitalid_f2_isolated_schema_rehearsal.php'), 'live_schema_mutations=0'),
    'rehearsal is bounded to a random isolated database with cleanup'
);

printf("RESULT checks=%d failures=%d live_schema_mutations=0 runtime_wiring=0\n", $checks, $failures);
exit($failures === 0 ? 0 : 1);
