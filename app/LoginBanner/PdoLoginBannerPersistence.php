<?php

declare(strict_types=1);

namespace OneId\App\LoginBanner;

use PDO;
use Throwable;

final class PdoLoginBannerPersistence implements LoginBannerPersistenceInterface
{
    private const TABLES = [
        'login_banner',
        'login_banner_translation',
        'login_banner_asset',
        'login_banner_locale_asset',
        'login_banner_history',
    ];

    public function __construct(private readonly PDO $pdo)
    {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    public function schemaStatus(): array
    {
        $placeholders = implode(',', array_fill(0, count(self::TABLES), '?'));
        $statement = $this->pdo->prepare(
            "SELECT TABLE_NAME FROM information_schema.TABLES
             WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ({$placeholders})"
        );
        $statement->execute(self::TABLES);
        $present = array_fill_keys(self::TABLES, false);
        foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $table) {
            $present[(string) $table] = true;
        }
        return ['available' => !in_array(false, $present, true), 'tables' => $present];
    }

    public function adminList(string $environment): array
    {
        $this->assertEnvironment($environment);
        $statement = $this->pdo->prepare(
            "SELECT b.banner_id,b.banner_key,b.banner_status,b.display_order,
                    b.starts_at_utc,b.ends_at_utc,b.configuration_version,
                    b.created_by,b.updated_by,b.created_at,b.updated_at,
                    t.locale,t.alt_text,t.fallback_policy,m.asset_id,
                    a.image_filename,a.mime_type,a.image_width,a.image_height,
                    a.byte_size,a.sha256_digest,a.storage_status
               FROM login_banner b
               LEFT JOIN login_banner_translation t ON t.banner_id=b.banner_id
               LEFT JOIN login_banner_locale_asset m
                 ON m.banner_id=b.banner_id AND m.locale=t.locale
                AND m.environment=:environment
               LEFT JOIN login_banner_asset a
                 ON a.asset_id=m.asset_id AND a.banner_id=b.banner_id
                AND a.environment=m.environment
              ORDER BY b.display_order,b.banner_id,t.locale"
        );
        $statement->execute([':environment' => $environment]);
        return $statement->fetchAll();
    }

