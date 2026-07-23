<?php

declare(strict_types=1);

namespace OneId\App\Sync\Odl;

use OneId\App\Sync\Provenance\ProvenanceBackfillPreview;

final class OdlDataQualityAudit
{
    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $users
     * @param list<array<string,mixed>> $memberships
     * @return array<string,mixed>
     */
    public function audit(array $rows, array $users, array $memberships): array
    {
        $blank = array_fill_keys([
            'name', 'ic', 'matric', 'email', 'faculty', 'program',
        ], 0);
        $overlength = array_fill_keys([
            'name', 'ic', 'matric', 'email', 'faculty', 'program',
        ], 0);
        $limits = [
            'name' => 100,
            'ic' => 100,
            'matric' => 20,
            'email' => 100,
            'faculty' => 100,
            'program' => 100,
        ];
        $fields = [
            'name' => 'data1',
            'ic' => 'data2',
            'matric' => 'data4',
            'email' => 'data5',
            'faculty' => 'data6',
            'program' => 'data7',
        ];
        $invalidUtf8Rows = 0;
        $invalidEmailRows = 0;
        $invalidMatricFormatRows = 0;
        $statusCounts = [];

        foreach ($rows as $row) {
            $rowInvalidUtf8 = false;
            foreach ($fields as $label => $field) {
                $value = trim((string) ($row[$field] ?? ''));
                if ($value === '') {
                    $blank[$label]++;
                }
                if (preg_match('//u', $value) !== 1) {
                    $rowInvalidUtf8 = true;
                } elseif (mb_strlen($value, 'UTF-8') > $limits[$label]) {
                    $overlength[$label]++;
                }
            }
            $invalidUtf8Rows += $rowInvalidUtf8 ? 1 : 0;
            $email = trim((string) ($row['data5'] ?? ''));
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $invalidEmailRows++;
            }
            $matric = trim((string) ($row['data4'] ?? ''));
            if ($matric !== '' && preg_match('/\A[A-Za-z0-9]+\z/', $matric) !== 1) {
                $invalidMatricFormatRows++;
            }
            $status = trim((string) ($row['external_status_code'] ?? ''));
            $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
        }
        ksort($statusCounts, SORT_STRING);

        $identity = (new ProvenanceBackfillPreview())->preview(
            OdlStudentSource::SOURCE_CODE,
            $rows,
            $users,
            $memberships
        );

        $normalize = static function (mixed $value): string {
            $trimmed = trim((string) $value);
            return preg_replace('/[\s\p{Pd}]+/u', '', $trimmed) ?? $trimmed;
        };
        $usersByPair = [];
        foreach ($users as $user) {
            $pair = $normalize($user['u_id'] ?? '')
                . '|' . $normalize($user['data2'] ?? '');
            $usersByPair[$pair][] = (string) ($user['u_id'] ?? '');
        }
        $ugUsers = [];
        foreach ($memberships as $membership) {
            if (($membership['source_code'] ?? '') === 'STUDENT_UG') {
                $ugUsers[$normalize($membership['u_id'] ?? '')] = true;
            }
        }
        $ugOverlap = 0;
        foreach ($rows as $row) {
            $pair = $normalize($row['data4'] ?? '')
                . '|' . $normalize($row['data2'] ?? '');
            $matches = array_unique($usersByPair[$pair] ?? []);
            if (count($matches) === 1 && isset($ugUsers[$normalize($matches[0])])) {
                $ugOverlap++;
            }
        }

        $ineligibleStatusRows = count($rows) - array_sum(array_intersect_key(
            $statusCounts,
            array_flip(['2', '4', '5'])
        ));
        $qualityBlocking = (int) $identity['blocking_findings']
            + $blank['name']
            + $blank['ic']
            + $blank['matric']
            + array_sum($overlength)
            + $invalidUtf8Rows
            + $invalidEmailRows
            + $invalidMatricFormatRows
            + $ineligibleStatusRows;

        return [
            'mode' => 'read_only_audit',
            'can_apply' => false,
            'source_code' => OdlStudentSource::SOURCE_CODE,
            'source_rows' => count($rows),
            'status_counts' => $statusCounts,
            'ineligible_status_rows' => $ineligibleStatusRows,
            'blank_fields' => $blank,
            'overlength_fields' => $overlength,
            'invalid_utf8_rows' => $invalidUtf8Rows,
            'invalid_email_rows' => $invalidEmailRows,
            'invalid_matric_format_rows' => $invalidMatricFormatRows,
            'valid_student_identities' => $identity['valid_student_identities'],
            'duplicate_pair_groups' => $identity['duplicate_pair_groups'],
            'duplicate_pair_rows' => $identity['duplicate_pair_rows'],
            'matric_conflict_groups' => $identity['matric_conflict_groups'],
            'ic_conflict_groups' => $identity['ic_conflict_groups'],
            'matched_active_users' => $identity['matched_active_users'],
            'matched_inactive_users' => $identity['matched_inactive_users'],
            'unmatched_external' => $identity['unmatched_external'],
            'protected_collisions' => $identity['protected_collisions'],
            'ambiguous_user_matches' => $identity['ambiguous_user_matches'],
            'membership_conflicts' => $identity['membership_conflicts'],
            'ug_membership_overlap' => $ugOverlap,
            'blocking_findings' => $qualityBlocking,
            'review_findings' => (int) $identity['review_findings'] + $ugOverlap,
            'snapshot_digest' => hash('sha256', json_encode([
                count($rows),
                $statusCounts,
                $blank,
                $overlength,
                $identity['plan_digest'],
            ], JSON_UNESCAPED_SLASHES) ?: ''),
            'mutation_statements' => 0,
        ];
    }
}
