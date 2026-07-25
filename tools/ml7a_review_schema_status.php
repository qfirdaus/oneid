<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/config.php';

$pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$table = 'metadata_content_review';
$columns = $pdo->query(
    "SELECT column_name,data_type,is_nullable,column_default
     FROM information_schema.columns
     WHERE table_schema=DATABASE() AND table_name='{$table}'
     ORDER BY ordinal_position"
)->fetchAll();
$indexes = $pdo->query(
    "SELECT index_name,GROUP_CONCAT(column_name ORDER BY seq_in_index) AS columns_list,non_unique
     FROM information_schema.statistics
     WHERE table_schema=DATABASE() AND table_name='{$table}'
     GROUP BY index_name,non_unique ORDER BY index_name"
)->fetchAll();
$checks = $pdo->query(
    "SELECT constraint_name,check_clause
     FROM information_schema.check_constraints
     WHERE constraint_schema=DATABASE()
       AND constraint_name LIKE 'chk_metadata_content_review_%'
     ORDER BY constraint_name"
)->fetchAll();
$rows = $columns === []
    ? null
    : (int) $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();

$result = [
    'mode' => 'ml7a_review_schema_status',
    'schema_ready' => count($columns) === 13
        && count($indexes) === 3
        && count($checks) === 4,
    'table' => $table,
    'column_count' => count($columns),
    'index_count' => count($indexes),
    'check_constraint_count' => count($checks),
    'review_rows' => $rows,
    'zero_initial_rows' => $rows === 0,
    'rollback_script_available' => is_file(
        dirname(__DIR__) . '/docs/migrations/20260725_ml7a_content_review_decision_down.sql'
    ),
    'mutation_statements' => 0,
];
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
