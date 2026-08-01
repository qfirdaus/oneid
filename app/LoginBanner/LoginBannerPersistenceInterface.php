<?php

declare(strict_types=1);

namespace OneId\App\LoginBanner;

interface LoginBannerPersistenceInterface
{
    /** @return array{available:bool,tables:array<string,bool>} */
    public function schemaStatus(): array;

    /** @return list<array<string,mixed>> */
    public function adminList(string $environment): array;

    public function transactional(callable $operation): mixed;

    /** @return list<array<string,mixed>> */
    public function publishedForLocale(
        string $environment,
        string $locale,
        string $effectiveAtUtc
    ): array;

    /** @return array<string,mixed>|null */
    public function bannerForUpdate(int $bannerId): ?array;

    /** @return list<array<string,mixed>> */
    public function localeAssetsForUpdate(int $bannerId, string $environment): array;

    /** @return list<array<string,mixed>> */
    public function publishedForUpdate(string $environment): array;

    /** @return array<string,mixed>|null */
    public function latestSuccessfulHistoryForUpdate(int $bannerId, string $environment): ?array;

    /** @param array<string,mixed> $banner */
    public function insertBanner(array $banner): int;

    /** @param array<string,mixed> $translation */
    public function upsertTranslation(array $translation): int;

    /** @param array<string,mixed> $asset */
    public function insertAsset(array $asset): int;

    public function assetIdByDigestForUpdate(
        int $bannerId,
        string $environment,
        string $sha256Digest
    ): ?int;

    public function mapLocaleAsset(
        int $bannerId,
        string $environment,
        string $locale,
        int $assetId,
        string $actorId
    ): int;

    /** @param array<string,mixed> $changes */
    public function updateBannerVersioned(
        int $bannerId,
        int $expectedVersion,
        array $changes,
        string $actorId
    ): int;

    /** @param array<string,mixed> $changes */
    public function updateDraftVersioned(
        int $bannerId,
        int $expectedVersion,
        array $changes,
        string $actorId
    ): int;

    /** @param array<string,mixed> $event */
    public function recordHistory(array $event): int;
}
