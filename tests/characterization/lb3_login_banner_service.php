<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/LoginBanner/LoginBannerPersistenceInterface.php';
require_once dirname(__DIR__, 2) . '/app/LoginBanner/LoginBannerImagePipelineInterface.php';
require_once dirname(__DIR__, 2) . '/app/LoginBanner/LoginBannerDomainException.php';
require_once dirname(__DIR__, 2) . '/app/LoginBanner/LoginBannerService.php';

use OneId\App\LoginBanner\LoginBannerDomainException;
use OneId\App\LoginBanner\LoginBannerImagePipelineInterface;
use OneId\App\LoginBanner\LoginBannerPersistenceInterface;
use OneId\App\LoginBanner\LoginBannerService;

final class Lb3FakeImages implements LoginBannerImagePipelineInterface
{
    public array $staged = [];
    public array $published = [];
    public array $discardedStaged = [];
    public array $discardedPublished = [];
    private int $next = 1;

    public function stageUpload(?array $file, string $stagingDirectory): array
    {
        if ($file === null) {
            throw new LoginBannerDomainException('LB2_UPLOAD_REQUIRED');
        }
        $id = $this->next++;
        $asset = [
            'filename' => 'login_banner_' . str_pad((string) $id, 32, '0', STR_PAD_LEFT) . '.webp',
            'staged_path' => $stagingDirectory . '/.pending_login_banner_' . str_pad((string) $id, 32, '0', STR_PAD_LEFT),
            'mime_type' => 'image/webp', 'width' => 1600, 'height' => 800,
            'byte_size' => 1000, 'sha256_digest' => str_repeat(dechex($id), 64),
        ];
        $this->staged[] = $asset;
        return $asset;
    }

    public function publish(array $staged, string $publishedDirectory): string
    {
        $path = $publishedDirectory . '/' . $staged['filename'];
        $this->published[] = $path;
        return $path;
    }

    public function discardStaged(array $staged, string $stagingDirectory): void
    {
        $this->discardedStaged[] = $staged['staged_path'];
    }

    public function discardPublished(string $publishedPath, string $publishedDirectory): void
    {
        $this->discardedPublished[] = $publishedPath;
    }
}

final class Lb3FakePersistence implements LoginBannerPersistenceInterface
{
    public array $banners = [];
    public array $translations = [];
    public array $assets = [];
    public array $maps = [];
    public array $history = [];
    public array $localeState = [];
    public bool $failSuccessAudit = false;
    private int $nextBanner = 1;
    private int $nextAsset = 1;

