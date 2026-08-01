<?php

declare(strict_types=1);

namespace OneId\App\LoginBanner;

use Throwable;

final class LoginBannerService
{
    public function __construct(
        private readonly LoginBannerPersistenceInterface $persistence,
        private readonly LoginBannerImagePipelineInterface $images
    ) {
    }

    /**
     * @param array<string,mixed> $input
     * @param array{ms:?array,en:?array} $uploads
     * @return array<string,mixed>
     */
    public function createDraft(
        array $input,
        array $uploads,
        bool $sameImageForEnglish,
        string $environment,
        string $stagingDirectory,
        string $publishedDirectory,
        string $actorId,
        string $ipAddress,
        string $changeReason
    ): array {
        $this->assertContext($environment, $actorId, $ipAddress, $changeReason);
        $draft = $this->normalizeDraft($input, $actorId, $sameImageForEnglish);
        $correlation = bin2hex(random_bytes(8));
        $staged = [];
        $published = [];
        try {
            $staged['ms'] = $this->images->stageUpload($uploads['ms'] ?? null, $stagingDirectory);
            if (!$sameImageForEnglish) {
                $staged['en'] = $this->images->stageUpload($uploads['en'] ?? null, $stagingDirectory);
            }
            $result = $this->persistence->transactional(function () use (
                $draft,
                $staged,
                $sameImageForEnglish,
                $environment,
                $publishedDirectory,
                $actorId,
                $ipAddress,
                $changeReason,
                $correlation,
                &$published
            ): array {
                $bannerId = $this->persistence->insertBanner($draft['banner']);
                foreach ($draft['translations'] as $translation) {
                    $translation['banner_id'] = $bannerId;
                    if ($this->persistence->upsertTranslation($translation) < 1) {
                        throw new LoginBannerDomainException('LB3_TRANSLATION_NOT_WRITTEN');
                    }
                }
                $assetIds = [];
                foreach ($staged as $locale => $asset) {
                    $published[$locale] = $this->images->publish($asset, $publishedDirectory);
                    $assetIds[$locale] = $this->persistence->insertAsset([
                        'banner_id' => $bannerId,
                        'environment' => $environment,
                        'source_locale' => $locale,
                        'image_filename' => $asset['filename'],
                        'mime_type' => $asset['mime_type'],
                        'image_width' => $asset['width'],
                        'image_height' => $asset['height'],
                        'byte_size' => $asset['byte_size'],
                        'sha256_digest' => $asset['sha256_digest'],
                        'storage_status' => 'AVAILABLE',
                        'actor_id' => $actorId,
                    ]);
                }
                $englishAssetId = $sameImageForEnglish ? $assetIds['ms'] : $assetIds['en'];
                foreach (['ms' => $assetIds['ms'], 'en' => $englishAssetId] as $locale => $assetId) {
                    if ($this->persistence->mapLocaleAsset(
                        $bannerId,
                        $environment,
                        $locale,
                        $assetId,
                        $actorId
                    ) < 1) {
                        throw new LoginBannerDomainException('LB3_LOCALE_ASSET_NOT_MAPPED');
                    }
                }
                $after = $draft['banner'] + [
                    'banner_id' => $bannerId,
                    'configuration_version' => 1,
                    'same_image_for_english' => $sameImageForEnglish,
                    'asset_ids' => ['ms' => $assetIds['ms'], 'en' => $englishAssetId],
                ];
                $this->writeHistory([
                    'banner_id' => $bannerId,
                    'environment' => $environment,
                    'version_before' => null,
                    'version_after' => 1,
                    'actor_id' => $actorId,
                    'ip_address' => $ipAddress,
                    'action_name' => 'CREATE_DRAFT',
                    'outcome' => 'SUCCESS',
                    'reason_code' => 'LB3_DRAFT_CREATED',
                    'change_reason' => $changeReason,
                    'before' => null,
                    'after' => $after,
                    'correlation_id' => $correlation,
                ]);
                return [
                    'status' => 1,
                    'code' => 'LB3_DRAFT_CREATED',
                    'banner_id' => $bannerId,
                    'configuration_version' => 1,
                    'same_image_for_english' => $sameImageForEnglish,
                    'correlation_id' => $correlation,
                ];
            });
            return $result;
        } catch (Throwable $exception) {
            $this->compensateFiles($staged, $published, $stagingDirectory, $publishedDirectory);
            $this->recordRejectedBestEffort(
                null,
                $environment,
                $actorId,
                $ipAddress,
                'CREATE_DRAFT',
                $this->reasonCode($exception),
                $changeReason,
                $correlation
            );
            throw new LoginBannerDomainException($this->reasonCode($exception), $correlation, $exception);
        }
    }

