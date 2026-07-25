<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

require_once dirname(__DIR__) . '/lib/config.php';

$pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$tablePresent = (int) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.TABLES
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_locale_preference'"
)->fetchColumn() === 1;
$counts = ['ms' => 0, 'en' => 0];
if ($tablePresent) {
    foreach ($pdo->query(
        "SELECT locale,COUNT(*) AS aggregate_count
         FROM user_locale_preference
         GROUP BY locale ORDER BY locale"
    )->fetchAll() as $row) {
        if (array_key_exists((string) $row['locale'], $counts)) {
            $counts[(string) $row['locale']] = (int) $row['aggregate_count'];
        }
    }
}

$snapshot = [
    'mode' => 'ml2_local_observation',
    'checked_at' => (new DateTimeImmutable('now'))->format(DateTimeInterface::ATOM),
    'environment' => (string) oneid_config('ONEID_ENVIRONMENT', ''),
    'app_url' => (string) oneid_config('ONEID_APP_URL', ''),
    'infrastructure_enabled' => filter_var(
        oneid_config('ONEID_LOCALE_INFRASTRUCTURE_ENABLED', 'false'),
        FILTER_VALIDATE_BOOLEAN
    ),
    'schema_apply_enabled' => filter_var(
        oneid_config('ONEID_ML1_SCHEMA_APPLY_ENABLED', 'false'),
        FILTER_VALIDATE_BOOLEAN
    ),
    'preference_table_present' => $tablePresent,
    'preference_counts' => $counts,
    'allowed_locales' => ['ms', 'en'],
    'default_locale' => (string) oneid_config('ONEID_DEFAULT_LOCALE', 'ms'),
    'legacy_msg_retained' => str_contains(
        (string) file_get_contents(dirname(__DIR__) . '/lib/q_func.php'),
        "'msg'"
    ),
    'mutation_statements' => 0,
];
$snapshot['ready'] = $snapshot['environment'] === 'local'
    && $snapshot['app_url'] === 'https://oneid.local'
    && $snapshot['infrastructure_enabled'] === true
    && $snapshot['schema_apply_enabled'] === false
    && $snapshot['preference_table_present'] === true
    && $snapshot['default_locale'] === 'ms'
    && $snapshot['legacy_msg_retained'] === true;

echo json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
exit($snapshot['ready'] ? 0 : 1);
