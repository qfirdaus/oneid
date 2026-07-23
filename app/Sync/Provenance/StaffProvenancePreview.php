<?php
declare(strict_types=1);

namespace OneId\App\Sync\Provenance;

final class StaffProvenancePreview
{
    /**
     * @param list<array<string,mixed>> $externalRows
     * @param list<array<string,mixed>> $users
     * @param list<array<string,mixed>> $memberships
     * @return list<array{u_id:string,external_user_id:string,source_hash:string}>
     */
    public function candidatesForApprovedBackfill(
        array $externalRows,
        array $users,
        array $memberships,
        int $expected,
        string $digest
    ): array {
        $preview = $this->preview($externalRows, $users, $memberships);
        if ($preview['blocking_findings'] !== 0
            || $preview['candidate_memberships'] !== $expected
            || !hash_equals($digest, (string) $preview['plan_digest'])
        ) {
            throw new \RuntimeException('STAFF_PROVENANCE_APPROVAL_MISMATCH');
        }
        $normalize = static fn(mixed $value): string =>
            preg_replace('/[\s-]+/', '', trim((string) $value))
            ?? trim((string) $value);
        $external = [];
        foreach ($externalRows as $row) {
            $identity = $normalize($row['data4'] ?? '');
            if ($identity !== '') {
                $external[$identity] = hash(
                    'sha256',
                    json_encode($row, JSON_UNESCAPED_SLASHES) ?: ''
                );
            }
        }
        $candidates = [];
        foreach ($users as $user) {
            if (($user['account_source'] ?? '') === 'manual'
                && (int) ($user['sync_protected'] ?? 0) === 1
            ) {
                continue;
            }
            if (!in_array(
                (int) ($user['u_category'] ?? 0),
                [2, 3, 11, 12],
                true
            )) {
                continue;
            }
            $identity = $normalize($user['data4'] ?? '');
            if (isset($external[$identity])) {
                $candidates[] = [
                    'u_id' => (string) $user['u_id'],
                    'external_user_id' => $identity,
                    'source_hash' => $external[$identity],
                ];
            }
        }
        usort($candidates, static fn(array $a,array $b): int =>
            strcmp($a['u_id'], $b['u_id']));
        if (count($candidates) !== $expected) {
            throw new \RuntimeException('STAFF_PROVENANCE_CANDIDATE_MISMATCH');
        }
        return $candidates;
    }

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
        $missingUserMembership = $missingExternalMembership = 0;
        $externalMismatch = $userMismatch = 0;
        $safePairs = [];
        foreach (array_keys($external) as $identityKey) {
            $identity = (string) $identityKey;
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
            $rawUserId = $matches[0];
            $userId = $rawUserId;
            $memberExternal = $byUser[$userId] ?? null;
            $memberUser = $byExternal[$identity] ?? null;
            if ($memberExternal === $identity && $memberUser === $userId) {
                $existing++;
            } elseif (($memberExternal !== null && $memberExternal !== $identity)
                || ($memberUser !== null && $memberUser !== $userId)
            ) {
                $conflicts++;
                if ($memberExternal === null) $missingUserMembership++;
                if ($memberUser === null) $missingExternalMembership++;
                if ($memberExternal !== null && $memberExternal !== $identity) {
                    $externalMismatch++;
                }
                if ($memberUser !== null && $memberUser !== $userId) {
                    $userMismatch++;
                }
            } else {
                $candidates++;
                $safePairs[] = hash('sha256', $rawUserId . '|' . $identity);
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
            'membership_conflict_reasons' => [
                'missing_by_user' => $missingUserMembership,
                'missing_by_external' => $missingExternalMembership,
                'external_mismatch' => $externalMismatch,
                'user_mismatch' => $userMismatch,
            ],
            'blocking_findings' => $blocking,
            'status' => $blocking === 0 ? 'review' : 'blocked',
            'plan_digest' => hash('sha256', implode("\n", $safePairs)),
        ];
    }
}