    /** @return array<string,mixed> */
    public function publish(
        int $bannerId,
        int $expectedVersion,
        string $environment,
        string $actorId,
        string $ipAddress,
        string $changeReason
    ): array {
        return $this->changeState(
            $bannerId,
            $expectedVersion,
            'PUBLISHED',
            'PUBLISH',
            'LB3_BANNER_PUBLISHED',
            $environment,
            $actorId,
            $ipAddress,
            $changeReason,
            true
        );
    }

    /** @return array<string,mixed> */
    public function inactivate(
        int $bannerId,
        int $expectedVersion,
        string $environment,
        string $actorId,
        string $ipAddress,
        string $changeReason
    ): array {
        return $this->changeState(
            $bannerId,
            $expectedVersion,
            'INACTIVE',
            'INACTIVATE',
            'LB3_BANNER_INACTIVATED',
            $environment,
            $actorId,
            $ipAddress,
            $changeReason,
            false
        );
    }

    /** @param list<array{banner_id:int,expected_version:int,display_order:int}> $items */
    public function reorder(
        array $items,
        string $environment,
        string $actorId,
        string $ipAddress,
        string $changeReason
    ): array {
        $this->assertContext($environment, $actorId, $ipAddress, $changeReason);
        if ($items === [] || count($items) > 5) {
            throw new LoginBannerDomainException('LB3_REORDER_ITEMS_INVALID');
        }
        $ids = [];
        $orders = [];
        foreach ($items as $item) {
            $id = (int) ($item['banner_id'] ?? 0);
            $version = (int) ($item['expected_version'] ?? 0);
            $order = (int) ($item['display_order'] ?? 0);
            if ($id < 1 || $version < 1 || $order < 1 || $order > 5
                || isset($ids[$id]) || isset($orders[$order])
            ) {
                throw new LoginBannerDomainException('LB3_REORDER_ITEMS_INVALID');
            }
            $ids[$id] = true;
            $orders[$order] = true;
        }
        $batchCorrelation = bin2hex(random_bytes(8));
        try {
            return $this->persistence->transactional(function () use (
                $items, $environment, $actorId, $ipAddress, $changeReason
            ): array {
                $correlations = [];
                foreach ($items as $item) {
                    $current = $this->requiredBanner((int) $item['banner_id'], (int) $item['expected_version']);
                    if (($current['banner_status'] ?? '') !== 'PUBLISHED') {
                        throw new LoginBannerDomainException('LB3_REORDER_REQUIRES_PUBLISHED');
                    }
                    $changes = $this->stateChanges($current, 'PUBLISHED');
                    $changes['display_order'] = (int) $item['display_order'];
                    $this->updateExactlyOne($current, $changes, $actorId);
                    $correlation = bin2hex(random_bytes(8));
                    $correlations[] = $correlation;
                    $this->writeHistory($this->historyEvent(
                        $current,
                        $changes,
                        $environment,
                        $actorId,
                        $ipAddress,
                        $changeReason,
                        'REORDER',
                        'LB3_BANNER_REORDERED',
                        $correlation
                    ));
                }
                return ['status' => 1, 'code' => 'LB3_BANNERS_REORDERED', 'correlation_ids' => $correlations];
            });
        } catch (Throwable $exception) {
            $this->recordRejectedBestEffort(
                null, $environment, $actorId, $ipAddress, 'REORDER',
                $this->reasonCode($exception), $changeReason, $batchCorrelation
            );
            throw new LoginBannerDomainException($this->reasonCode($exception), $batchCorrelation, $exception);
        }
    }

