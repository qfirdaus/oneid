<?php
declare(strict_types=1);

namespace OneId\App\Sync\Provenance;

final class StaffProvenancePreview
{
    /**
     * @param list<array<string,mixed>> $externalRows
     * @param list<array<string,mixed>> $users
     * @param list<array<string,mixed>> $memberships
     * @return array<string,mixed>
     */
    public function preview(
        array $externalRows,
        array $users,
        array $memberships
    ): array {
        $normalize = static fn(mixed $value): string =>
            preg_replace('/[\s-]+/', '', trim((string) $value))
            ?? trim((string) $value);
        $external = [];
        $invalid = 0;
        $duplicates = 0;
        foreach ($externalRows as $row) {
            if (!in_array(trim((string) ($row['ext_data_source_category'] ?? '')), [
                'Akademik', 'Pentadbiran',
                'AkademikPelajar', 'PentadbiranPelajar',
            ], true)) {
                continue;
            }
            $identity = $normalize($row['data4'] ?? '');
            if ($identity === '') {
                $invalid++;
                continue;
            }
            if (isset($external[$identity])) {
                $duplicates++;
            }
            $external[$identity] = true;
        }
        $usersByIdentity = [];
        $protected = [];
        foreach ($users as $user) {
            $identity = $normalize($user['data4'] ?? '');
            if ($identity === '') {
                continue;
            }
            if (($user['account_source'] ?? '') === 'manual'
                && (int) ($user['sync_protected'] ?? 0) === 1
            ) {
                $protected[$identity] = true;
                continue;
            }
            if (in_array((int) ($user['u_category'] ?? 0), [2, 3, 11, 12], true)) {
                $usersByIdentity[$identity][] = (string) ($user['u_id'] ?? '');
            }
        }
        $byUser = [];
        $byExternal = [];
        foreach ($memberships as $membership) {
            if (($membership['source_code'] ?? '') !== 'STAFF_HR') {
                continue;
            }
            $byUser[(string) ($membership['u_id'] ?? '')] =
                $normalize($membership['external_user_id'] ?? '');
            $byExternal[$normalize($membership['external_user_id'] ?? '')] =
                (string) ($membership['u_id'] ?? '');
        }
        $matched = $unmatched = $ambiguous = $protectedCollisions = 0;
        $existing = $candidates = $conflicts = 0;
        $safePairs = [];
        foreach (array_keys($external) as $identity) {
            if (isset($protected[$identity])) {
                $protectedCollisions++;
                continue;
            }
            $matches = $usersByIdentity[$identity] ?? [];
            if ($matches === []) {
                $unmatched++;
                continue;
            }
            if (count($matches) !== 1) {
                $ambiguous++;
                continue;
            }
            $matched++;
            $userId = $matches[0];
            $memberExternal = $byUser[$userId] ?? null;
            $memberUser = $byExternal[$identity] ?? null;
            if ($memberExternal === $identity && $memberUser === $userId) {
                $existing++;
            } elseif (($memberExternal !== null && $memberExternal !== $identity)
                || ($memberUser !== null && $memberUser !== $userId)
            ) {
                $conflicts++;
            } else {
                $candidates++;
                $safePairs[] = hash('sha256', $userId . '|' . $identity);
            }
        }
        sort($safePairs, SORT_STRING);
        $blocking = $invalid + $duplicates + $ambiguous
            + $protectedCollisions + $conflicts;
        return [
            'mode' => 'staff_provenance_preview',
            'can_apply' => false,
            'mutation_statements' => 0,
            'proposed_source_code' => 'STAFF_HR',
            'source_rows' => count($externalRows),
            'valid_source_identities' => count($external),
            'matched_users' => $matched,
            'unmatched_external' => $unmatched,
            'invalid_identity_rows' => $invalid,
            'duplicate_identity_rows' => $duplicates,
            'ambiguous_matches' => $ambiguous,
            'protected_collisions' => $protectedCollisions,
            'existing_memberships' => $existing,
            'candidate_memberships' => $candidates,
            'membership_conflicts' => $conflicts,
            'blocking_findings' => $blocking,
            'status' => $blocking === 0 ? 'review' : 'blocked',
            'plan_digest' => hash('sha256', implode("\n", $safePairs)),
        ];
    }
}
