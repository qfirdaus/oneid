<?php
declare(strict_types=1);

namespace OneId\App\Metadata;

use PDO;

final class MetadataContentInventory
{
    public function __construct(private readonly PDO $pdo)
    {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    /** @return array<string,mixed> */
    public function preview(): array
    {
        $applications = $this->pdo->query(
            'SELECT sp_id,sp_name,sp_description,avail_status,sp_group_id
             FROM sp_list ORDER BY sp_id'
        )->fetchAll();
        $categories = $this->pdo->query(
            'SELECT sp_group_id,sp_group_name FROM sp_group ORDER BY sp_group_id'
        )->fetchAll();
        $appTranslations = $this->translations(
            'sp_app_translation',
            'sp_id',
            ['sp_name', 'sp_description', 'translation_version']
        );
        $categoryTranslations = $this->translations(
            'sp_group_translation',
            'sp_group_id',
            ['sp_group_name', 'translation_version']
        );
        $reviewDecisions = $this->reviewDecisions();

        $items = [];
        foreach ($applications as $row) {
            $id = (string) $row['sp_id'];
            $existing = $appTranslations[$id]['en'] ?? null;
            $classification = $this->classify(
                'application',
                (string) $row['sp_name'],
                (string) $row['sp_description'],
                (int) $row['avail_status'] === 1,
                $existing !== null
            );
            $draft = $this->applicationDraft(
                $id,
                (string) $row['sp_name'],
                (string) $row['sp_description']
            );
            $items[] = [
                'entity_type' => 'application',
                'entity_id' => $id,
                'active' => (int) $row['avail_status'],
                'category_id' => (string) $row['sp_group_id'],
                'original_name' => trim((string) $row['sp_name']),
                'original_description' => trim((string) $row['sp_description']),
                'classification' => $classification,
                'review_decision' => $existing !== null ? 'APPROVED_EXISTING' : 'PENDING_OWNER_REVIEW',
                'draft_ms_name' => trim((string) $row['sp_name']),
                'draft_ms_description' => trim((string) $row['sp_description']),
                'draft_en_name' => $existing['sp_name'] ?? $draft['name'],
                'draft_en_description' => $existing['sp_description'] ?? $draft['description'],
                'existing_en_version' => (int) ($existing['translation_version'] ?? 0),
                'source_digest' => $this->sourceDigest('application', $row),
            ];
        }
        foreach ($categories as $row) {
            $id = (string) $row['sp_group_id'];
            $existing = $categoryTranslations[$id]['en'] ?? null;
            $classification = $this->classify(
                'category',
                (string) $row['sp_group_name'],
                '',
                true,
                $existing !== null
            );
            $items[] = [
                'entity_type' => 'category',
                'entity_id' => $id,
                'active' => 1,
                'original_name' => trim((string) $row['sp_group_name']),
                'original_description' => '',
                'classification' => $classification,
                'review_decision' => $existing !== null ? 'APPROVED_EXISTING' : 'PENDING_OWNER_REVIEW',
                'draft_ms_name' => trim((string) $row['sp_group_name']),
                'draft_ms_description' => '',
                'draft_en_name' => $existing['sp_group_name'] ?? $this->categoryDraft((string) $row['sp_group_name']),
                'draft_en_description' => '',
                'existing_en_version' => (int) ($existing['translation_version'] ?? 0),
                'source_digest' => $this->sourceDigest('category', $row),
            ];
        }

        $staleSourceCount = 0;
        foreach ($items as &$item) {
            $identity = $item['entity_type'] . ':' . $item['entity_id'] . ':en';
            $review = $reviewDecisions[$identity] ?? null;
            if ($review === null) {
                continue;
            }
            if (!hash_equals((string) $review['source_digest'], (string) $item['source_digest'])) {
                $item['review_decision'] = 'STALE_REVIEW';
                $staleSourceCount++;
                continue;
            }
            $item['classification'] = (string) $review['classification'];
            $item['review_decision'] = (string) $review['review_decision'];
            $item['review_evidence_reference'] = (string) $review['evidence_reference'];
        }
        unset($item);

        $classificationCounts = [];
        $decisionCounts = [];
        foreach ($items as $item) {
            $classificationCounts[$item['classification']] =
                ($classificationCounts[$item['classification']] ?? 0) + 1;
            $decisionCounts[$item['review_decision']] =
                ($decisionCounts[$item['review_decision']] ?? 0) + 1;
        }
        ksort($classificationCounts);
        ksort($decisionCounts);
        $duplicates = $this->duplicateContent($items);
        $approved = count(array_filter(
            $items,
            static fn(array $item): bool => !in_array(
                $item['review_decision'],
                ['PENDING_OWNER_REVIEW', 'STALE_REVIEW'],
                true
            )
        ));
        $total = count($items);
        $manifest = [
            'source' => ['applications' => count($applications), 'categories' => count($categories)],
            'classification_counts' => $classificationCounts,
            'review_decision_counts' => $decisionCounts,
            'approved_items' => $approved,
            'pending_owner_review' => $total - $approved,
            'completion_percent' => $total === 0 ? 100 : round(($approved / $total) * 100, 2),
            'duplicate_content_groups' => count($duplicates),
            'duplicate_content' => $duplicates,
            'unresolved_identity_count' => count(array_filter(
                $items,
                static fn(array $item): bool => $item['entity_id'] === ''
            )),
            'duplicate_identity_count' => $total - count(array_unique(array_map(
                static fn(array $item): string => $item['entity_type'] . ':' . $item['entity_id'],
                $items
            ))),
            'stale_source_count' => $staleSourceCount,
            'items' => $items,
        ];
        $blockingCodes = [];
        if (($manifest['pending_owner_review'] ?? 0) > 0) {
            $blockingCodes[] = 'ML7A_OWNER_REVIEW_INCOMPLETE';
        }
        if (($manifest['unresolved_identity_count'] ?? 0) > 0
            || ($manifest['duplicate_identity_count'] ?? 0) > 0
        ) {
            $blockingCodes[] = 'ML7A_IDENTITY_INTEGRITY_FAILED';
        }
        if (($manifest['stale_source_count'] ?? 0) > 0) {
            $blockingCodes[] = 'ML7A_REVIEW_SOURCE_STALE';
        }
        return [
            'status' => 1,
            'code' => 'ML7A_CONTENT_PREVIEW_READY',
            'mode' => 'inventory_draft_preview',
            'can_apply' => false,
            'automatic_approval' => false,
            'bulk_apply_ready' => $blockingCodes === [],
            'blocking_codes' => $blockingCodes,
            'manifest' => $manifest,
            'manifest_digest' => hash('sha256', json_encode($manifest, JSON_THROW_ON_ERROR)),
            'mutation_statements' => 0,
        ];
    }

    /** @return array<string,array<string,mixed>> */
    private function reviewDecisions(): array
    {
        $statement = $this->pdo->query(
            "SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema=DATABASE() AND table_name='metadata_content_review'"
        );
        if ((int) $statement->fetchColumn() !== 1) {
            return [];
        }
        $result = [];
        foreach ($this->pdo->query(
            'SELECT entity_type,entity_id,locale,classification,review_decision,
                    source_digest,evidence_reference
             FROM metadata_content_review'
        )->fetchAll() as $row) {
            $result[
                $row['entity_type'] . ':' . $row['entity_id'] . ':' . $row['locale']
            ] = $row;
        }
        return $result;
    }

    /** @param list<string> $columns
     *  @return array<string,array<string,array<string,mixed>>>
     */
    private function translations(string $table, string $idColumn, array $columns): array
    {
        $selected = implode(',', array_map(
            static fn(string $column): string => "`{$column}`",
            $columns
        ));
        $result = [];
        foreach ($this->pdo->query(
            "SELECT `{$idColumn}`,locale,{$selected} FROM `{$table}`"
        )->fetchAll() as $row) {
            $result[(string) $row[$idColumn]][(string) $row['locale']] = $row;
        }
        return $result;
    }

    private function classify(
        string $entityType,
        string $name,
        string $description,
        bool $active,
        bool $existing
    ): string
    {
        if ($existing) {
            return 'EXISTING_TRANSLATION_APPROVED';
        }
        $combined = trim($name . ' ' . $description);
        if (!$active && preg_match('/(?:\bTEST\b|WA[245][ -]|UAT-20|Temporary)/i', $combined) === 1) {
            return 'REVIEW_REQUIRED';
        }
        if (!$active) {
            return 'INTENTIONALLY_FALLBACK';
        }
        if (str_contains($combined, 'â€”') || trim($name) === ''
            || ($entityType === 'application' && trim($description) === '')
        ) {
            return 'REVIEW_REQUIRED';
        }
        if (preg_match('/\A(?:MyCampus|IQS-Framework|Celik Madani|Portal MOOC|UPNM30|NON SSO)\b/i', trim($name)) === 1) {
            return 'PROPER_NOUN_INVARIANT';
        }
        return 'TRANSLATION_REQUIRED';
    }

    /** @return array{name:string,description:string} */
    private function applicationDraft(string $id, string $name, string $description): array
    {
        $drafts = [
            '255P8BZEDL' => ['MyCampus', 'MyCampus Portal'],
            '3LOCHMW45E' => ['APEL Registration System (Administrator)', 'APEL.A Level 6 & 7 Application Management System (Administrator)'],
            '5OFZ2YYNNG' => ['Asset Management System (SPA)', 'Asset Management System (SPA)'],
            '5QK1E12NKR' => ['Lecturer Teaching Evaluation System', 'Lecturer Teaching Evaluation System'],
            '8R8QLPLTDN' => ['Celik Madani (ASNB)', 'Celik Madani, Financial Literacy and Belia Madani'],
            'BTOG4WZNQP' => ['IQS-Framework', 'IQS-Framework'],
            'C2TGT7QQK4' => ['Risk Management System (e-Risk)', 'UPNM Risk Management System'],
            'DYYOWQGYLE' => ['e-Performance System (Administrator)', 'Non-Academic e-Performance System (Administrator)'],
            'EJEN8QNV9N' => ['ODL System', 'Open and Distance Learning System (ODL)'],
            'G24JJET3EV' => ['E-Security System', 'E-Security System'],
            'H22UTX4U7Y' => ['IStAD System', 'Integrated Student Affairs Data System (IStAD)'],
            'KQF5YRG3GD' => ['PEKA System (Administrator)', 'Electronic Comments & Complaints Portal (PEKA) — Moderator'],
            'LULYRSYM8I' => ['E-Counselling System', 'UPNM E-Counselling System'],
            'LYFO08UX23' => ['E-PMS System', 'Project Monitoring System'],
            'NKBUVTXBWN' => ['E-Cover System', 'Medical Coverage Team Application System'],
            'NQ1B9X7D9D' => ['PEKA System', 'Electronic Comments & Complaints Portal (PEKA)'],
            'NQLH2V1M3X' => ['Zakat System (Administrator)', 'Zakat System (Administrator)'],
            'NRINRFSSEN' => ['Salary Portal', 'UPNM Staff Salary Portal'],
            'O9NDT81CYJ' => ['E-SPPLN System', 'Overseas Travel Application System (ESPPLN)'],
            'OMNRTOBCXR' => ['E-Procurement System (EP)', 'UPNM E-Procurement System'],
            'OOYOUFQQHS' => ['E-LPPT System (Administrator)', 'Annual Performance Assessment E-Report System (Administrator)'],
            'OY169SDFVY' => ['E-Learning Portal', 'E-Learning Portal'],
            'PEYYRREE2B' => ['E-Student Affairs System', 'E-Student Affairs System (i-Star, i-Care)'],
            'PKMUSQM80X' => ['Alumni System (Administrator)', 'UPNM Alumni Information System (AIS)'],
            'PYZF3KU6QH' => ['Work From Home System (Administrator)', 'Work From Home System (e-BDR)'],
            'Q1V3FJDZ0Q' => ['E-LPPT System', 'Annual Performance Assessment E-Report System'],
            'ROSNJ0FR3D' => ['Staff Attendance System', 'UPNM Staff Attendance System'],
            'VAX0HZO9HX' => ['E-Vehicle System', 'E-Vehicle System'],
            'VWGNQLQLES' => ['E-Meeting System', 'E-Meeting System'],
            'VXMWPZQR2H' => ['E-HRM System', 'Staff Information System 2.0 (e-HRM)'],
            'XIBYRMNYYX' => ['MOOC Portal', 'UPNM MOOC & MC'],
            'XJKYI1VDZY' => ['i-MAP System', 'UPNM Personnel Information System'],
            'XNNW6TDOHO' => ['Residential College Management System (Administrator)', 'Student Information System (Student Residential Management Centre)'],
            'XR6OQUXAL7' => ['E-College System', 'Residential college applications, electrical item stickers, damage complaints and residential college summons information'],
            'YJRRRWRMX1' => ['Zakat System', 'Zakat System'],
            'ZUGLXDNM49' => ['SKP System', 'Student Financial System (SKP)'],
        ];
        if (isset($drafts[$id])) {
            return ['name' => $drafts[$id][0], 'description' => $drafts[$id][1]];
        }
        return [
            'name' => $this->englishDraft($name),
            'description' => $this->englishDraft($description),
        ];
    }

    private function categoryDraft(string $value): string
    {
        return match (strtoupper(trim($value))) {
            'UNCATEGORIZE' => 'Uncategorized',
            'HR' => 'Human Resources',
            'PELAJAR' => 'Students',
            'NON SSO' => 'NON SSO',
            'SOKONGAN' => 'Support',
            'KEWANGAN' => 'Finance',
            'UPNM30' => 'UPNM30',
            default => $this->englishDraft($value),
        };
    }

    private function englishDraft(string $value): string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? $value);
        $phrases = [
            'Pusat Pengurusan Kediaman Pelajar' => 'Student Residential Management Centre',
            'Sistem Pengurusan Kolej Kediaman' => 'Residential College Management System',
            'Sistem Pengurusan Permohonan' => 'Application Management System',
            'Sistem Pembelajaran Jarak Jauh dan Terbuka' => 'Open and Distance Learning System',
            'Sistem Penilaian Pengajaran Pensyarah' => 'Lecturer Teaching Evaluation System',
            'Sistem Permohonan Perjalanan Luar Negara' => 'Overseas Travel Application System',
            'Sistem Permohonan Pasukan Perlindungan Perubatan' => 'Medical Coverage Team Application System',
            'Sistem E-Laporan Penilaian Prestasi Tahunan' => 'Annual Performance Assessment E-Report System',
            'Sistem e-Laporan Penilaian Prestasi Tahunan' => 'Annual Performance Assessment E-Report System',
            'Sistem Maklumat Pascasiswazah' => 'Postgraduate Information System',
            'Sistem Maklumat Personel' => 'Personnel Information System',
            'Sistem Maklumat Personal' => 'Personnel Information System',
            'Sistem Maklumat Staf' => 'Staff Information System',
            'Sistem Maklumat Pelajar' => 'Student Information System',
            'Sistem Pemantauan Projek' => 'Project Monitoring System',
            'Sistem Pengurusan Risiko' => 'Risk Management System',
            'Sistem Pengurusan Aset' => 'Asset Management System',
            'Sistem Bekerja Dari Rumah' => 'Work From Home System',
            'Sistem Kewangan Pelajar' => 'Student Financial System',
            'Sistem Permohonan Zakat' => 'Zakat Application System',
            'Sistem E-Perolehan' => 'E-Procurement System',
            'Sistem E-Kenderaan' => 'E-Vehicle System',
            'Sistem E-Keselamatan' => 'E-Security System',
            'Sistem E-Kaunseling' => 'E-Counselling System',
            'Sistem E-Mesyuarat' => 'E-Meeting System',
            'Portal Gaji Staf' => 'Staff Salary Portal',
            'Portal Elektronik Komen & Aduan' => 'Electronic Comments & Complaints Portal',
            'Permohonan kolej kediaman' => 'Residential college applications',
            '(Pentadbir)' => '(Administrator)',
            '(Admin)' => '(Administrator)',
            'Tahap' => 'Level',
            'Sistem' => 'System',
            'Pentadbir' => 'Administrator',
        ];
        return strtr($value, $phrases);
    }

    /** @param array<string,mixed> $row */
    private function sourceDigest(string $type, array $row): string
    {
        return hash('sha256', json_encode([$type, $row], JSON_THROW_ON_ERROR));
    }

    /** @param list<array<string,mixed>> $items
     *  @return list<array{name:string,entity_ids:list<string>}>
     */
    private function duplicateContent(array $items): array
    {
        $groups = [];
        foreach ($items as $item) {
            if ($item['entity_type'] !== 'application') {
                continue;
            }
            $normalized = strtolower(trim(preg_replace(
                '/\s+/',
                ' ',
                (string) $item['original_name']
            ) ?? ''));
            if ($normalized !== '') {
                $groups[$normalized][] = (string) $item['entity_id'];
            }
        }
        $duplicates = [];
        foreach ($groups as $name => $ids) {
            if (count($ids) > 1) {
                $duplicates[] = ['name' => $name, 'entity_ids' => $ids];
            }
        }
        return $duplicates;
    }
}