    public function transactional(callable $operation): mixed
    {
        if ($this->pdo->inTransaction()) {
            throw new LoginBannerPersistenceException('LB1_TRANSACTION_ALREADY_ACTIVE');
        }
        $this->pdo->beginTransaction();
        try {
            $result = $operation($this);
            $this->pdo->commit();
            return $result;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function publishedForLocale(
        string $environment,
        string $locale,
        string $effectiveAtUtc
    ): array {
        $this->assertEnvironment($environment);
        $this->assertLocale($locale);
        $this->assertUtcDateTime($effectiveAtUtc);
        $statement = $this->pdo->prepare(
            "SELECT b.banner_id,b.banner_key,b.display_order,b.starts_at_utc,b.ends_at_utc,
                    b.configuration_version,t.locale,t.alt_text,t.fallback_policy,
                    a.asset_id,a.image_filename,a.mime_type,a.image_width,a.image_height,
                    a.byte_size,a.sha256_digest
               FROM login_banner b
               JOIN login_banner_translation t
                 ON t.banner_id=b.banner_id AND t.locale=:locale
               JOIN login_banner_locale_asset m
                 ON m.banner_id=b.banner_id AND m.locale=t.locale
                AND m.environment=:environment
               JOIN login_banner_asset a
                 ON a.asset_id=m.asset_id AND a.banner_id=b.banner_id
                AND a.environment=m.environment AND a.storage_status='AVAILABLE'
              WHERE b.banner_status='PUBLISHED'
                AND (b.starts_at_utc IS NULL OR b.starts_at_utc<=:effective_at)
                AND (b.ends_at_utc IS NULL OR b.ends_at_utc>:effective_at)
              ORDER BY b.display_order,b.banner_id
              LIMIT 5"
        );
        $statement->execute([
            ':locale' => $locale,
            ':environment' => $environment,
            ':effective_at' => $effectiveAtUtc,
        ]);
        return $statement->fetchAll();
    }

    public function bannerForUpdate(int $bannerId): ?array
    {
        $this->assertId($bannerId, 'LB1_BANNER_ID_INVALID');
        $statement = $this->pdo->prepare(
            'SELECT banner_id,banner_key,banner_status,display_order,starts_at_utc,
                    ends_at_utc,configuration_version,created_by,updated_by,created_at,updated_at
               FROM login_banner WHERE banner_id=:banner_id LIMIT 1 FOR UPDATE'
        );
        $statement->execute([':banner_id' => $bannerId]);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    public function localeAssetsForUpdate(int $bannerId, string $environment): array
    {
        $this->assertId($bannerId, 'LB1_BANNER_ID_INVALID');
        $this->assertEnvironment($environment);
        $statement = $this->pdo->prepare(
            "SELECT t.locale,t.alt_text,t.fallback_policy,m.asset_id,
                    a.image_filename,a.mime_type,a.image_width,a.image_height,
                    a.byte_size,a.sha256_digest,a.storage_status
               FROM login_banner_translation t
               LEFT JOIN login_banner_locale_asset m
                 ON m.banner_id=t.banner_id AND m.locale=t.locale
                AND m.environment=:environment
               LEFT JOIN login_banner_asset a
                 ON a.asset_id=m.asset_id AND a.banner_id=t.banner_id
                AND a.environment=m.environment
              WHERE t.banner_id=:banner_id
              ORDER BY t.locale
              FOR UPDATE"
        );
        $statement->execute([':banner_id' => $bannerId, ':environment' => $environment]);
        return $statement->fetchAll();
    }

    public function publishedForUpdate(string $environment): array
    {
        $this->assertEnvironment($environment);
        $statement = $this->pdo->prepare(
            "SELECT DISTINCT b.banner_id,b.banner_status,b.display_order,b.starts_at_utc,
                    b.ends_at_utc,b.configuration_version
               FROM login_banner b
               JOIN login_banner_locale_asset m ON m.banner_id=b.banner_id
              WHERE b.banner_status='PUBLISHED' AND m.environment=:environment
              ORDER BY b.display_order,b.banner_id
              FOR UPDATE"
        );
        $statement->execute([':environment' => $environment]);
        return $statement->fetchAll();
    }

    public function latestSuccessfulHistoryForUpdate(int $bannerId, string $environment): ?array
    {
        $this->assertId($bannerId, 'LB1_BANNER_ID_INVALID');
        $this->assertEnvironment($environment);
        $statement = $this->pdo->prepare(
            "SELECT history_id,banner_id,environment,configuration_version_before,
                    configuration_version_after,action_name,before_json,after_json,
                    correlation_id,created_at
               FROM login_banner_history
              WHERE banner_id=:banner_id AND environment=:environment
                AND outcome='SUCCESS' AND before_json IS NOT NULL
              ORDER BY history_id DESC LIMIT 1 FOR UPDATE"
        );
        $statement->execute([':banner_id' => $bannerId, ':environment' => $environment]);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    public function insertBanner(array $banner): int
    {
        $statement = $this->pdo->prepare(
            "INSERT INTO login_banner(
                banner_key,banner_status,display_order,starts_at_utc,ends_at_utc,
                configuration_version,created_by,updated_by
             ) VALUES(
                :banner_key,'DRAFT',:display_order,:starts_at_utc,:ends_at_utc,
                1,:actor_id,:actor_id
             )"
        );
        $statement->execute([
            ':banner_key' => (string) ($banner['banner_key'] ?? ''),
            ':display_order' => (int) ($banner['display_order'] ?? 1),
            ':starts_at_utc' => $banner['starts_at_utc'] ?? null,
            ':ends_at_utc' => $banner['ends_at_utc'] ?? null,
            ':actor_id' => (string) ($banner['actor_id'] ?? ''),
        ]);
        $id = (int) $this->pdo->lastInsertId();
        if ($statement->rowCount() !== 1 || $id < 1) {
            throw new LoginBannerPersistenceException('LB1_BANNER_NOT_INSERTED');
        }
        return $id;
    }

    public function upsertTranslation(array $translation): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO login_banner_translation(
                banner_id,locale,alt_text,fallback_policy,created_by,updated_by
             ) VALUES(:banner_id,:locale,:alt_text,:fallback_policy,:actor_id,:actor_id)
             ON DUPLICATE KEY UPDATE alt_text=VALUES(alt_text),
                fallback_policy=VALUES(fallback_policy),updated_by=VALUES(updated_by)'
        );
        $statement->execute([
            ':banner_id' => (int) ($translation['banner_id'] ?? 0),
            ':locale' => (string) ($translation['locale'] ?? ''),
            ':alt_text' => (string) ($translation['alt_text'] ?? ''),
            ':fallback_policy' => (string) ($translation['fallback_policy'] ?? ''),
            ':actor_id' => (string) ($translation['actor_id'] ?? ''),
        ]);
        return $statement->rowCount();
    }

    public function insertAsset(array $asset): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO login_banner_asset(
                banner_id,environment,source_locale,image_filename,mime_type,
                image_width,image_height,byte_size,sha256_digest,storage_status,created_by
             ) VALUES(
                :banner_id,:environment,:source_locale,:image_filename,:mime_type,
                :image_width,:image_height,:byte_size,:sha256_digest,:storage_status,:actor_id
             )'
        );
        $statement->execute([
            ':banner_id' => (int) ($asset['banner_id'] ?? 0),
            ':environment' => (string) ($asset['environment'] ?? ''),
            ':source_locale' => (string) ($asset['source_locale'] ?? ''),
            ':image_filename' => (string) ($asset['image_filename'] ?? ''),
            ':mime_type' => (string) ($asset['mime_type'] ?? ''),
            ':image_width' => (int) ($asset['image_width'] ?? 0),
            ':image_height' => (int) ($asset['image_height'] ?? 0),
            ':byte_size' => (int) ($asset['byte_size'] ?? 0),
            ':sha256_digest' => (string) ($asset['sha256_digest'] ?? ''),
            ':storage_status' => (string) ($asset['storage_status'] ?? 'STAGED'),
            ':actor_id' => (string) ($asset['actor_id'] ?? ''),
        ]);
        $id = (int) $this->pdo->lastInsertId();
        if ($statement->rowCount() !== 1 || $id < 1) {
            throw new LoginBannerPersistenceException('LB1_ASSET_NOT_INSERTED');
        }
        return $id;
    }

    public function mapLocaleAsset(
        int $bannerId,
        string $environment,
        string $locale,
        int $assetId,
        string $actorId
    ): int {
        $this->assertId($bannerId, 'LB1_BANNER_ID_INVALID');
        $this->assertId($assetId, 'LB1_ASSET_ID_INVALID');
        $this->assertEnvironment($environment);
        $this->assertLocale($locale);
        $statement = $this->pdo->prepare(
            'INSERT INTO login_banner_locale_asset(
                banner_id,environment,locale,asset_id,mapped_by
             ) VALUES(:banner_id,:environment,:locale,:asset_id,:actor_id)
             ON DUPLICATE KEY UPDATE asset_id=VALUES(asset_id),mapped_by=VALUES(mapped_by),mapped_at=NOW()'
        );
        $statement->execute([
            ':banner_id' => $bannerId,
            ':environment' => $environment,
            ':locale' => $locale,
            ':asset_id' => $assetId,
            ':actor_id' => $actorId,
        ]);
        return $statement->rowCount();
    }

    public function updateBannerVersioned(
        int $bannerId,
        int $expectedVersion,
        array $changes,
        string $actorId
    ): int {
        $this->assertId($bannerId, 'LB1_BANNER_ID_INVALID');
        $status = (string) ($changes['banner_status'] ?? '');
        if (!in_array($status, ['DRAFT', 'PUBLISHED', 'INACTIVE', 'ARCHIVED'], true)) {
            throw new LoginBannerPersistenceException('LB1_BANNER_STATUS_INVALID');
        }
        $statement = $this->pdo->prepare(
            'UPDATE login_banner
                SET banner_status=:banner_status,display_order=:display_order,
                    starts_at_utc=:starts_at_utc,ends_at_utc=:ends_at_utc,
                    configuration_version=configuration_version+1,updated_by=:actor_id
              WHERE banner_id=:banner_id AND configuration_version=:expected_version'
        );
        $statement->execute([
            ':banner_status' => $status,
            ':display_order' => (int) ($changes['display_order'] ?? 0),
            ':starts_at_utc' => $changes['starts_at_utc'] ?? null,
            ':ends_at_utc' => $changes['ends_at_utc'] ?? null,
            ':actor_id' => $actorId,
            ':banner_id' => $bannerId,
            ':expected_version' => $expectedVersion,
        ]);
        return $statement->rowCount();
    }

    public function recordHistory(array $event): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO login_banner_history(
                banner_id,environment,configuration_version_before,configuration_version_after,
                actor_id,ip_address,action_name,outcome,reason_code,change_reason,
                before_json,after_json,correlation_id
             ) VALUES(
                :banner_id,:environment,:version_before,:version_after,:actor_id,:ip_address,
                :action_name,:outcome,:reason_code,:change_reason,:before_json,:after_json,:correlation_id
             )'
        );
        $statement->execute([
            ':banner_id' => $event['banner_id'] ?? null,
            ':environment' => (string) ($event['environment'] ?? ''),
            ':version_before' => $event['version_before'] ?? null,
            ':version_after' => $event['version_after'] ?? null,
            ':actor_id' => (string) ($event['actor_id'] ?? ''),
            ':ip_address' => (string) ($event['ip_address'] ?? ''),
            ':action_name' => (string) ($event['action_name'] ?? ''),
            ':outcome' => (string) ($event['outcome'] ?? ''),
            ':reason_code' => (string) ($event['reason_code'] ?? ''),
            ':change_reason' => $event['change_reason'] ?? null,
            ':before_json' => isset($event['before']) ? json_encode($event['before'], JSON_THROW_ON_ERROR) : null,
            ':after_json' => isset($event['after']) ? json_encode($event['after'], JSON_THROW_ON_ERROR) : null,
            ':correlation_id' => (string) ($event['correlation_id'] ?? ''),
        ]);
        return $statement->rowCount();
    }

    private function assertId(int $id, string $code): void
    {
        if ($id < 1) {
            throw new LoginBannerPersistenceException($code);
        }
    }

    private function assertEnvironment(string $environment): void
    {
        if (preg_match('/^[a-z][a-z0-9_-]{1,31}$/D', $environment) !== 1) {
            throw new LoginBannerPersistenceException('LB1_ENVIRONMENT_INVALID');
        }
    }

    private function assertLocale(string $locale): void
    {
        if (!in_array($locale, ['ms', 'en'], true)) {
            throw new LoginBannerPersistenceException('LB1_LOCALE_INVALID');
        }
    }

    private function assertUtcDateTime(string $value): void
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $value) !== 1) {
            throw new LoginBannerPersistenceException('LB1_EFFECTIVE_TIME_INVALID');
        }
    }
}
