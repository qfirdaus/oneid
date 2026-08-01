<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$up = (string) file_get_contents($root . '/docs/migrations/20260801_lb1_login_banner_up.sql');
$down = (string) file_get_contents($root . '/docs/migrations/20260801_lb1_login_banner_down.sql');
$interface = (string) file_get_contents($root . '/app/LoginBanner/LoginBannerPersistenceInterface.php');
$repository = (string) file_get_contents($root . '/app/LoginBanner/PdoLoginBannerPersistence.php');
$index = (string) file_get_contents($root . '/index.php');
$admin = (string) file_get_contents($root . '/admin/dashboard.php');
$actions = (string) file_get_contents($root . '/lib/request_security.php');

$checks = 0;
$failures = 0;
$report = static function (bool $passed, string $description) use (&$checks, &$failures): void {
    $checks++;
    echo ($passed ? 'PASS ' : 'FAIL ') . $description . PHP_EOL;
    if (!$passed) {
        $failures++;
    }
};

$tables = [
    'login_banner',
    'login_banner_translation',
    'login_banner_asset',
    'login_banner_locale_asset',
    'login_banner_history',
];
$createsAll = true;
$dropsAll = true;
foreach ($tables as $table) {
    $createsAll = $createsAll && substr_count($up, 'CREATE TABLE ' . $table . ' ') === 1;
    $dropsAll = $dropsAll && substr_count($down, 'DROP TABLE IF EXISTS ' . $table . ';') === 1;
}
$report(
    $createsAll && $dropsAll && substr_count($up, 'CREATE TABLE ') === 5,
    'additive up and explicit down migrations cover five banner tables'
);

$report(
    strpos($down, 'DROP TABLE IF EXISTS login_banner_history;')
        < strpos($down, 'DROP TABLE IF EXISTS login_banner_locale_asset;')
    && strpos($down, 'DROP TABLE IF EXISTS login_banner_locale_asset;')
        < strpos($down, 'DROP TABLE IF EXISTS login_banner_asset;')
    && strpos($down, 'DROP TABLE IF EXISTS login_banner_asset;')
        < strpos($down, 'DROP TABLE IF EXISTS login_banner_translation;')
    && strpos($down, 'DROP TABLE IF EXISTS login_banner_translation;')
        < strpos($down, 'DROP TABLE IF EXISTS login_banner;'),
    'destructive rollback drops dependants before banner roots'
);

$report(
    str_contains($up, "locale IN ('ms','en')")
    && str_contains($up, "fallback_policy IN ('OWN_ASSET','SAME_AS_MS')")
    && str_contains($up, "locale <> 'ms' OR fallback_policy = 'OWN_ASSET'")
    && str_contains($up, 'PRIMARY KEY (banner_id, environment, locale)'),
    'locale contract supports explicit same-as-BM without silent BM fallback'
);

$report(
    str_contains($up, 'UNIQUE KEY uq_login_banner_asset_identity (asset_id, banner_id, environment)')
    && str_contains($up, 'FOREIGN KEY (asset_id, banner_id, environment)')
    && str_contains($up, 'REFERENCES login_banner_asset(asset_id, banner_id, environment)'),
    'composite asset mapping prevents cross-banner and cross-environment references'
);

$report(
    str_contains($up, "image_filename REGEXP '^login_banner_[a-f0-9]{32}\\\\.webp$'")
    && str_contains($up, "mime_type = 'image/webp'")
    && str_contains($up, 'image_width = 1600 AND image_height = 800')
    && str_contains($up, 'byte_size BETWEEN 1 AND 512000')
    && str_contains($up, "sha256_digest REGEXP '^[a-f0-9]{64}$'"),
    'asset constraints lock immutable WebP dimensions size and digest'
);

$report(
    str_contains($up, "banner_status IN ('DRAFT','PUBLISHED','INACTIVE','ARCHIVED')")
    && str_contains($up, 'display_order BETWEEN 1 AND 5')
    && str_contains($up, 'starts_at_utc < ends_at_utc')
    && str_contains($up, 'configuration_version >= 1'),
    'banner lifecycle schedule order and version constraints are explicit'
);

$report(
    str_contains($up, "outcome IN ('SUCCESS','REJECTED')")
    && str_contains($up, 'before_json JSON NULL')
    && str_contains($up, 'after_json JSON NULL')
    && str_contains($up, 'UNIQUE KEY uq_login_banner_history_correlation'),
    'history schema preserves correlated success and rejection evidence'
);

$requiredMethods = [
    'schemaStatus', 'transactional', 'publishedForLocale', 'bannerForUpdate',
    'insertBanner', 'upsertTranslation', 'insertAsset', 'mapLocaleAsset',
    'updateBannerVersioned', 'recordHistory',
];
$interfaceComplete = true;
foreach ($requiredMethods as $method) {
    $interfaceComplete = $interfaceComplete && str_contains($interface, 'function ' . $method . '(');
}
$report($interfaceComplete, 'persistence interface exposes the complete dormant LB1 boundary');

$report(
    str_contains($repository, 'if ($this->pdo->inTransaction())')
    && str_contains($repository, '$this->pdo->beginTransaction();')
    && str_contains($repository, '$this->pdo->commit();')
    && str_contains($repository, '$this->pdo->rollBack();'),
    'transaction wrapper rejects nesting and rolls back failures'
);

$report(
    str_contains($repository, "a.storage_status='AVAILABLE'")
    && str_contains($repository, 'm.environment=:environment')
    && str_contains($repository, 't.locale=:locale')
    && str_contains($repository, 'b.starts_at_utc<=:effective_at')
    && str_contains($repository, 'b.ends_at_utc>:effective_at')
    && str_contains($repository, 'LIMIT 5'),
    'public read contract is locale environment availability schedule and count scoped'
);

$report(
    str_contains($repository, 'LIMIT 1 FOR UPDATE')
    && str_contains($repository, 'configuration_version=configuration_version+1')
    && str_contains($repository, 'configuration_version=:expected_version'),
    'write contract supports locking and optimistic concurrency'
);

$report(
    str_contains($index, 'PdoLoginBannerPersistence')
    && !str_contains($admin, 'PdoLoginBannerPersistence')
    && str_contains($actions, 'admin_login_banner_list')
    && str_contains($index, 'assetsM/images/banner6.png')
    && str_contains($index, 'assetsM/images/banner7.png'),
    'LB1 schema remains unapplied while LB6 is flagged off and UI stays persistence-free'
);

echo "RESULT checks={$checks} failures={$failures}\n";
exit($failures === 0 ? 0 : 1);
