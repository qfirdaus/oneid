<?php
declare(strict_types=1);

namespace OneId\App\Metadata;

use DateTimeImmutable;
use PDO;
use RuntimeException;
use Throwable;

final class MetadataBulkContentApplyService
{
    public const APPROVED_PLAN_HASH =
        '3ade2d6bf970c2f87c9f6889cf5584c6d06c7ab66da62c5956681941d8c8c664';
    public const CHANGE_REFERENCE = 'ONEID-ML7A-BULK-LOCAL-20260725-02';
    public const BACKUP_REFERENCE = 'ONEID-LOCAL-BACKUP-20260725-05';
    public const WINDOW_START = '2026-07-25T21:35:00+08:00';
    public const WINDOW_END = '2026-07-25T22:05:00+08:00';

    public function __construct(
        private readonly PDO $pdo,
        private readonly MetadataBulkContentPlanner $planner
    ) {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    /** @return array<string,mixed> */
    public function apply(string $actor, ?DateTimeImmutable $now = null): array
    {
        $now ??= new DateTimeImmutable('now');
        $this->assertWindow($now);
        if (!preg_match('/\A[0-9A-Za-z._-]{3,20}\z/', $actor)) {
            throw new RuntimeException('ML7A_BULK_ACTOR_INVALID');
        }
        $before = $this->baseline();
        $this->assertBaseline($before);
        $started = false;
        try {
            $this->pdo->beginTransaction();
            $started = true;
            $this->lockSources();
            $preview = $this->planner->preview();
            $this->assertPlan($preview);

            $reviewInsert = $this->pdo->prepare(
                'INSERT INTO metadata_content_review(
                    entity_type,entity_id,locale,classification,review_decision,
                    source_digest,manifest_digest,reviewed_by,evidence_reference
                 ) VALUES(
                    :entity_type,:entity_id,:locale,:classification,:review_decision,
                    :source_digest,:manifest_digest,:actor,:evidence
                 )'
            );
            $appInsert = $this->pdo->prepare(
                'INSERT INTO sp_app_translation(
                    sp_id,locale,sp_name,sp_description,translation_version,created_by,updated_by
                 ) VALUES(:entity_id,:locale,:name,:description,1,:actor,:actor)'
            );
            $categoryInsert = $this->pdo->prepare(
                'INSERT INTO sp_group_translation(
                    sp_group_id,locale,sp_group_name,translation_version,created_by,updated_by
                 ) VALUES(:entity_id,:locale,:name,1,:actor,:actor)'
            );
            $historyInsert = $this->pdo->prepare(
                'INSERT INTO metadata_translation_history(
                    entity_type,entity_id,locale,version_before,version_after,actor_id,
                    change_reason,before_json,after_json,correlation_id
                 ) VALUES(
                    :entity_type,:entity_id,:locale,NULL,1,:actor,
                    :reason,NULL,:after_json,:correlation
                 )'
            );

            $reviewRows = 0;
            $translationRows = 0;
            $historyRows = 0;
            foreach ($preview['plan']['actions'] as $action) {
                $reviewInsert->execute([
                    ':entity_type' => $action['entity_type'],
                    ':entity_id' => $action['entity_id'],
                    ':locale' => $action['locale'],
                    ':classification' => $action['classification'],
                    ':review_decision' => $action['review_decision'],
                    ':source_digest' => $action['source_digest'],
                    ':manifest_digest' => MetadataBulkContentPlanner::APPROVED_MANIFEST_DIGEST,
                    ':actor' => $actor,
                    ':evidence' => 'ONEID-ML7A-REVISED-LOCAL-20260725-01',
                ]);
                $reviewRows += $reviewInsert->rowCount();
                if ($action['translation_action'] !== 'INSERT_TRANSLATION') {
                    continue;
                }
                $translationParameters = [
                    ':entity_id' => $action['entity_id'],
                    ':locale' => 'en',
                    ':name' => $action['translated_name'],
                    ':actor' => $actor,
                ];
                if ($action['entity_type'] === 'application') {
                    $translationParameters[':description'] = $action['translated_description'];
                    $appInsert->execute($translationParameters);
                    $after = [
                        'name' => $action['translated_name'],
                        'description' => $action['translated_description'],
                    ];
                } else {
                    $categoryInsert->execute($translationParameters);
                    $after = ['name' => $action['translated_name'], 'description' => ''];
                }
                $translationRows++;
                $historyInsert->execute([
                    ':entity_type' => $action['entity_type'],
                    ':entity_id' => $action['entity_id'],
                    ':locale' => 'en',
                    ':actor' => $actor,
                    ':reason' => 'Approved ML7A bulk metadata translation',
                    ':after_json' => json_encode($after, JSON_THROW_ON_ERROR),
                    ':correlation' => bin2hex(random_bytes(8)),
                ]);
                $historyRows += $historyInsert->rowCount();
            }
            if ($reviewRows !== 84 || $translationRows !== 33 || $historyRows !== 33) {
                throw new RuntimeException('ML7A_BULK_MUTATION_COUNT_MISMATCH');
            }
            $after = $this->baseline();
            if ($after['review_rows'] !== 84
                || $after['english_translations'] !== 41
                || $after['history_rows'] !== $before['history_rows'] + 33
                || $after['sp_list_checksum'] !== $before['sp_list_checksum']
                || $after['sp_group_checksum'] !== $before['sp_group_checksum']
            ) {
                throw new RuntimeException('ML7A_BULK_RECONCILIATION_FAILED');
            }
            $this->pdo->commit();
            $started = false;
            return [
                'status' => 1,
                'code' => 'ML7A_BULK_APPLY_COMMITTED',
                'change_reference' => self::CHANGE_REFERENCE,
                'backup_reference' => self::BACKUP_REFERENCE,
                'plan_hash' => self::APPROVED_PLAN_HASH,
                'review_decision_inserts' => $reviewRows,
                'translation_inserts' => $translationRows,
                'translation_history_inserts' => $historyRows,
                'original_metadata_updates' => 0,
                'quarantine_translation_inserts' => 0,
                'before' => $before,
                'after' => $after,
            ];
        } catch (Throwable $exception) {
            if ($started && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** @param array<string,mixed> $preview */
    private function assertPlan(array $preview): void
    {
        if (($preview['status'] ?? 0) !== 1
            || ($preview['plan_hash'] ?? '') !== self::APPROVED_PLAN_HASH
            || ($preview['blocking_codes'] ?? null) !== []
            || ($preview['proposed_mutations']['review_decision_inserts'] ?? 0) !== 84
            || ($preview['proposed_mutations']['translation_inserts'] ?? 0) !== 33
            || ($preview['proposed_mutations']['translation_history_inserts'] ?? 0) !== 33
        ) {
            throw new RuntimeException('ML7A_BULK_EXACT_PLAN_REJECTED');
        }
    }

    /** @param array<string,mixed> $baseline */
    private function assertBaseline(array $baseline): void
    {
        if ($baseline['applications'] !== 77
            || $baseline['categories'] !== 7
            || $baseline['english_translations'] !== 8
            || $baseline['review_rows'] !== 0
        ) {
            throw new RuntimeException('ML7A_BULK_BASELINE_MISMATCH');
        }
    }

    private function assertWindow(DateTimeImmutable $now): void
    {
        if ($now < new DateTimeImmutable(self::WINDOW_START)
            || $now > new DateTimeImmutable(self::WINDOW_END)
        ) {
            throw new RuntimeException('ML7A_BULK_OUTSIDE_CHANGE_WINDOW');
        }
    }

    private function lockSources(): void
    {
        $this->pdo->query('SELECT sp_id FROM sp_list ORDER BY sp_id FOR UPDATE')->fetchAll();
        $this->pdo->query('SELECT sp_group_id FROM sp_group ORDER BY sp_group_id FOR UPDATE')->fetchAll();
        $this->pdo->query('SELECT translation_id FROM sp_app_translation ORDER BY translation_id FOR UPDATE')->fetchAll();
        $this->pdo->query('SELECT translation_id FROM sp_group_translation ORDER BY translation_id FOR UPDATE')->fetchAll();
        $this->pdo->query('SELECT review_id FROM metadata_content_review ORDER BY review_id FOR UPDATE')->fetchAll();
    }

    /** @return array<string,mixed> */
    private function baseline(): array
    {
        $apps = $this->pdo->query(
            'SELECT sp_id,sp_name,sp_description,sp_group_id,sp_domain,avail_status
             FROM sp_list ORDER BY sp_id'
        )->fetchAll();
        $groups = $this->pdo->query(
            'SELECT sp_group_id,sp_group_name FROM sp_group ORDER BY sp_group_id'
        )->fetchAll();
        return [
            'applications' => count($apps),
            'categories' => count($groups),
            'english_translations' => (int) $this->pdo->query(
                "SELECT
                    (SELECT COUNT(*) FROM sp_app_translation WHERE locale='en') +
                    (SELECT COUNT(*) FROM sp_group_translation WHERE locale='en')"
            )->fetchColumn(),
            'review_rows' => (int) $this->pdo->query(
                'SELECT COUNT(*) FROM metadata_content_review'
            )->fetchColumn(),
            'history_rows' => (int) $this->pdo->query(
                'SELECT COUNT(*) FROM metadata_translation_history'
            )->fetchColumn(),
            'sp_list_checksum' => hash(
                'sha256',
                json_encode($apps, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ),
            'sp_group_checksum' => hash(
                'sha256',
                json_encode($groups, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ),
        ];
    }
}
