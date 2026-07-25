<?php
declare(strict_types=1);

namespace OneId\App\Documentation;

use RuntimeException;

final class ApprovedReleaseCatalogue
{
    /** @var array<string,mixed> */
    private array $draft;
    /** @var array<string,mixed> */
    private array $approval;

    public function __construct(private readonly string $root)
    {
        $draftPath = $root . '/storage/generated/ml8c_release_english_draft.json';
        $approvalPath = $root . '/config/content/ml8c_release_approval.php';
        if (!is_file($draftPath) || !is_file($approvalPath)) {
            throw new RuntimeException('ML8C_RELEASE_CATALOGUE_UNAVAILABLE');
        }
        $this->draft = json_decode(
            (string) file_get_contents($draftPath),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        $approval = require $approvalPath;
        if (!is_array($approval)) {
            throw new RuntimeException('ML8C_RELEASE_APPROVAL_INVALID');
        }
        $this->approval = $approval;
        $this->assertApprovalBinding();
    }

    /**
     * @return array{
     *   requested_locale:string,
     *   effective_locale:string,
     *   fallback_used:bool,
     *   notice:?string,
     *   releases:list<array{identity:string,version:string,date:string,changes:list<string>}>
     * }
     */
    public function forLocale(string $locale): array
    {
        $requested = $locale === 'en' ? 'en' : 'ms';
        $releases = [];
        foreach ($this->draft['releases'] as $release) {
            $changes = [];
            foreach ($release['items'] as $item) {
                $changes[] = $requested === 'en' ? $item['en_draft'] : $item['bm'];
            }
            $releases[] = [
                'identity' => $release['identity'],
                'version' => $release['version'],
                'date' => $release['date'],
                'changes' => $changes,
            ];
        }
        return [
            'requested_locale' => $requested,
            'effective_locale' => $requested,
            'fallback_used' => false,
            'notice' => null,
            'releases' => $releases,
        ];
    }

    /** @return array<string,mixed> */
    public function preview(): array
    {
        $bm = $this->forLocale('ms');
        $en = $this->forLocale('en');
        return [
            'status' => 1,
            'code' => 'ML8C_LOCAL_RELEASE_ACTIVATION_READY',
            'mode' => 'ml8c_controlled_local_release_activation',
            'activation_authorized' => true,
            'live_wiring' => true,
            'can_publish_english_manual' => false,
            'automatic_approval' => false,
            'release_count' => count($bm['releases']),
            'bm_item_count' => $this->itemCount($bm['releases']),
            'en_item_count' => $this->itemCount($en['releases']),
            'approved_manifest_digest' => $this->approval['approved_manifest_digest'],
            'evidence_reference' => $this->approval['evidence_reference'],
            'blocking_codes' => [],
            'mutation_statements' => 0,
        ];
    }

    private function assertApprovalBinding(): void
    {
        $payload = $this->draft;
        $storedDigest = (string) ($payload['manifest_digest'] ?? '');
        unset($payload['manifest_digest']);
        $computedDigest = hash(
            'sha256',
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
        );
        if (
            $storedDigest === ''
            || !hash_equals($computedDigest, $storedDigest)
            || !hash_equals((string) $this->approval['approved_manifest_digest'], $storedDigest)
            || ($this->approval['content_status'] ?? null) !== 'APPROVED'
            || ($this->approval['live_activation_authorized'] ?? false) !== true
            || ($this->approval['automatic_approval'] ?? true) !== false
            || count($this->draft['releases'] ?? []) !== (int) $this->approval['expected_release_count']
            || $this->itemCountFromDraft() !== (int) $this->approval['expected_item_count']
        ) {
            throw new RuntimeException('ML8C_RELEASE_APPROVAL_BINDING_FAILED');
        }
    }

    /** @param list<array{changes:list<string>}> $releases */
    private function itemCount(array $releases): int
    {
        return array_sum(array_map(
            static fn (array $release): int => count($release['changes']),
            $releases
        ));
    }

    private function itemCountFromDraft(): int
    {
        return array_sum(array_map(
            static fn (array $release): int => count($release['items'] ?? []),
            $this->draft['releases'] ?? []
        ));
    }
}
