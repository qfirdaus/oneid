<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

require_once dirname(__DIR__) . '/lib/config.php';

$mode = $argv[1] ?? '--preview';
if (!in_array($mode, ['--preview', '--apply', '--rollback'], true)) {
    fwrite(STDERR, "Usage: php tools/ml1_uat_migration_gate.php [--preview|--apply|--rollback]\n");
    exit(2);
}

$pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$tableExists = static fn(): bool => (int) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.TABLES
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_locale_preference'"
)->fetchColumn() === 1;
$preferenceCount = static fn(): int => $tableExists()
    ? (int) $pdo->query('SELECT COUNT(*) FROM user_locale_preference')->fetchColumn()
    : 0;

$exists = $tableExists();
$rows = $preferenceCount();
printf(
    "ML1_UAT_SCHEMA mode=%s table=%s preference_rows=%d infrastructure=%s\n",
    $mode,
    $exists ? 'present' : 'absent',
    $rows,
    filter_var(oneid_config('ONEID_LOCALE_INFRASTRUCTURE_ENABLED', 'false'), FILTER_VALIDATE_BOOLEAN)
        ? 'enabled'
        : 'disabled'
);
if ($mode === '--preview') {
    echo "RESULT mutation_statements=0\n";
    exit(0);
}

$environment = strtolower(trim((string) oneid_config('ONEID_ENVIRONMENT', '')));
$enabled = (string) oneid_config('ONEID_ML1_SCHEMA_APPLY_ENABLED', 'false') === 'true';
$changeReference = trim((string) oneid_config('ONEID_ML1_CHANGE_REFERENCE', ''));
$backupReference = trim((string) oneid_config('ONEID_ML1_BACKUP_REFERENCE', ''));
$start = strtotime((string) oneid_config('ONEID_ML1_WINDOW_START', ''));
$end = strtotime((string) oneid_config('ONEID_ML1_WINDOW_END', ''));
$now = time();
$expectedRows = filter_var(
    oneid_config('ONEID_ML1_EXPECTED_EXISTING_PREFERENCES', '0'),
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 0]]
);
$typed = trim((string) getenv('ONEID_ML1_TYPED_CONFIRMATION'));

$reject = static function (string $code): never {
    fwrite(STDERR, "FAIL {$code}\n");
    exit(1);
};
if (!in_array($environment, ['local', 'uat'], true)) {
    $reject('ML1_NON_PRODUCTION_ENVIRONMENT_REQUIRED');
}
if (!$enabled) {
    $reject('ML1_SCHEMA_APPLY_DISABLED');
}
if (preg_match('/\AONEID-ML1-[A-Z0-9-]+\z/', $changeReference) !== 1) {
    $reject('ML1_CHANGE_REFERENCE_INVALID');
}
if (preg_match('/\AONEID-(?:LOCAL|UAT)-BACKUP-[A-Z0-9-]+\z/', $backupReference) !== 1) {
    $reject('ML1_BACKUP_REFERENCE_INVALID');
}
if ($start === false || $end === false || $start >= $end || $now < $start || $now > $end) {
    $reject('ML1_OUTSIDE_CHANGE_WINDOW');
}
if ($expectedRows === false || $rows !== $expectedRows) {
    $reject('ML1_PREFERENCE_COUNT_MISMATCH');
}

if ($mode === '--apply') {
    if (!hash_equals('APPLY ML1 LOCALE SCHEMA', $typed)) {
        $reject('ML1_APPLY_CONFIRMATION_INVALID');
    }
    if (!$exists) {
        $pdo->exec((string) file_get_contents(
            dirname(__DIR__) . '/docs/migrations/20260725_ml1_locale_preference_up.sql'
        ));
    }
    if (!$tableExists() || $preferenceCount() !== $expectedRows) {
        $reject('ML1_APPLY_RECONCILIATION_FAILED');
    }
    echo "PASS ML1 schema installed preference_rows={$expectedRows} user_mutations=0\n";
    exit(0);
}

if (!hash_equals('ROLLBACK ML1 LOCALE SCHEMA', $typed)) {
    $reject('ML1_ROLLBACK_CONFIRMATION_INVALID');
}
if (filter_var(oneid_config('ONEID_LOCALE_INFRASTRUCTURE_ENABLED', 'false'), FILTER_VALIDATE_BOOLEAN)) {
    $reject('ML1_ROLLBACK_REQUIRES_INFRASTRUCTURE_DISABLED');
}
if ($rows !== 0) {
    $reject('ML1_ROLLBACK_REQUIRES_ZERO_PREFERENCES');
}
if ($exists) {
    $pdo->exec((string) file_get_contents(
        dirname(__DIR__) . '/docs/migrations/20260725_ml1_locale_preference_down.sql'
    ));
}
if ($tableExists()) {
    $reject('ML1_ROLLBACK_RECONCILIATION_FAILED');
}
echo "PASS ML1 schema rolled back preference_rows=0 user_mutations=0\n";
