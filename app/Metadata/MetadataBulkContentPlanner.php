<?php
declare(strict_types=1);

namespace OneId\App\Metadata;

final class MetadataBulkContentPlanner
{
    public const APPROVED_MANIFEST_DIGEST =
        '6c4524393cd86fdab4beaa76e88feb63f24e6691b191457e044408e3446eb444';

    private const EXPECTED_CLASSIFICATIONS = [
        'EXISTING_TRANSLATION_APPROVED' => 8,
        'TRANSLATION_REQUIRED' => 33,
        'PROPER_NOUN_INVARIANT' => 3,
        'INTENTIONALLY_FALLBACK' => 32,
        'REVIEW_REQUIRED' => 8,
    ];

    private const DECISIONS = [
        'EXISTING_TRANSLATION_APPROVED' => 'ACCEPT_EXISTING',
        'TRANSLATION_REQUIRED' => 'ACCEPT_TRANSLATION',
        'PROPER_NOUN_INVARIANT' => 'ACCEPT_INVARIANT',
        'INTENTIONALLY_FALLBACK' => 'ACCEPT_INTENTIONAL_FALLBACK',
        'REVIEW_REQUIRED' => 'EXCLUDE_QUARANTINE',
    ];

    public function __construct(private readonly MetadataContentInventory $inventory)
    {
    }

    /** @return array<string,mixed> */
    public function preview(): array
    {
        $source = $this->inventory->preview();
        $manifest = $source['manifest'] ?? [];
        $blockingCodes = [];
        if (($source['manifest_digest'] ?? '') !== self::APPROVED_MANIFEST_DIGEST) {
            $blockingCodes[] = 'ML7A_APPROVED_MANIFEST_DIGEST_MISMATCH';
        }
        if (($manifest['source'] ?? null) !== ['applications' => 77, 'categories' => 7]
            || count($manifest['items'] ?? []) !== 84
        ) {
            $blockingCodes[] = 'ML7A_APPROVED_SOURCE_COUNT_MISMATCH';
        }
        $actualClassifications = $manifest['classification_counts'] ?? [];
        $expectedClassifications = self::EXPECTED_CLASSIFICATIONS;
        ksort($actualClassifications);
        ksort($expectedClassifications);
        if ($actualClassifications !== $expectedClassifications) {
            $blockingCodes[] = 'ML7A_APPROVED_DECISION_COUNT_MISMATCH';
        }
        if ((int) ($manifest['unresolved_identity_count'] ?? -1) !== 0
            || (int) ($manifest['duplicate_identity_count'] ?? -1) !== 0
        ) {
            $blockingCodes[] = 'ML7A_APPROVED_IDENTITY_INTEGRITY_FAILED';
        }

        $actions = [];
        foreach (($manifest['items'] ?? []) as $item) {
            $classification = (string) ($item['classification'] ?? '');
            if (!isset(self::DECISIONS[$classification])) {
                $blockingCodes[] = 'ML7A_APPROVED_CLASSIFICATION_UNKNOWN';
                continue;
            }
            $decision = self::DECISIONS[$classification];
            $action = [
                'entity_type' => (string) $item['entity_type'],
                'entity_id' => (string) $item['entity_id'],
                'locale' => 'en',
                'classification' => $classification,
                'review_decision' => $decision,
                'source_digest' => (string) $item['source_digest'],
                'translation_action' => $decision === 'ACCEPT_TRANSLATION'
                    ? 'INSERT_TRANSLATION'
                    : 'NO_TRANSLATION_MUTATION',
            ];
            if ($decision === 'ACCEPT_TRANSLATION') {
                $action['translated_name'] = (string) $item['draft_en_name'];
                $action['translated_description'] = (string) $item['draft_en_description'];
                if (trim($action['translated_name']) === '') {
                    $blockingCodes[] = 'ML7A_APPROVED_TRANSLATION_EMPTY';
                }
            }
            $actions[] = $action;
        }
        $blockingCodes = array_values(array_unique($blockingCodes));
        $decisionCounts = array_count_values(array_column($actions, 'review_decision'));
        ksort($decisionCounts);
        $translationCounts = array_count_values(array_column($actions, 'translation_action'));
        ksort($translationCounts);
        $plan = [
            'approved_manifest_digest' => self::APPROVED_MANIFEST_DIGEST,
            'evidence_reference' => 'ONEID-ML7A-REVISED-LOCAL-20260725-01',
            'source' => $manifest['source'] ?? [],
            'decision_counts' => $decisionCounts,
            'translation_action_counts' => $translationCounts,
            'actions' => $actions,
        ];

        return [
            'status' => $blockingCodes === [] ? 1 : 0,
            'code' => $blockingCodes === []
                ? 'ML7A_BULK_PREVIEW_READY'
                : 'ML7A_BULK_PREVIEW_BLOCKED',
            'mode' => 'ml7a_bulk_implementation_preview',
            'can_apply' => false,
            'live_apply_authorized' => false,
            'approved_manifest_match' => ($source['manifest_digest'] ?? '') === self::APPROVED_MANIFEST_DIGEST,
            'blocking_codes' => $blockingCodes,
            'plan' => $plan,
            'plan_hash' => hash('sha256', json_encode($plan, JSON_THROW_ON_ERROR)),
            'proposed_mutations' => [
                'review_decision_inserts' => count($actions),
                'translation_inserts' => (int) ($translationCounts['INSERT_TRANSLATION'] ?? 0),
                'translation_history_inserts' => (int) ($translationCounts['INSERT_TRANSLATION'] ?? 0),
                'original_metadata_updates' => 0,
                'quarantine_translation_inserts' => 0,
            ],
            'mutation_statements' => 0,
        ];
    }
}