    /** @return array<string,mixed> */
    public function rollback(
        int $bannerId,
        int $expectedVersion,
        string $environment,
        string $actorId,
        string $ipAddress,
        string $changeReason
    ): array {
        $this->assertContext($environment, $actorId, $ipAddress, $changeReason);
        $correlation = bin2hex(random_bytes(8));
        try {
            return $this->persistence->transactional(function () use (
                $bannerId, $expectedVersion, $environment, $actorId, $ipAddress,
                $changeReason, $correlation
            ): array {
                $current = $this->requiredBanner($bannerId, $expectedVersion);
                $history = $this->persistence->latestSuccessfulHistoryForUpdate($bannerId, $environment);
                $before = is_array($history) ? json_decode((string) ($history['before_json'] ?? ''), true) : null;
                if (!is_array($before)) {
                    throw new LoginBannerDomainException('LB3_ROLLBACK_STATE_UNAVAILABLE');
                }
                $status = (string) ($before['banner_status'] ?? '');
                if (!in_array($status, ['DRAFT', 'PUBLISHED', 'INACTIVE', 'ARCHIVED'], true)) {
                    throw new LoginBannerDomainException('LB3_ROLLBACK_STATE_INVALID');
                }
                $changes = [
                    'banner_status' => $status,
                    'display_order' => (int) ($before['display_order'] ?? 0),
                    'starts_at_utc' => $before['starts_at_utc'] ?? null,
                    'ends_at_utc' => $before['ends_at_utc'] ?? null,
                ];
                if ($status === 'PUBLISHED') {
                    $this->assertPublishable($bannerId, $environment, $current);
                }
                $this->updateExactlyOne($current, $changes, $actorId);
                $this->writeHistory($this->historyEvent(
                    $current,
                    $changes,
                    $environment,
                    $actorId,
                    $ipAddress,
                    $changeReason,
                    'ROLLBACK',
                    'LB3_BANNER_ROLLED_BACK',
                    $correlation
                ));
                return [
                    'status' => 1,
                    'code' => 'LB3_BANNER_ROLLED_BACK',
                    'banner_id' => $bannerId,
                    'configuration_version' => $expectedVersion + 1,
                    'correlation_id' => $correlation,
                ];
            });
        } catch (Throwable $exception) {
            $this->recordRejectedBestEffort(
                $bannerId, $environment, $actorId, $ipAddress, 'ROLLBACK',
                $this->reasonCode($exception), $changeReason, $correlation
            );
            throw new LoginBannerDomainException($this->reasonCode($exception), $correlation, $exception);
        }
    }

    /** @return array<string,mixed> */
    private function changeState(
        int $bannerId,
        int $expectedVersion,
        string $targetStatus,
        string $action,
        string $successCode,
        string $environment,
        string $actorId,
        string $ipAddress,
        string $changeReason,
        bool $validatePublish
    ): array {
        $this->assertContext($environment, $actorId, $ipAddress, $changeReason);
        $correlation = bin2hex(random_bytes(8));
        try {
            return $this->persistence->transactional(function () use (
                $bannerId, $expectedVersion, $targetStatus, $action, $successCode,
                $environment, $actorId, $ipAddress, $changeReason, $validatePublish,
                $correlation
            ): array {
                $current = $this->requiredBanner($bannerId, $expectedVersion);
                $currentStatus = (string) ($current['banner_status'] ?? '');
                if (($targetStatus === 'PUBLISHED' && !in_array($currentStatus, ['DRAFT', 'INACTIVE'], true))
                    || ($targetStatus === 'INACTIVE' && $currentStatus !== 'PUBLISHED')
                ) {
                    throw new LoginBannerDomainException('LB3_STATE_TRANSITION_INVALID');
                }
                if ($validatePublish) {
                    $this->assertPublishable($bannerId, $environment, $current);
                }
                $changes = $this->stateChanges($current, $targetStatus);
                $this->updateExactlyOne($current, $changes, $actorId);
                $this->writeHistory($this->historyEvent(
                    $current,
                    $changes,
                    $environment,
                    $actorId,
                    $ipAddress,
                    $changeReason,
                    $action,
                    $successCode,
                    $correlation
                ));
                return [
                    'status' => 1,
                    'code' => $successCode,
                    'banner_id' => $bannerId,
                    'configuration_version' => $expectedVersion + 1,
                    'correlation_id' => $correlation,
                ];
            });
        } catch (Throwable $exception) {
            $this->recordRejectedBestEffort(
                $bannerId, $environment, $actorId, $ipAddress, $action,
                $this->reasonCode($exception), $changeReason, $correlation
            );
            throw new LoginBannerDomainException($this->reasonCode($exception), $correlation, $exception);
        }
    }

    /** @return array<string,mixed> */
    private function requiredBanner(int $bannerId, int $expectedVersion): array
    {
        if ($bannerId < 1 || $expectedVersion < 1) {
            throw new LoginBannerDomainException('LB3_BANNER_REFERENCE_INVALID');
        }
        $current = $this->persistence->bannerForUpdate($bannerId);
        if (!is_array($current)) {
            throw new LoginBannerDomainException('LB3_BANNER_NOT_FOUND');
        }
        if ((int) ($current['configuration_version'] ?? 0) !== $expectedVersion) {
            throw new LoginBannerDomainException('LB3_BANNER_STALE');
        }
        return $current;
    }

