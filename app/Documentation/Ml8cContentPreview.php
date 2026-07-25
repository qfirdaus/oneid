<?php
declare(strict_types=1);

namespace OneId\App\Documentation;

use RuntimeException;

final class Ml8cContentPreview
{
    public const MANUAL_FALLBACK_NOTICE =
        'English manual is not yet available. The Bahasa Melayu version is provided.';

    public function __construct(private readonly string $root)
    {
    }

    /** @return array<string,mixed> */
    public function preview(): array
    {
        $releases = $this->releaseInventory();
        $versions = array_column($releases, 'version');
        $duplicateVersions = count($versions) - count(array_unique($versions));
        $policies = [
            $this->documentStatus('docs/VERSION_NUMBERING_POLICY.md'),
            $this->documentStatus('docs/ONEID_EMAIL_DESIGN_STANDARD.md'),
        ];
        $manualPath = $this->root . '/public/public_docs/MANUAL_SALAM.pdf';
        $manualDraft = $this->root . '/docs/MANUAL_SALAM_ENGLISH_DRAFT_REVIEW.md';
        $blocking = [];
        if (count($releases) !== 38) {
            $blocking[] = 'ML8C_RELEASE_BASELINE_MISMATCH';
        }
        if ($duplicateVersions !== 0) {
            $blocking[] = 'ML8C_DUPLICATE_RELEASE_IDENTITY';
        }
        if (!is_file($manualPath)) {
            $blocking[] = 'ML8C_BM_MANUAL_MISSING';
        }
        foreach ($policies as $policy) {
            if (!$policy['bilingual_sections_present']) {
                $blocking[] = 'ML8C_POLICY_BILINGUAL_DRAFT_MISSING';
                break;
            }
        }

        $manifest = [
            'active_release_entries' => count($releases),
            'release_change_items' => array_sum(array_column($releases, 'change_count')),
            'release_english_approved' => 0,
            'release_english_review_required' => count($releases),
            'duplicate_release_identities' => $duplicateVersions,
            'unresolved_release_identities' => count($releases) === 38 ? 0 : abs(38 - count($releases)),
            'official_bm_manuals' => is_file($manualPath) ? 1 : 0,
            'approved_english_manuals' => 0,
            'english_manual_draft_review_file' => is_file($manualDraft),
            'policy_documents' => $policies,
            'releases' => $releases,
        ];
        $digestPayload = $manifest;
        unset($digestPayload['releases']);
        $digestPayload['release_identities'] = array_map(
            static fn (array $release): array => [
                $release['identity'],
                $release['date'],
                $release['change_count'],
                $release['bm_digest'],
            ],
            $releases
        );

        return [
            'status' => $blocking === [] ? 1 : 0,
            'code' => $blocking === [] ? 'ML8C_PREVIEW_READY' : 'ML8C_PREVIEW_BLOCKED',
            'mode' => 'ml8c_inventory_implementation_preview',
            'can_apply' => false,
            'can_publish_english_manual' => false,
            'automatic_translation_approval' => false,
            'mutation_statements' => 0,
            'manual_fallback_notice' => self::MANUAL_FALLBACK_NOTICE,
            'blocking_codes' => $blocking,
            'manifest_digest' => hash(
                'sha256',
                json_encode($digestPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
            ),
            'manifest' => $manifest,
        ];
    }

    /**
     * Locale-aware read seam. English remains an explicit BM fallback until an
     * owner-approved English release catalogue is supplied in a later gate.
     *
     * @return array{locale:string,fallback_used:bool,notice:?string,releases:list<array<string,mixed>>}
     */
    public function releasesForLocale(string $locale): array
    {
        $englishRequested = $locale === 'en';
        return [
            'locale' => $englishRequested ? 'ms' : 'ms',
            'fallback_used' => $englishRequested,
            'notice' => $englishRequested
                ? 'English release content is under owner review. The Bahasa Melayu release content is provided.'
                : null,
            'releases' => $this->releaseInventory(),
        ];
    }

    /** @return list<array{identity:string,version:string,date:string,changes:list<string>}> */
    public function canonicalReleaseContent(): array
    {
        $path = $this->root . '/admin/dashboard.php';
        $source = is_file($path) ? (string) file_get_contents($path) : '';
        $start = strpos($source, 'const canonicalReleaseNotes = [');
        $end = $start === false ? false : strpos($source, "\n  ];", $start);
        if ($start === false || $end === false) {
            throw new RuntimeException('ML8C_RELEASE_SOURCE_NOT_FOUND');
        }
        $block = substr($source, $start, $end - $start);
        preg_match_all(
            '/\{\s*version:\s*(?:"([^"]+)"|<\?php\s+echo\s+json_encode\(ONEID_APP_VERSION\);\s+\?>)\s*,'
            . '\s*date:\s*"([^"]+)"\s*,(?:\s*\/\/[^\n]*)?\s*changes:\s*\[(.*?)\]\s*\}/s',
            $block,
            $matches,
            PREG_SET_ORDER
        );
        $releases = [];
        foreach ($matches as $match) {
            $version = $match[1] !== '' ? $match[1] : $this->configuredVersion();
            preg_match_all('/"((?:\\\\.|[^"\\\\])*)"/s', $match[3], $changeMatches);
            $changes = [];
            foreach ($changeMatches[1] as $encoded) {
                $decoded = json_decode('"' . $encoded . '"', true);
                if (is_string($decoded)) {
                    $changes[] = $decoded;
                }
            }
            $releases[] = [
                'identity' => 'release:' . $version,
                'version' => $version,
                'date' => $match[2],
                'changes' => $changes,
            ];
        }
        return $releases;
    }

    /** @return list<array{identity:string,version:string,date:string,change_count:int,bm_digest:string,english_status:string}> */
    private function releaseInventory(): array
    {
        $releases = [];
        foreach ($this->canonicalReleaseContent() as $release) {
            $releases[] = [
                'identity' => $release['identity'],
                'version' => $release['version'],
                'date' => $release['date'],
                'change_count' => count($release['changes']),
                'bm_digest' => hash(
                    'sha256',
                    json_encode($release['changes'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
                ),
                'english_status' => 'REVIEW_REQUIRED',
            ];
        }
        return $releases;
    }

    private function configuredVersion(): string
    {
        $source = (string) file_get_contents($this->root . '/config/application.php');
        if (preg_match("/ONEID_APP_VERSION'\\s*,\\s*'([^']+)'/", $source, $match) !== 1) {
            return 'CURRENT';
        }
        return $match[1];
    }

    /** @return array{source:string,bilingual_sections_present:bool,digest:string} */
    private function documentStatus(string $relative): array
    {
        $path = $this->root . '/' . $relative;
        $content = is_file($path) ? (string) file_get_contents($path) : '';
        return [
            'source' => $relative,
            'bilingual_sections_present' => str_contains($content, '## English')
                && str_contains($content, '## Bahasa Melayu'),
            'digest' => hash('sha256', $content),
        ];
    }
}