    public function schemaStatus(): array { return ['available' => true, 'tables' => []]; }
    public function adminList(string $environment): array { return []; }
    public function transactional(callable $operation): mixed
    {
        $snapshot = serialize([$this->banners, $this->translations, $this->assets, $this->maps, $this->history]);
        try { return $operation($this); }
        catch (Throwable $error) {
            [$this->banners, $this->translations, $this->assets, $this->maps, $this->history] = unserialize($snapshot);
            throw $error;
        }
    }
    public function publishedForLocale(string $environment, string $locale, string $effectiveAtUtc): array { return []; }
    public function bannerForUpdate(int $bannerId): ?array { return $this->banners[$bannerId] ?? null; }
    public function localeAssetsForUpdate(int $bannerId, string $environment): array { return $this->localeState[$bannerId] ?? []; }
    public function publishedForUpdate(string $environment): array
    {
        return array_values(array_filter($this->banners, static fn(array $row): bool => $row['banner_status'] === 'PUBLISHED'));
    }
    public function latestSuccessfulHistoryForUpdate(int $bannerId, string $environment): ?array
    {
        foreach (array_reverse($this->history) as $event) {
            if (($event['banner_id'] ?? null) === $bannerId && ($event['outcome'] ?? '') === 'SUCCESS'
                && isset($event['before']) && is_array($event['before'])) {
                return ['before_json' => json_encode($event['before'], JSON_THROW_ON_ERROR)];
            }
        }
        return null;
    }
    public function insertBanner(array $banner): int
    {
        $id = $this->nextBanner++;
        $this->banners[$id] = $banner + ['banner_id' => $id, 'configuration_version' => 1, 'banner_status' => 'DRAFT'];
        return $id;
    }
    public function upsertTranslation(array $translation): int { $this->translations[] = $translation; return 1; }
    public function insertAsset(array $asset): int { $id = $this->nextAsset++; $this->assets[$id] = $asset + ['asset_id' => $id]; return $id; }
    public function mapLocaleAsset(int $bannerId, string $environment, string $locale, int $assetId, string $actorId): int
    { $this->maps[$bannerId][$locale] = $assetId; return 1; }
    public function updateBannerVersioned(int $bannerId, int $expectedVersion, array $changes, string $actorId): int
    {
        if (($this->banners[$bannerId]['configuration_version'] ?? 0) !== $expectedVersion) return 0;
        $this->banners[$bannerId] = array_replace($this->banners[$bannerId], $changes, [
            'configuration_version' => $expectedVersion + 1, 'updated_by' => $actorId,
        ]);
        return 1;
    }
    public function updateDraftVersioned(int $bannerId, int $expectedVersion, array $changes, string $actorId): int
    { return $this->updateBannerVersioned($bannerId, $expectedVersion, $changes, $actorId); }
    public function recordHistory(array $event): int
    {
        if ($this->failSuccessAudit && ($event['outcome'] ?? '') === 'SUCCESS') return 0;
        $this->history[] = $event;
        return 1;
    }
}

$checks = 0; $failures = 0;
$report = static function (bool $ok, string $label) use (&$checks, &$failures): void {
    $checks++; echo ($ok ? 'PASS ' : 'FAIL ') . $label . PHP_EOL; if (!$ok) $failures++;
};
$expect = static function (callable $call, string $code): bool {
    try { $call(); } catch (Throwable $error) { return $error->getMessage() === $code; } return false;
};
$context = ['staging', '/tmp/lb3-stage', '/tmp/lb3-live', '0530-09', '127.0.0.1', 'Approved banner test change'];
$input = ['banner_key' => 'LB-UAT-01', 'display_order' => 1, 'alt_text_ms' => 'Banner ujian OneID', 'alt_text_en' => 'OneID test banner'];

$db = new Lb3FakePersistence(); $images = new Lb3FakeImages(); $service = new LoginBannerService($db, $images);
$created = $service->createDraft($input, ['ms' => ['x' => 1], 'en' => null], true, ...$context);
$report($created['code'] === 'LB3_DRAFT_CREATED' && count($images->staged) === 1 && count($db->assets) === 1, 'same-image draft stages and persists one binary asset');
$report($db->maps[1]['ms'] === $db->maps[1]['en'], 'BM and English map to the same asset ID without duplication');
$report(count($db->translations) === 2 && $db->translations[1]['fallback_policy'] === 'SAME_AS_MS', 'localized alt text and explicit English fallback are persisted');
$report(($db->history[0]['outcome'] ?? '') === 'SUCCESS' && ($db->history[0]['action_name'] ?? '') === 'CREATE_DRAFT', 'draft asset metadata and success audit share one transaction');

