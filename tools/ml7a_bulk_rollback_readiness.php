<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/config.php';

$pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$reviewRows = (int) $pdo->query(
    "SELECT COUNT(*) FROM metadata_content_review
     WHERE manifest_digest='6c4524393cd86fdab4beaa76e88feb63f24e6691b191457e044408e3446eb444'"
)->fetchColumn();
$translationRows = (int) $pdo->query(
    "SELECT COUNT(*)
     FROM metadata_content_review r
     LEFT JOIN sp_app_translation a
       ON r.entity_type='application' AND a.sp_id=r.entity_id AND a.locale=r.locale
     LEFT JOIN sp_group_translation g
       ON r.entity_type='category' AND CAST(g.sp_group_id AS CHAR)=r.entity_id
      AND g.locale=r.locale
     WHERE r.review_decision='ACCEPT_TRANSLATION'
       AND (a.translation_id IS NOT NULL OR g.translation_id IS NOT NULL)"
)->fetchColumn();
$historyRows = (int) $pdo->query(
    "SELECT COUNT(*) FROM metadata_translation_history
     WHERE change_reason='Approved ML7A bulk metadata translation'"
)->fetchColumn();
$quarantineRows = (int) $pdo->query(
    "SELECT COUNT(*)
     FROM metadata_content_review r
     LEFT JOIN sp_app_translation a
       ON r.entity_type='application' AND a.sp_id=r.entity_id AND a.locale=r.locale
     LEFT JOIN sp_group_translation g
       ON r.entity_type='category' AND CAST(g.sp_group_id AS CHAR)=r.entity_id
      AND g.locale=r.locale
     WHERE r.review_decision='EXCLUDE_QUARANTINE'
       AND (a.translation_id IS NOT NULL OR g.translation_id IS NOT NULL)"
)->fetchColumn();

$ready = $reviewRows === 84
    && $translationRows === 33
    && $historyRows === 33
    && $quarantineRows === 0;
echo json_encode([
    'mode' => 'ml7a_bulk_rollback_readiness',
    'change_reference' => 'ONEID-ML7A-BULK-LOCAL-20260725-02',
    'backup_reference' => 'ONEID-LOCAL-BACKUP-20260725-05',
    'review_rows_identified' => $reviewRows,
    'translation_rows_identified' => $translationRows,
    'history_rows_identified' => $historyRows,
    'quarantine_translation_rows' => $quarantineRows,
    'rollback_ready' => $ready,
    'blocking_codes' => $ready ? [] : ['ML7A_ROLLBACK_SCOPE_MISMATCH'],
    'mutation_statements' => 0,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
