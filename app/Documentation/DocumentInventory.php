<?php
declare(strict_types=1);

namespace OneId\App\Documentation;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

final class DocumentInventory
{
    public function __construct(private readonly string $root)
    {
    }

    /** @return array<string,mixed> */
    public function preview(): array
    {
        $items = [];
        $this->addPublicDocuments($items);
        $this->addFaqSurfaces($items);
        $this->addReleaseUi($items);
        $this->addRepositoryDocuments($items);
        usort($items, static fn(array $a, array $b): int => strcmp(
            $a['identity'],
            $b['identity']
        ));

        $identityCount = count(array_unique(array_column($items, 'identity')));
        $classificationCounts = [];
        $surfaceCounts = [];
        $localeCounts = [];
        foreach ($items as $item) {
            $classificationCounts[$item['classification']] =
                ($classificationCounts[$item['classification']] ?? 0) + 1;
            $surfaceCounts[$item['surface']] = ($surfaceCounts[$item['surface']] ?? 0) + 1;
            $localeCounts[$item['detected_locale']] =
                ($localeCounts[$item['detected_locale']] ?? 0) + 1;
        }
        ksort($classificationCounts);
        ksort($surfaceCounts);
        ksort($localeCounts);
        $manifest = [
            'items' => $items,
            'total_items' => count($items),
            'classification_counts' => $classificationCounts,
            'surface_counts' => $surfaceCounts,
            'locale_counts' => $localeCounts,
            'duplicate_identity_count' => count($items) - $identityCount,
            'missing_target_count' => count(array_filter(
                $items,
                static fn(array $item): bool => $item['target_exists'] === false
            )),
            'translation_required_count' => count(array_filter(
                $items,
                static fn(array $item): bool => in_array(
                    $item['classification'],
                    [
                        'BM_ONLY_TRANSLATION_REQUIRED',
                        'BM_ONLY_EXPLICIT_FALLBACK_REQUIRED',
                        'MIXED_TRANSLATION_REQUIRED',
                        'REVIEW_REQUIRED',
                    ],
                    true
                )
            )),
        ];
        $blockingCodes = [];
        if ($manifest['duplicate_identity_count'] > 0) {
            $blockingCodes[] = 'ML8A_DUPLICATE_DOCUMENT_IDENTITY';
        }
        if ($manifest['missing_target_count'] > 0) {
            $blockingCodes[] = 'ML8A_DOCUMENT_TARGET_MISSING';
        }

        return [
            'status' => $blockingCodes === [] ? 1 : 0,
            'code' => $blockingCodes === []
                ? 'ML8A_DOCUMENT_INVENTORY_READY'
                : 'ML8A_DOCUMENT_INVENTORY_BLOCKED',
            'mode' => 'ml8a_inventory_document_contract',
            'can_apply' => false,
            'automatic_translation' => false,
            'blocking_codes' => $blockingCodes,
            'manifest' => $manifest,
            'manifest_digest' => hash(
                'sha256',
                json_encode($manifest, JSON_THROW_ON_ERROR)
            ),
            'mutation_statements' => 0,
        ];
    }

