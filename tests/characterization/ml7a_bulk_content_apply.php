<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/lib/config.php';
require_once dirname(__DIR__, 2) . '/app/Metadata/MetadataContentInventory.php';
require_once dirname(__DIR__, 2) . '/app/Metadata/MetadataBulkContentPlanner.php';
require_once dirname(__DIR__, 2) . '/app/Metadata/MetadataBulkContentApplyService.php';

$checks = 0;
$failed = 0;
$report = static function (bool $ok, string $label) use (&$checks, &$failed): void {
    $checks++;
    echo ($ok ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
    if (!$ok) {
        $failed++;
    }
};

$pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$planner = new \OneId\App\Metadata\MetadataBulkContentPlanner(
    new \OneId\App\Metadata\MetadataContentInventory($pdo)
);
$service = new \OneId\App\Metadata\MetadataBulkContentApplyService($pdo, $planner);
$beforeReviews = (int) $pdo->query('SELECT COUNT(*) FROM metadata_content_review')->fetchColumn();
$beforeTranslations = (int) $pdo->query(
    "SELECT
        (SELECT COUNT(*) FROM sp_app_translation WHERE locale='en') +
        (SELECT COUNT(*) FROM sp_group_translation WHERE locale='en')"
)->fetchColumn();
$blocked = false;
try {
    $service->apply('820705025923', new DateTimeImmutable('2026-07-25T21:34:59+08:00'));
} catch (RuntimeException $exception) {
    $blocked = $exception->getMessage() === 'ML7A_BULK_OUTSIDE_CHANGE_WINDOW';
}
$report($blocked, 'Apply rejects execution outside the exact change window');
$report(
    (int) $pdo->query('SELECT COUNT(*) FROM metadata_content_review')->fetchColumn() === $beforeReviews
    && (int) $pdo->query(
        "SELECT
            (SELECT COUNT(*) FROM sp_app_translation WHERE locale='en') +
            (SELECT COUNT(*) FROM sp_group_translation WHERE locale='en')"
    )->fetchColumn() === $beforeTranslations,
    'rejected Apply produces zero mutation'
);
$reflection = new ReflectionClass($service);
$report(
    $reflection->getConstant('APPROVED_PLAN_HASH')
        === '3ade2d6bf970c2f87c9f6889cf5584c6d06c7ab66da62c5956681941d8c8c664'
    && $reflection->getConstant('BACKUP_REFERENCE') === 'ONEID-LOCAL-BACKUP-20260725-05',
    'Apply is bound to the exact plan and backup reference'
);
$replayBlocked = false;
try {
    $service->apply('820705025923', new DateTimeImmutable('2026-07-25T21:40:00+08:00'));
} catch (RuntimeException $exception) {
    $replayBlocked = $exception->getMessage() === 'ML7A_BULK_BASELINE_MISMATCH';
}
$report($replayBlocked, 'committed Apply cannot be replayed inside the approved window');

echo "RESULT checks={$checks} failed={$failed}\n";
exit($failed === 0 ? 0 : 1);