    /** @param array<string,mixed> $current */
    private function assertPublishable(int $bannerId, string $environment, array $current): void
    {
        $rows = $this->persistence->localeAssetsForUpdate($bannerId, $environment);
        $byLocale = [];
        foreach ($rows as $row) {
            $byLocale[(string) ($row['locale'] ?? '')] = $row;
        }
        foreach (['ms', 'en'] as $locale) {
            $row = $byLocale[$locale] ?? null;
            if (!is_array($row)
                || (int) ($row['asset_id'] ?? 0) < 1
                || ($row['storage_status'] ?? '') !== 'AVAILABLE'
                || trim((string) ($row['alt_text'] ?? '')) === ''
            ) {
                throw new LoginBannerDomainException('LB3_LOCALE_ASSET_INCOMPLETE');
            }
        }
        if (($byLocale['en']['fallback_policy'] ?? '') === 'SAME_AS_MS'
            && (int) $byLocale['en']['asset_id'] !== (int) $byLocale['ms']['asset_id']
        ) {
            throw new LoginBannerDomainException('LB3_SAME_AS_MS_MAPPING_INVALID');
        }
        $published = $this->persistence->publishedForUpdate($environment);
        $overlaps = 0;
        foreach ($published as $row) {
            if ((int) ($row['banner_id'] ?? 0) === $bannerId) {
                continue;
            }
            if ($this->schedulesOverlap($current, $row)) {
                $overlaps++;
            }
        }
        if ($overlaps >= 5) {
            throw new LoginBannerDomainException('LB3_ACTIVE_BANNER_LIMIT');
        }
    }

    /** @param array<string,mixed> $left @param array<string,mixed> $right */
    private function schedulesOverlap(array $left, array $right): bool
    {
        $leftStart = $left['starts_at_utc'] ?? null;
        $leftEnd = $left['ends_at_utc'] ?? null;
        $rightStart = $right['starts_at_utc'] ?? null;
        $rightEnd = $right['ends_at_utc'] ?? null;
        return ($leftEnd === null || $rightStart === null || $rightStart < $leftEnd)
            && ($rightEnd === null || $leftStart === null || $leftStart < $rightEnd);
    }

    /** @param array<string,mixed> $current @return array<string,mixed> */
    private function stateChanges(array $current, string $status): array
    {
        return [
            'banner_status' => $status,
            'display_order' => (int) ($current['display_order'] ?? 0),
            'starts_at_utc' => $current['starts_at_utc'] ?? null,
            'ends_at_utc' => $current['ends_at_utc'] ?? null,
        ];
    }

    /** @param array<string,mixed> $current @param array<string,mixed> $changes */
    private function updateExactlyOne(array $current, array $changes, string $actorId): void
    {
        if ($this->persistence->updateBannerVersioned(
            (int) $current['banner_id'],
            (int) $current['configuration_version'],
            $changes,
            $actorId
        ) !== 1) {
            throw new LoginBannerDomainException('LB3_BANNER_STALE');
        }
    }

    /** @param array<string,mixed> $event */
    private function writeHistory(array $event): void
    {
        if ($this->persistence->recordHistory($event) !== 1) {
            throw new LoginBannerDomainException('LB3_AUDIT_NOT_WRITTEN');
        }
    }

    /** @param array<string,mixed> $current @param array<string,mixed> $changes @return array<string,mixed> */
    private function historyEvent(
        array $current,
        array $changes,
        string $environment,
        string $actorId,
        string $ipAddress,
        string $changeReason,
        string $action,
        string $reasonCode,
        string $correlation
    ): array {
        $next = $changes + [
            'banner_id' => (int) $current['banner_id'],
            'configuration_version' => (int) $current['configuration_version'] + 1,
        ];
        return [
            'banner_id' => (int) $current['banner_id'],
            'environment' => $environment,
            'version_before' => (int) $current['configuration_version'],
            'version_after' => (int) $current['configuration_version'] + 1,
            'actor_id' => $actorId,
            'ip_address' => $ipAddress,
            'action_name' => $action,
            'outcome' => 'SUCCESS',
            'reason_code' => $reasonCode,
            'change_reason' => $changeReason,
            'before' => $current,
            'after' => $next,
            'correlation_id' => $correlation,
        ];
    }

