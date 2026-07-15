<?php

/** Read-only schema/data compatibility audit for the deterministic 2/1 pilot subset. */
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI only\n"); exit(2); }
$root = dirname(__DIR__);
require_once $root . '/lib/config.php';
require_once $root . '/lib/external_data_source_API.php';
require_once $root . '/bootstrap/sync_runtime.php';

$external = EXTERNAL_DATA_SOURCE_GET_ALL_USER();
$active = $operation->sync_get_all_sso_user();
$inactive = $operation->sync_get_inactive_user_ids();
$planner = new \OneId\App\Sync\SyncPlanner(new \OneId\App\Sync\Adapters\LegacySyncPolicy());
$fullPlan = $planner->plan($external, $active, $inactive);
$selector = new \OneId\App\Sync\SyncPlanSubsetSelector(
    \OneId\App\Sync\SyncPilotConfig::fromValues('true', '2', '1', '0', '0')
);
$plan = $selector->select($fullPlan);

$reflection = new ReflectionProperty(Database::class, 'pdo');
$reflection->setAccessible(true);
/** @var PDO $pdo */
$pdo = $reflection->getValue($operation);
$database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
$columnQuery = $pdo->prepare(
    "SELECT COLUMN_NAME,DATA_TYPE,CHARACTER_MAXIMUM_LENGTH,IS_NULLABLE
     FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME='user_tbl'"
);
$columnQuery->execute([$database]);
$columns = [];
foreach ($columnQuery->fetchAll(PDO::FETCH_ASSOC) as $column) {
    $columns[(string) $column['COLUMN_NAME']] = $column;
}
$indexRows = $pdo->prepare(
    "SELECT INDEX_NAME,COLUMN_NAME,SEQ_IN_INDEX FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA=? AND TABLE_NAME='user_tbl' AND NON_UNIQUE=0
     ORDER BY INDEX_NAME,SEQ_IN_INDEX"
);
$indexRows->execute([$database]);
$uniqueIndexes = [];
foreach ($indexRows->fetchAll(PDO::FETCH_ASSOC) as $indexRow) {
    $uniqueIndexes[(string) $indexRow['INDEX_NAME']][] = (string) $indexRow['COLUMN_NAME'];
}

$exists = $pdo->prepare('SELECT account_source,sync_protected,avail_status FROM user_tbl WHERE u_id=? LIMIT 1');
$category = $pdo->prepare('SELECT 1 FROM user_category WHERE uc_id=? AND avail_status=1 LIMIT 1');
$violations = 0;
$counts = $plan->legacyCounts();
printf("READ_ONLY source=%d subset_new=%d subset_update=%d subset_deactivate=%d subset_reactivate=%d\n", $plan->sourceRows, $counts['New'], $counts['Update'], $counts['Deactivate'], $counts['Reactivate']);

foreach ($plan->actions as $index => $action) {
    $type = (string) ($action['action'] ?? '');
    $uid = trim((string) ($action['u_id'] ?? ''));
    $row = is_array($action['row'] ?? null) ? $action['row'] : [];
    $candidateViolations = [];
    $values = ['u_id' => $uid, 'u_changes_hash' => (string) ($action['change_hash'] ?? '')];
    for ($i = 1; $i <= 12; $i++) $values['data' . $i] = (string) ($row['data' . $i] ?? '');
    foreach ($values as $field => $value) {
        if (!isset($columns[$field])) { $candidateViolations[] = 'COLUMN_MISSING_' . strtoupper($field); continue; }
        $max = $columns[$field]['CHARACTER_MAXIMUM_LENGTH'];
        if ($max !== null && mb_strlen($value, 'UTF-8') > (int) $max) {
            $candidateViolations[] = sprintf('TOO_LONG_%s_%d_GT_%d', strtoupper($field), mb_strlen($value, 'UTF-8'), (int) $max);
        }
    }
    $exists->execute([$uid]);
    $existing = $exists->fetch(PDO::FETCH_ASSOC);
    if ($type === 'NEW' && is_array($existing)) $candidateViolations[] = 'NEW_UID_ALREADY_EXISTS';
    if ($type === 'UPDATE' && !is_array($existing)) $candidateViolations[] = 'UPDATE_UID_MISSING';
    if ($type === 'NEW') {
        $category->execute([(int) ($action['category_id'] ?? 0)]);
        if ($category->fetchColumn() === false) $candidateViolations[] = 'CATEGORY_INACTIVE_OR_MISSING';
        foreach ($uniqueIndexes as $indexName => $indexColumns) {
            if ($indexColumns === ['u_id']) continue;
            if (array_filter($indexColumns, static fn(string $field): bool => !isset($values[$field])) !== []) continue;
            $where = implode(' AND ', array_map(static fn(string $field): string => '`' . $field . '`=?', $indexColumns));
            $uniqueQuery = $pdo->prepare('SELECT COUNT(*) FROM user_tbl WHERE ' . $where);
            $uniqueQuery->execute(array_map(static fn(string $field): string => $values[$field], $indexColumns));
            if ((int) $uniqueQuery->fetchColumn() > 0) {
                $candidateViolations[] = 'UNIQUE_COLLISION_' . preg_replace('/[^A-Za-z0-9_]/', '_', strtoupper($indexName));
            }
        }
    }
    $violations += count($candidateViolations);
    printf(
        "candidate=%d action=%s uid_digest=%s changed_fields=%s violations=%s\n",
        $index + 1, $type, substr(hash('sha256', $uid), 0, 16),
        (string) ($action['changed_fields'] ?? '-'),
        $candidateViolations === [] ? 'none' : implode(',', $candidateViolations)
    );
}

printf("RESULT candidates=%d violations=%d mutation_statements=0\n", count($plan->actions), $violations);
exit($violations === 0 ? 0 : 1);
