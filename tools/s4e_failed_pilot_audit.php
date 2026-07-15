<?php

/** Read-only audit after a failed controlled pilot. No raw user data. */
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI only\n"); exit(2); }
$root = dirname(__DIR__);
require_once $root . '/lib/config.php';

$reflection = new ReflectionProperty(Database::class, 'pdo');
$reflection->setAccessible(true);
/** @var PDO $pdo */
$pdo = $reflection->getValue($operation);
$database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();

echo "READ_ONLY database={$database}\n";
$tables = ['user_tbl', 'ext_data_temp_header', 'ext_data_temp_body', 'sync_change_log'];
$engineQuery = $pdo->prepare(
    'SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES
     WHERE TABLE_SCHEMA=? AND TABLE_NAME IN (?,?,?,?) ORDER BY TABLE_NAME'
);
$engineQuery->execute(array_merge([$database], $tables));
$engines = [];
foreach ($engineQuery->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $engines[(string) $row['TABLE_NAME']] = (string) $row['ENGINE'];
}
foreach ($tables as $table) {
    printf("TABLE name=%s engine=%s transactional=%s\n", $table, $engines[$table] ?? 'missing', ($engines[$table] ?? '') === 'InnoDB' ? 'yes' : 'no');
}

$requiredColumns = [
    'ext_data_temp_header' => ['total_new','total_updated','total_deactivated','total_reactivated','triggered_by'],
    'sync_change_log' => ['ext_head_id','u_id','action','old_data','new_data','changed_fields','logged_at'],
];
foreach ($requiredColumns as $table => $columns) {
    $placeholders = implode(',', array_fill(0, count($columns), '?'));
    $query = $pdo->prepare(
        "SELECT COLUMN_NAME FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME IN ({$placeholders})"
    );
    $query->execute(array_merge([$database, $table], $columns));
    $present = array_flip($query->fetchAll(PDO::FETCH_COLUMN));
    $missing = array_values(array_filter($columns, static fn(string $column): bool => !isset($present[$column])));
    printf("SCHEMA table=%s required=%d missing=%s\n", $table, count($columns), $missing === [] ? 'none' : implode(',', $missing));
}

$emulated = (bool) $pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES);
$duplicateNamed = 'pass';
$duplicateSqlState = '-';
try {
    $statement = $pdo->prepare('SELECT :probe + :probe');
    $statement->execute([':probe' => 1]);
    $statement->fetchColumn();
} catch (PDOException $exception) {
    $duplicateNamed = 'fail';
    $duplicateSqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode() ?: 'unknown');
}
printf("PDO emulate_prepares=%s duplicate_named_select=%s sqlstate=%s\n", $emulated ? 'yes' : 'no', $duplicateNamed, $duplicateSqlState);

$lockStatement = $pdo->prepare("SELECT IS_USED_LOCK('oneid:external-user-sync')");
$lockStatement->execute();
$lockOwner = $lockStatement->fetchColumn();
printf("LOCK active=%s\n", $lockOwner === false || $lockOwner === null ? 'no' : 'yes');

if (isset($engines['ext_data_temp_header'])) {
    $headers = $pdo->query(
        'SELECT ext_head_id,ext_head_dt_start,ext_head_dt_end,ext_head_status,
                ext_head_initial_sourcedata,ext_head_uploaded_data
         FROM ext_data_temp_header ORDER BY ext_head_id DESC LIMIT 5'
    )->fetchAll(PDO::FETCH_ASSOC);
    foreach ($headers as $header) {
        printf(
            "HEADER id=%d start=%s end=%s status=%d source=%d uploaded=%d\n",
            (int) $header['ext_head_id'], (string) $header['ext_head_dt_start'],
            (string) $header['ext_head_dt_end'], (int) $header['ext_head_status'],
            (int) $header['ext_head_initial_sourcedata'], (int) $header['ext_head_uploaded_data']
        );
    }
}

printf("RESULT mutation_statements=0\n");