    /** @return array{banner:array<string,mixed>,translations:list<array<string,mixed>>} */
    private function normalizeDraft(array $input, string $actorId, bool $sameImageForEnglish): array
    {
        $key = strtoupper(trim((string) ($input['banner_key'] ?? '')));
        $order = (int) ($input['display_order'] ?? 0);
        $altMs = trim((string) ($input['alt_text_ms'] ?? ''));
        $altEn = trim((string) ($input['alt_text_en'] ?? ''));
        $starts = $this->nullableUtc($input['starts_at_utc'] ?? null);
        $ends = $this->nullableUtc($input['ends_at_utc'] ?? null);
        if (preg_match('/^LB-[A-Z0-9][A-Z0-9_-]{2,61}$/D', $key) !== 1
            || $order < 1 || $order > 5
            || mb_strlen($altMs) < 5 || mb_strlen($altMs) > 160
            || mb_strlen($altEn) < 5 || mb_strlen($altEn) > 160
            || ($starts !== null && $ends !== null && $starts >= $ends)
        ) {
            throw new LoginBannerDomainException('LB3_DRAFT_INPUT_INVALID');
        }
        return [
            'banner' => [
                'banner_key' => $key,
                'display_order' => $order,
                'starts_at_utc' => $starts,
                'ends_at_utc' => $ends,
                'actor_id' => $actorId,
                'banner_status' => 'DRAFT',
            ],
            'translations' => [
                ['locale' => 'ms', 'alt_text' => $altMs, 'fallback_policy' => 'OWN_ASSET', 'actor_id' => $actorId],
                ['locale' => 'en', 'alt_text' => $altEn, 'fallback_policy' => $sameImageForEnglish ? 'SAME_AS_MS' : 'OWN_ASSET', 'actor_id' => $actorId],
            ],
        ];
    }

    private function assertContext(string $environment, string $actorId, string $ipAddress, string $reason): void
    {
        if (preg_match('/^[a-z][a-z0-9_-]{1,31}$/D', $environment) !== 1
            || trim($actorId) === '' || strlen($actorId) > 20
            || filter_var($ipAddress, FILTER_VALIDATE_IP) === false
            || mb_strlen(trim($reason)) < 10 || mb_strlen(trim($reason)) > 500
        ) {
            throw new LoginBannerDomainException('LB3_CONTEXT_INVALID');
        }
    }

    private function nullableUtc(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        $value = trim((string) $value);
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $value) !== 1
            || strtotime($value . ' UTC') === false
        ) {
            throw new LoginBannerDomainException('LB3_SCHEDULE_INVALID');
        }
        return $value;
    }

    /** @param array<string,array<string,mixed>> $staged @param array<string,string> $published */
    private function compensateFiles(
        array $staged,
        array $published,
        string $stagingDirectory,
        string $publishedDirectory
    ): void {
        foreach ($staged as $asset) {
            try {
                $this->images->discardStaged($asset, $stagingDirectory);
            } catch (Throwable $cleanupError) {
                error_log('LB3 staged compensation failed: ' . $cleanupError->getMessage());
            }
        }
        foreach ($published as $path) {
            try {
                $this->images->discardPublished($path, $publishedDirectory);
            } catch (Throwable $cleanupError) {
                error_log('LB3 published compensation failed: ' . $cleanupError->getMessage());
            }
        }
    }

    private function recordRejectedBestEffort(
        ?int $bannerId,
        string $environment,
        string $actorId,
        string $ipAddress,
        string $action,
        string $reasonCode,
        string $changeReason,
        string $correlation
    ): void {
        try {
            $this->persistence->transactional(function () use (
                $bannerId, $environment, $actorId, $ipAddress, $action,
                $reasonCode, $changeReason, $correlation
            ): void {
                $this->writeHistory([
                    'banner_id' => $bannerId,
                    'environment' => $environment,
                    'version_before' => null,
                    'version_after' => null,
                    'actor_id' => $actorId,
                    'ip_address' => $ipAddress,
                    'action_name' => $action,
                    'outcome' => 'REJECTED',
                    'reason_code' => substr($reasonCode, 0, 64),
                    'change_reason' => $changeReason,
                    'before' => null,
                    'after' => null,
                    'correlation_id' => $correlation,
                ]);
            });
        } catch (Throwable $auditError) {
            error_log('LB3 rejection audit failed correlation=' . $correlation);
        }
    }

    private function reasonCode(Throwable $exception): string
    {
        $message = trim($exception->getMessage());
        return preg_match('/^LB[0-9]_[A-Z0-9_]{1,60}$/D', $message) === 1
            ? $message
            : 'LB3_OPERATION_REJECTED';
    }
}
