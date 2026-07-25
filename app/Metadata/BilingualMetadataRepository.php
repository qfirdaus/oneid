<?php
declare(strict_types=1);

namespace OneId\App\Metadata;

use PDO;
use PDOException;
use RuntimeException;
use Throwable;

final class BilingualMetadataRepository
{
    public function __construct(private readonly PDO $pdo)
    {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    /** @return array{available:bool,tables:array<string,bool>} */
    public function schemaStatus(): array
    {
        $required = ['sp_app_translation', 'sp_group_translation', 'metadata_translation_history'];
        $statement = $this->pdo->prepare(
            'SELECT TABLE_NAME FROM information_schema.TABLES
             WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN (?,?,?)'
        );
        $statement->execute($required);
        $present = array_fill_keys($required, false);
        foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $table) {
            $present[(string) $table] = true;
        }
        return [
            'available' => !in_array(false, $present, true),
            'tables' => $present,
        ];
    }

    /** @param list<array<string,mixed>> $groups
     *  @return list<array<string,mixed>>
     */
    public function localizeGroups(array $groups, string $locale): array
    {
        if (!in_array($locale, ['ms', 'en'], true) || !$this->schemaStatus()['available']) {
            return $this->fallbackGroups($groups, $locale, 'schema_unavailable');
        }
        $groupIds = [];
        $appIds = [];
        foreach ($groups as $group) {
            $groupIds[] = (string) ($group['sp_group_id'] ?? '');
            foreach (($group['data'] ?? []) as $application) {
                $appIds[] = (string) ($application['sp_id'] ?? '');
            }
        }
        $groupTranslations = $this->groupTranslations($groupIds, $locale);
        $appTranslations = $this->appTranslations($appIds, $locale);
        foreach ($groups as &$group) {
            $groupId = (string) ($group['sp_group_id'] ?? '');
            $originalGroupName = (string) ($group['sp_group_name'] ?? '');
            $group['sp_group_name_original'] = $originalGroupName;
            $group['sp_group_name'] = $groupTranslations[$groupId]['sp_group_name'] ?? $originalGroupName;
            $group['metadata_locale'] = isset($groupTranslations[$groupId]) ? $locale : 'original';
            $group['metadata_fallback'] = isset($groupTranslations[$groupId]) ? 0 : 1;
            if (isset($group['data']) && is_array($group['data'])) {
                foreach ($group['data'] as &$application) {
                    $appId = (string) ($application['sp_id'] ?? '');
                    $originalName = (string) ($application['sp_name'] ?? '');
                    $originalDescription = (string) ($application['sp_description'] ?? '');
                    $application['sp_name_original'] = $originalName;
                    $application['sp_description_original'] = $originalDescription;
                    $application['sp_name'] = $appTranslations[$appId]['sp_name'] ?? $originalName;
                    $application['sp_description'] = $appTranslations[$appId]['sp_description'] ?? $originalDescription;
                    $application['metadata_locale'] = isset($appTranslations[$appId]) ? $locale : 'original';
                    $application['metadata_fallback'] = isset($appTranslations[$appId]) ? 0 : 1;
                }
                unset($application);
            }
        }
        unset($group);
        return $groups;
    }

    /** @return array<string,mixed> */
    public function preview(): array
    {
        $schema = $this->schemaStatus();
        $apps = (int) $this->pdo->query('SELECT COUNT(*) FROM sp_list')->fetchColumn();
        $categories = (int) $this->pdo->query('SELECT COUNT(*) FROM sp_group')->fetchColumn();
        $appTranslations = 0;
        $categoryTranslations = 0;
        $byLocale = [
            'ms' => ['applications' => 0, 'categories' => 0],
            'en' => ['applications' => 0, 'categories' => 0],
        ];
        if ($schema['available']) {
            $appTranslations = (int) $this->pdo->query('SELECT COUNT(*) FROM sp_app_translation')->fetchColumn();
            $categoryTranslations = (int) $this->pdo->query('SELECT COUNT(*) FROM sp_group_translation')->fetchColumn();
            foreach ($this->pdo->query(
                'SELECT locale,COUNT(*) AS total FROM sp_app_translation GROUP BY locale'
            )->fetchAll() as $row) {
                $byLocale[(string) $row['locale']]['applications'] = (int) $row['total'];
            }
            foreach ($this->pdo->query(
                'SELECT locale,COUNT(*) AS total FROM sp_group_translation GROUP BY locale'
            )->fetchAll() as $row) {
                $byLocale[(string) $row['locale']]['categories'] = (int) $row['total'];
            }
        }
        foreach ($byLocale as &$coverage) {
            $coverage['missing_applications'] = max(0, $apps - $coverage['applications']);
            $coverage['missing_categories'] = max(0, $categories - $coverage['categories']);
            $coverage['application_percent'] = $apps === 0
                ? 100
                : round(($coverage['applications'] / $apps) * 100, 2);
            $coverage['category_percent'] = $categories === 0
                ? 100
                : round(($coverage['categories'] / $categories) * 100, 2);
        }
        unset($coverage);
        return [
            'status' => 1,
            'code' => $schema['available'] ? 'ML7_METADATA_SCHEMA_READY' : 'ML7_METADATA_SCHEMA_DORMANT',
            'schema_available' => $schema['available'],
            'tables' => $schema['tables'],
            'source' => ['applications' => $apps, 'categories' => $categories],
            'translations' => [
                'applications' => $appTranslations,
                'categories' => $categoryTranslations,
                'by_locale' => $byLocale,
            ],
            'fallback_to_original' => true,
            'can_apply_migration' => false,
            'mutation_statements' => 0,
        ];
    }