$db->localeState[1] = [
    ['locale' => 'en', 'alt_text' => 'OneID test banner', 'fallback_policy' => 'SAME_AS_MS', 'asset_id' => 1, 'storage_status' => 'AVAILABLE'],
    ['locale' => 'ms', 'alt_text' => 'Banner ujian OneID', 'fallback_policy' => 'OWN_ASSET', 'asset_id' => 1, 'storage_status' => 'AVAILABLE'],
];
$published = $service->publish(1, 1, 'staging', '0530-09', '127.0.0.1', 'Publish approved UAT banner');
$report($published['code'] === 'LB3_BANNER_PUBLISHED' && $db->banners[1]['banner_status'] === 'PUBLISHED' && $db->banners[1]['configuration_version'] === 2, 'publish validates locale assets and increments version');
$report($expect(fn() => $service->publish(1, 1, 'staging', '0530-09', '127.0.0.1', 'Repeat stale publish attempt'), 'LB3_BANNER_STALE'), 'stale publish is rejected without overwrite');
$report(end($db->history)['outcome'] === 'REJECTED', 'rejected state mutation is recorded best-effort with correlation');

$rolledBack = $service->rollback(1, 2, 'staging', '0530-09', '127.0.0.1', 'Rollback approved UAT banner');
$report($rolledBack['code'] === 'LB3_BANNER_ROLLED_BACK' && $db->banners[1]['banner_status'] === 'DRAFT' && $db->banners[1]['configuration_version'] === 3, 'rollback restores the prior versioned state');
$service->publish(1, 3, 'staging', '0530-09', '127.0.0.1', 'Republish approved UAT banner');
$inactive = $service->inactivate(1, 4, 'staging', '0530-09', '127.0.0.1', 'Inactivate approved UAT banner');
$report($inactive['code'] === 'LB3_BANNER_INACTIVATED' && $db->banners[1]['banner_status'] === 'INACTIVE', 'inactivate allows only a currently published version');
$updatedInput = $input;
$updatedInput['alt_text_ms'] = 'Banner ujian OneID dikemas kini';
$updated = $service->updateDraft(1, 5, $updatedInput, ['ms' => null, 'en' => null], true, ...$context);
$report($updated['code'] === 'LB3_DRAFT_UPDATED' && $db->banners[1]['configuration_version'] === 6 && end($db->history)['action_name'] === 'UPDATE_DRAFT', 'inactive banner metadata updates as a new audited version without replacing its immutable key');

$db2 = new Lb3FakePersistence(); $images2 = new Lb3FakeImages(); $service2 = new LoginBannerService($db2, $images2);
$db2->failSuccessAudit = true;
$report($expect(fn() => $service2->createDraft($input, ['ms' => ['x' => 1], 'en' => null], true, ...$context), 'LB3_AUDIT_NOT_WRITTEN'), 'mandatory audit failure rejects draft creation');
$report($db2->banners === [] && count($images2->discardedPublished) === 1 && count($images2->discardedStaged) === 1, 'database rollback and filesystem compensation remove partial draft state');

$db3 = new Lb3FakePersistence(); $images3 = new Lb3FakeImages(); $service3 = new LoginBannerService($db3, $images3);
$separate = $service3->createDraft($input, ['ms' => ['x' => 1], 'en' => ['x' => 2]], false, ...$context);
$report($separate['same_image_for_english'] === false && count($db3->assets) === 2 && $db3->maps[1]['ms'] !== $db3->maps[1]['en'], 'separate BM and English uploads produce distinct locale assets');
$db3->banners[1]['banner_status'] = 'PUBLISHED';
$db3->banners[2] = ['banner_id' => 2, 'banner_status' => 'PUBLISHED', 'display_order' => 2, 'starts_at_utc' => null, 'ends_at_utc' => null, 'configuration_version' => 1];
$reordered = $service3->reorder([
    ['banner_id' => 1, 'expected_version' => 1, 'display_order' => 2],
    ['banner_id' => 2, 'expected_version' => 1, 'display_order' => 1],
], 'staging', '0530-09', '127.0.0.1', 'Reorder approved UAT banners');
$report($reordered['code'] === 'LB3_BANNERS_REORDERED' && $db3->banners[1]['display_order'] === 2 && $db3->banners[2]['display_order'] === 1, 'reorder updates all expected versions in one transaction');

echo "RESULT checks={$checks} failures={$failures}\n";
exit($failures === 0 ? 0 : 1);
