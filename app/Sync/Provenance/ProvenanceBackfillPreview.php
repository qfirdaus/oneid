<?php

declare(strict_types=1);

namespace OneId\App\Sync\Provenance;

final class ProvenanceBackfillPreview
{
    /**
     * Return raw candidate mappings only after an exact approved safe preview
     * is reproduced. Callers must never serialize this result to logs/UI.
     *
     * @param list<array<string,mixed>> $externalRows
     * @param list<array<string,mixed>> $users
     * @param list<array<string,mixed>> $memberships
     * @return list<array{u_id:string,external_user_id:string}>
     */
    public function candidatesForApprovedBackfill(
        string $sourceCode,
        array $externalRows,
        array $users,
        array $memberships,
        int $expectedCandidates,
        string $expectedDigest
    ): array {
        $safe = $this->preview($sourceCode, $externalRows, $users, $memberships);
        if ((int) $safe['blocking_findings'] !== 0
            || (int) $safe['candidate_memberships'] !== $expectedCandidates
            || !hash_equals($expectedDigest, (string) $safe['plan_digest'])
        ) {
            throw new \RuntimeException('PROVENANCE_BACKFILL_APPROVAL_MISMATCH');
        }

        $normalize = static function (mixed $value): string {
            $trimmed = trim((string) $value);
            $compacted = preg_replace('/[\s\p{Pd}]+/u', '', $trimmed);

            return $compacted ?? $trimmed;
        };
        $validPairs = [];
        foreach ($externalRows as $row) {
            $category = trim((string) ($row['ext_data_source_category'] ?? ''));
            if (!in_array($category, [
                'Pelajar',
                'PelajarPelajar',
                'PentadbiranPelajar',
                'AkademikPelajar',
            ], true)) {
                continue;
            }
            $matric = $normalize($row['data4'] ?? '');
            $ic = $normalize($row['data2'] ?? '');
            if ($matric !== '' && $ic !== '') {
                $validPairs[$matric . '|' . $ic] = [
                    'matric' => $matric,
                    'ic' => $ic,
                ];
            }
        }

        $usersByPair = [];
        foreach ($users as $user) {
            if (($user['account_source'] ?? '') === 'manual'
                && (int) ($user['sync_protected'] ?? 0) === 1
            ) {
                continue;
            }
            $matric = $normalize($user['u_id'] ?? '');
            $ic = $normalize($user['data2'] ?? '');
            if ($matric !== '' && $ic !== '') {
                $usersByPair[$matric . '|' . $ic][] = $matric;
            }
        }

        $candidates = [];
        foreach ($validPairs as $pair => $identity) {
            $matches = $usersByPair[$pair] ?? [];
            if (count($matches) !== 1) {
                continue;
            }
            $candidates[] = [
                'u_id' => $matches[0],
                'external_user_id' => $identity['matric'],
            ];
        }
        usort(
            $candidates,
            static fn(array $left, array $right): int =>
                strcmp($left['u_id'], $right['u_id'])
        );
        if (count($candidates) !== $expectedCandidates) {
            throw new \RuntimeException('PROVENANCE_BACKFILL_CANDIDATE_MISMATCH');
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
        string $proposedSourceCode,
        array $externalRows,
        array $users,
        array $memberships
    ): array {
        if (preg_match('/\A[A-Z][A-Z0-9_]{2,49}\z/', $proposedSourceCode) !== 1) {
            throw new \InvalidArgumentException('PROVENANCE_SOURCE_CODE_INVALID');
        }

        $normalize = static function (mixed $value): string {
            $trimmed = trim((string) $value);
            $compacted = preg_replace('/[\s\p{Pd}]+/u', '', $trimmed);

            return $compacted ?? $trimmed;
        };

        $valid = [];
        $invalidRows = 0;
        $pairCounts = [];
        $pairProfiles = [];
        $pairProfileFields = [];
        $matricToIc = [];
        $icToMatric = [];
        foreach ($externalRows as $row) {
            $category = trim((string) ($row['ext_data_source_category'] ?? ''));
            if (!in_array($category, [
                'Pelajar',
                'PelajarPelajar',
                'PentadbiranPelajar',
                'AkademikPelajar',
            ], true)) {
                continue;
            }

            $matric = $normalize($row['data4'] ?? '');
            $ic = $normalize($row['data2'] ?? '');
            if ($matric === '' || $ic === '') {
                $invalidRows++;
                continue;
            }

            $pair = $matric . '|' . $ic;
            $pairCounts[$pair] = ($pairCounts[$pair] ?? 0) + 1;
            $profile = [];
            for ($field = 1; $field <= 7; $field++) {
                $profile['data' . $field] = trim((string) ($row['data' . $field] ?? ''));
                $pairProfileFields[$pair]['data' . $field][$profile['data' . $field]] = true;
            }
            $pairProfiles[$pair][hash(
                'sha256',
                json_encode($profile, JSON_UNESCAPED_SLASHES) ?: ''
            )] = true;
            $matricToIc[$matric][$ic] = true;
            $icToMatric[$ic][$matric] = true;
            $valid[$pair] = ['matric' => $matric, 'ic' => $ic];
        }

        $duplicatePairGroups = 0;
        $duplicatePairRows = 0;
        $exactDuplicatePairGroups = 0;
        $profileVariantDuplicateGroups = 0;
        $profileVariantFieldGroups = array_fill_keys([
            'data1',
            'data2',
            'data3',
            'data4',
            'data5',
            'data6',
            'data7',
        ], 0);
        foreach ($pairCounts as $count) {
            if ($count > 1) {
                $duplicatePairGroups++;
                $duplicatePairRows += $count;
            }
        }
        foreach ($pairProfiles as $pair => $profiles) {
            if (($pairCounts[$pair] ?? 0) <= 1) {
                continue;
            }
            if (count($profiles) === 1) {
                $exactDuplicatePairGroups++;
            } else {
                $profileVariantDuplicateGroups++;
                foreach ($profileVariantFieldGroups as $field => $_count) {
                    if (count($pairProfileFields[$pair][$field] ?? []) > 1) {
                        $profileVariantFieldGroups[$field]++;
                    }
                }
            }
        }
        $matricConflictGroups = count(array_filter(
            $matricToIc,
            static fn(array $values): bool => count($values) > 1
        ));
        $icConflictGroups = count(array_filter(
            $icToMatric,
            static fn(array $values): bool => count($values) > 1
        ));

        $usersByPair = [];
        $protectedPairs = [];
        foreach ($users as $user) {
            $matric = $normalize($user['u_id'] ?? '');
            $ic = $normalize($user['data2'] ?? '');
            if ($matric === '' || $ic === '') {
                continue;
            }
            $pair = $matric . '|' . $ic;
            if (($user['account_source'] ?? '') === 'manual'
                && (int) ($user['sync_protected'] ?? 0) === 1
            ) {
                $protectedPairs[$pair] = true;
                continue;
            }
            $usersByPair[$pair][] = [
                'u_id' => (string) ($user['u_id'] ?? ''),
                'active' => (int) ($user['avail_status'] ?? 0) === 1,
            ];
        }

        $membershipByUser = [];
        $membershipByExternal = [];
        foreach ($memberships as $membership) {
            if (($membership['source_code'] ?? '') !== $proposedSourceCode) {
                continue;
            }
            $uId = $normalize($membership['u_id'] ?? '');
            $externalId = $normalize($membership['external_user_id'] ?? '');
            if ($uId !== '') {
                $membershipByUser[$uId] = $externalId;
            }
            if ($externalId !== '') {
                $membershipByExternal[$externalId] = $uId;
            }
        }

        $matchedActive = 0;
        $matchedInactive = 0;
        $protectedCollisions = 0;
        $unmatchedExternal = 0;
        $ambiguousUserMatches = 0;
        $existingMemberships = 0;
        $candidateMemberships = 0;
        $membershipConflicts = 0;
        $safePlanPairs = [];

        foreach ($valid as $pair => $identity) {
            if (isset($protectedPairs[$pair])) {
                $protectedCollisions++;
                continue;
            }
            $matchedUsers = $usersByPair[$pair] ?? [];
            if ($matchedUsers === []) {
                $unmatchedExternal++;
                continue;
            }
            if (count($matchedUsers) !== 1) {
                $ambiguousUserMatches++;
                continue;
            }

            $user = $matchedUsers[0];
            if ($user['active']) {
                $matchedActive++;
            } else {
                $matchedInactive++;
            }

            $uId = $normalize($user['u_id']);
            $externalId = $identity['matric'];
            $existingExternal = $membershipByUser[$uId] ?? null;
            $existingUser = $membershipByExternal[$externalId] ?? null;
            if ($existingExternal === $externalId && $existingUser === $uId) {
                $existingMemberships++;
                continue;
            }
            if (($existingExternal !== null && $existingExternal !== $externalId)
                || ($existingUser !== null && $existingUser !== $uId)
            ) {
                $membershipConflicts++;
                continue;
            }

            $candidateMemberships++;
            $safePlanPairs[] = $pair;
        }

        sort($safePlanPairs, SORT_STRING);
        $blockingFindings = $invalidRows
            + $matricConflictGroups
            + $icConflictGroups
            + $protectedCollisions
            + $ambiguousUserMatches
            + $membershipConflicts;
        $reviewFindings = $duplicatePairGroups;

        return [
            'mode' => 'preview',
            'can_apply' => false,
            'proposed_source_code' => $proposedSourceCode,
            'source_rows' => count($externalRows),
            'valid_student_identities' => count($valid),
            'invalid_identity_rows' => $invalidRows,
            'duplicate_pair_groups' => $duplicatePairGroups,
            'duplicate_pair_rows' => $duplicatePairRows,
            'exact_duplicate_pair_groups' => $exactDuplicatePairGroups,
            'profile_variant_duplicate_groups' => $profileVariantDuplicateGroups,
            'profile_variant_field_groups' => $profileVariantFieldGroups,
            'matric_conflict_groups' => $matricConflictGroups,
            'ic_conflict_groups' => $icConflictGroups,
            'matched_active_users' => $matchedActive,
            'matched_inactive_users' => $matchedInactive,
            'protected_collisions' => $protectedCollisions,
            'unmatched_external' => $unmatchedExternal,
            'ambiguous_user_matches' => $ambiguousUserMatches,
            'existing_memberships' => $existingMemberships,
            'candidate_memberships' => $candidateMemberships,
            'membership_conflicts' => $membershipConflicts,
            'blocking_findings' => $blockingFindings,
            'review_findings' => $reviewFindings,
            'status' => $blockingFindings === 0 ? 'review' : 'blocked',
            'plan_digest' => hash('sha256', implode("\n", $safePlanPairs)),
        ];
    }
}