    /** @return array<string,mixed> */
    public function read(string $entityType, string $entityId, string $locale): array
    {
        $this->assertEntity($entityType, $entityId, $locale);
        if (!$this->schemaStatus()['available']) {
            throw new RuntimeException('ML7_METADATA_SCHEMA_UNAVAILABLE');
        }
        [$table, $idColumn] = $this->tableFor($entityType);
        $statement = $this->pdo->prepare(
            "SELECT * FROM {$table} WHERE {$idColumn}=:entity_id AND locale=:locale LIMIT 1"
        );
        $statement->execute([':entity_id' => $entityId, ':locale' => $locale]);
        return $statement->fetch() ?: [
            $idColumn => $entityId,
            'locale' => $locale,
            'translation_version' => 0,
        ];
    }

    /** @param array<string,string> $values
     *  @return array<string,mixed>
     */
    public function save(
        string $entityType,
        string $entityId,
        string $locale,
        array $values,
        int $expectedVersion,
        string $actor,
        string $reason
    ): array {
        $this->assertEntity($entityType, $entityId, $locale);
        if (!$this->schemaStatus()['available']) {
            throw new RuntimeException('ML7_METADATA_SCHEMA_UNAVAILABLE');
        }
        if ($expectedVersion < 0 || mb_strlen(trim($reason)) < 10 || mb_strlen(trim($reason)) > 500) {
            throw new RuntimeException('ML7_METADATA_APPROVAL_INVALID');
        }
        $correlation = bin2hex(random_bytes(8));
        [$table, $idColumn] = $this->tableFor($entityType);
        $started = false;
        try {
            $this->pdo->beginTransaction();
            $started = true;
            $current = $this->readForUpdate($table, $idColumn, $entityId, $locale);
            $currentVersion = (int) ($current['translation_version'] ?? 0);
            if ($currentVersion !== $expectedVersion) {
                throw new RuntimeException('ML7_METADATA_STALE');
            }
            $normalized = $this->normalizedValues($entityType, $values);
            $nextVersion = $currentVersion + 1;
            if ($currentVersion === 0) {
                $columns = $entityType === 'application'
                    ? "sp_id,locale,sp_name,sp_description,translation_version,created_by,updated_by"
                    : "sp_group_id,locale,sp_group_name,translation_version,created_by,updated_by";
                $parameters = $entityType === 'application'
                    ? ":entity_id,:locale,:name,:description,:version,:actor,:actor"
                    : ":entity_id,:locale,:name,:version,:actor,:actor";
                $statement = $this->pdo->prepare("INSERT INTO {$table}({$columns}) VALUES({$parameters})");
            } else {
                $fields = $entityType === 'application'
                    ? 'sp_name=:name,sp_description=:description'
                    : 'sp_group_name=:name';
                $statement = $this->pdo->prepare(
                    "UPDATE {$table} SET {$fields},translation_version=:version,updated_by=:actor
                     WHERE {$idColumn}=:entity_id AND locale=:locale AND translation_version=:expected_version"
                );
            }
            $parameters = [
                ':entity_id' => $entityId,
                ':locale' => $locale,
                ':name' => $normalized['name'],
                ':version' => $nextVersion,
                ':actor' => $actor,
            ];
            if ($entityType === 'application') {
                $parameters[':description'] = $normalized['description'];
            }
            if ($currentVersion > 0) {
                $parameters[':expected_version'] = $currentVersion;
            }
            $statement->execute($parameters);
            if ($statement->rowCount() !== 1) {
                throw new RuntimeException('ML7_METADATA_STALE');
            }
            $history = $this->pdo->prepare(
                'INSERT INTO metadata_translation_history(
                    entity_type,entity_id,locale,version_before,version_after,actor_id,
                    change_reason,before_json,after_json,correlation_id
                 ) VALUES(
                    :entity_type,:entity_id,:locale,:before_version,:after_version,:actor,
                    :reason,:before_json,:after_json,:correlation
                 )'
            );
            $history->execute([
                ':entity_type' => $entityType,
                ':entity_id' => $entityId,
                ':locale' => $locale,
                ':before_version' => $currentVersion === 0 ? null : $currentVersion,
                ':after_version' => $nextVersion,
                ':actor' => $actor,
                ':reason' => trim($reason),
                ':before_json' => $currentVersion === 0 ? null : json_encode($current, JSON_THROW_ON_ERROR),
                ':after_json' => json_encode($normalized, JSON_THROW_ON_ERROR),
                ':correlation' => $correlation,
            ]);
            $this->pdo->commit();
            $started = false;
            return [
                'status' => 1,
                'code' => 'ML7_METADATA_TRANSLATION_SAVED',
                'translation_version' => $nextVersion,
                'correlation_id' => $correlation,
            ];
        } catch (Throwable $exception) {
            if ($started && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** @param list<array<string,mixed>> $groups
     *  @return list<array<string,mixed>>
     */
    private function fallbackGroups(array $groups, string $locale, string $reason): array
    {
        foreach ($groups as &$group) {
            $group['sp_group_name_original'] = (string) ($group['sp_group_name'] ?? '');
            $group['metadata_locale'] = 'original';
            $group['metadata_fallback'] = 1;
            $group['metadata_fallback_reason'] = $reason;
            if (isset($group['data']) && is_array($group['data'])) {
                foreach ($group['data'] as &$application) {
                    $application['sp_name_original'] = (string) ($application['sp_name'] ?? '');
                    $application['sp_description_original'] = (string) ($application['sp_description'] ?? '');
                    $application['metadata_locale'] = 'original';
                    $application['metadata_fallback'] = 1;
                    $application['metadata_fallback_reason'] = $reason;
                }
                unset($application);
            }
        }
        unset($group);
        return $groups;
    }

    /** @param list<string> $ids
     *  @return array<string,array<string,mixed>>
     */
    private function appTranslations(array $ids, string $locale): array
    {
        return $this->translations(
            'sp_app_translation',
            'sp_id',
            array_values(array_unique(array_filter($ids))),
            $locale
        );
    }

    /** @param list<string> $ids
     *  @return array<string,array<string,mixed>>
     */
    private function groupTranslations(array $ids, string $locale): array
    {
        return $this->translations(
            'sp_group_translation',
            'sp_group_id',
            array_values(array_unique(array_filter($ids, static fn(string $id): bool => $id !== ''))),
            $locale
        );
    }

    /** @param list<string> $ids
     *  @return array<string,array<string,mixed>>
     */
    private function translations(string $table, string $idColumn, array $ids, string $locale): array
    {
        if ($ids === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $statement = $this->pdo->prepare(
            "SELECT * FROM {$table} WHERE locale=? AND {$idColumn} IN ({$placeholders})"
        );
        $statement->execute(array_merge([$locale], $ids));
        $result = [];
        foreach ($statement->fetchAll() as $row) {
            $result[(string) $row[$idColumn]] = $row;
        }
        return $result;
    }

    /** @return array<string,mixed>|false */
    private function readForUpdate(
        string $table,
        string $idColumn,
        string $entityId,
        string $locale
    ): array|false {
        $statement = $this->pdo->prepare(
            "SELECT * FROM {$table}
             WHERE {$idColumn}=:entity_id AND locale=:locale LIMIT 1 FOR UPDATE"
        );
        $statement->execute([':entity_id' => $entityId, ':locale' => $locale]);
        return $statement->fetch();
    }

    /** @return array{0:string,1:string} */
    private function tableFor(string $entityType): array
    {
        return $entityType === 'application'
            ? ['sp_app_translation', 'sp_id']
            : ['sp_group_translation', 'sp_group_id'];
    }

    private function assertEntity(string $entityType, string $entityId, string $locale): void
    {
        if (!in_array($entityType, ['application', 'category'], true)
            || !in_array($locale, ['ms', 'en'], true)
            || preg_match('/\A[A-Za-z0-9_-]{1,20}\z/', $entityId) !== 1
        ) {
            throw new RuntimeException('ML7_METADATA_IDENTITY_INVALID');
        }
    }

    /** @param array<string,string> $values
     *  @return array{name:string,description:string}
     */
    private function normalizedValues(string $entityType, array $values): array
    {
        $name = trim((string) ($values['name'] ?? ''));
        $description = trim((string) ($values['description'] ?? ''));
        if ($name === '' || mb_strlen($name) > ($entityType === 'application' ? 255 : 100)
            || ($entityType === 'application' && mb_strlen($description) > 2000)
        ) {
            throw new RuntimeException('ML7_METADATA_CONTENT_INVALID');
        }
        return ['name' => $name, 'description' => $description];
    }
}