    /** @param list<array<string,mixed>> $items */
    private function addPublicDocuments(array &$items): void
    {
        $directory = $this->root . '/public/public_docs';
        if (!is_dir($directory)) {
            $items[] = $this->missing(
                'public_document:public/public_docs',
                'public_document',
                'Public document directory'
            );
            return;
        }
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
            $directory,
            RecursiveDirectoryIterator::SKIP_DOTS
        )) as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $relative = $this->relative($file->getPathname());
            $items[] = $this->fileItem(
                'public_document:' . $relative,
                'public_document',
                $relative,
                'ms',
                'BM_ONLY_EXPLICIT_FALLBACK_REQUIRED',
                'Firdaus, System Analyst',
                'EXPLICIT_BM_FALLBACK_UNTIL_EN_APPROVED'
            );
        }
    }

    /** @param list<array<string,mixed>> $items */
    private function addFaqSurfaces(array &$items): void
    {
        foreach ([
            ['index.php', 'public_login_faq', '/#faqModal'],
            ['page/dashboard.php', 'authenticated_user_faq', '/page/dashboard#modal_faq'],
        ] as [$relative, $name, $route]) {
            $path = $this->root . '/' . $relative;
            if (!is_file($path)) {
                $items[] = $this->missing('faq:' . $name, 'faq', $relative);
                continue;
            }
            $content = (string) file_get_contents($path);
            $sharedRenderer = str_contains($content, 'oneid_render_login_faq()')
                || str_contains($content, 'oneid_render_dashboard_faq()');
            $count = $sharedRenderer
                ? 8
                : preg_match_all('/<!--\\s*FAQ\\s+[0-9]+\\s*-->/i', $content);
            $items[] = [
                'identity' => 'faq:' . $name,
                'surface' => 'faq',
                'source' => $relative,
                'route' => $route,
                'title' => $name,
                'detected_locale' => 'ms',
                'classification' => 'BM_ONLY_TRANSLATION_REQUIRED',
                'content_owner' => 'Firdaus, System Analyst',
                'security_reviewer' => 'Firdaus, System Analyst/DBA',
                'fallback_policy' => 'EXPLICIT_BM_FALLBACK_UNTIL_EN_APPROVED',
                'entry_count' => (int) $count,
                'target_exists' => true,
                'source_digest' => hash('sha256', $content),
            ];
        }
    }

    /** @param list<array<string,mixed>> $items */
    private function addReleaseUi(array &$items): void
    {
        $relative = 'admin/dashboard.php';
        $path = $this->root . '/' . $relative;
        if (!is_file($path)) {
            $items[] = $this->missing('release_ui:admin_dashboard', 'release_ui', $relative);
            return;
        }
        $content = (string) file_get_contents($path);
        $cataloguePath = $this->root . '/config/content/release_changelog_plain.php';
        $catalogue = is_file($cataloguePath) ? require $cataloguePath : [];
        $releaseCount = is_array($catalogue) ? count($catalogue) : 0;
        $items[] = [
            'identity' => 'release_ui:admin_dashboard',
            'surface' => 'release_ui',
            'source' => $relative,
            'route' => '/admin/dashboard#tab_versioning',
            'title' => 'Administrator Version Releases',
            'detected_locale' => 'mixed',
            'classification' => 'MIXED_TRANSLATION_REQUIRED',
            'content_owner' => 'Firdaus, System Analyst',
            'security_reviewer' => 'Firdaus, System Analyst/DBA',
            'fallback_policy' => 'EXPLICIT_ORIGINAL_FALLBACK',
            'entry_count' => (int) $releaseCount,
            'target_exists' => $releaseCount > 0,
            'source_digest' => hash('sha256', (string) file_get_contents($cataloguePath)),
        ];
    }

    /** @param list<array<string,mixed>> $items */
    private function addRepositoryDocuments(array &$items): void
    {
        $files = glob($this->root . '/docs/*.md') ?: [];
        foreach ([
            '/README.md',
            '/app/README.md',
            '/config/README.md',
            '/deployment/README.md',
            '/resources/README.md',
            '/tests/README.md',
        ] as $relative) {
            if (is_file($this->root . $relative)) {
                $files[] = $this->root . $relative;
            }
        }
        sort($files);
        foreach (array_values(array_unique($files)) as $path) {
            $relative = $this->relative($path);
            $basename = basename($relative);
            if (in_array($basename, [
                'AUDIT_DAN_PELAN_PELAKSANAAN_MULTILANGUAGE_BM_ENGLISH.md',
                'ML8A_INVENTORY_AND_DOCUMENT_CONTRACT.md',
                'ML8B_SHARED_FAQ_MULTILANGUAGE.md',
                'ML8C_BILINGUAL_CONTENT_PREVIEW.md',
                'ML8C_RELEASE_ENGLISH_DRAFT_REVIEW.md',
                'MANUAL_SALAM_ENGLISH_DRAFT_REVIEW.md',
                'MULTILINGUAL_EXTERNAL_SYNC_LOCAL.md',
                'MULTILINGUAL_ADMIN_STEP_UP_LOCAL.md',
                'ADMINISTRATOR_MULTILINGUAL_COMPLETENESS_LOCAL.md',
                'MULTILANGUAGE_PRE_ML9_RECONCILIATION_20260726.md',
            ], true)) {
                continue;
            }
            if (str_starts_with($basename, 'RELEASE_')) {
                $classification = 'BM_ONLY_TRANSLATION_REQUIRED';
                $surface = 'release_document';
                $fallback = 'EXPLICIT_BM_FALLBACK_UNTIL_EN_APPROVED';
            } elseif (in_array($basename, [
                'VERSION_NUMBERING_POLICY.md',
                'ONEID_EMAIL_DESIGN_STANDARD.md',
            ], true)) {
                $classification = 'REVIEW_REQUIRED';
                $surface = 'policy_document';
                $fallback = 'NO_SILENT_FALLBACK';
            } else {
                $classification = 'INTERNAL_TECHNICAL_INVARIANT';
                $surface = 'internal_document';
                $fallback = 'CANONICAL_INTERNAL_DOCUMENT';
            }
            $items[] = $this->fileItem(
                'repository_document:' . $relative,
                $surface,
                $relative,
                'ms',
                $classification,
                'Firdaus, System Analyst',
                $fallback
            );
        }
    }

    /** @return array<string,mixed> */
    private function fileItem(
        string $identity,
        string $surface,
        string $relative,
        string $locale,
        string $classification,
        string $owner,
        string $fallback
    ): array {
        $path = $this->root . '/' . $relative;
        $content = (string) file_get_contents($path);
        return [
            'identity' => $identity,
            'surface' => $surface,
            'source' => $relative,
            'route' => null,
            'title' => pathinfo($relative, PATHINFO_FILENAME),
            'detected_locale' => $locale,
            'classification' => $classification,
            'content_owner' => $owner,
            'security_reviewer' => 'Firdaus, System Analyst/DBA',
            'fallback_policy' => $fallback,
            'entry_count' => 1,
            'target_exists' => true,
            'source_digest' => hash('sha256', $content),
            'size_bytes' => filesize($path),
        ];
    }

    /** @return array<string,mixed> */
    private function missing(string $identity, string $surface, string $title): array
    {
        return [
            'identity' => $identity,
            'surface' => $surface,
            'source' => $title,
            'route' => null,
            'title' => $title,
            'detected_locale' => 'unknown',
            'classification' => 'REVIEW_REQUIRED',
            'content_owner' => 'Firdaus, System Analyst',
            'security_reviewer' => 'Firdaus, System Analyst/DBA',
            'fallback_policy' => 'BLOCK',
            'entry_count' => 0,
            'target_exists' => false,
            'source_digest' => '',
        ];
    }

    private function relative(string $path): string
    {
        $root = rtrim(str_replace('\\', '/', $this->root), '/');
        $normalized = str_replace('\\', '/', $path);
        if (!str_starts_with($normalized, $root . '/')) {
            throw new RuntimeException('ML8A_DOCUMENT_OUTSIDE_ROOT');
        }
        return substr($normalized, strlen($root) + 1);
    }
}
