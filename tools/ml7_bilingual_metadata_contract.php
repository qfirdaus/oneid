<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$checks = 0;
$failed = 0;
$report = static function (bool $ok, string $label) use (&$checks, &$failed): void {
    $checks++;
    echo ($ok ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
    if (!$ok) {
        $failed++;
    }
};

foreach ([
    'app/Metadata/BilingualMetadataRepository.php',
    'lib/q_func.php',
    'lib/request_security.php',
    'admin/dashboard.php',
    'page/dashboard.php',
    'page/admin_step_up.php',
    'tests/characterization/ml7_bilingual_metadata.php',
    'tools/ml7_metadata_readiness_preview.php',
    'tools/ml7_metadata_schema_rehearsal.php',
    'docs/migrations/20260725_ml7_metadata_translation_up.sql',
    'docs/migrations/20260725_ml7_metadata_translation_down.sql',
] as $relative) {
    exec('php -l ' . escapeshellarg($root . '/' . $relative) . ' 2>&1', $output, $status);
    $report($status === 0, "source and lint {$relative}");
}

$repository = file_get_contents($root . '/app/Metadata/BilingualMetadataRepository.php') ?: '';
$endpoint = file_get_contents($root . '/lib/q_func.php') ?: '';
$security = file_get_contents($root . '/lib/request_security.php') ?: '';
$admin = file_get_contents($root . '/admin/dashboard.php') ?: '';
$user = file_get_contents($root . '/page/dashboard.php') ?: '';
$up = file_get_contents($root . '/docs/migrations/20260725_ml7_metadata_translation_up.sql') ?: '';
$down = file_get_contents($root . '/docs/migrations/20260725_ml7_metadata_translation_down.sql') ?: '';

$report(
    str_contains($up, 'CREATE TABLE sp_app_translation')
    && str_contains($up, 'CREATE TABLE sp_group_translation')
    && str_contains($up, 'CREATE TABLE metadata_translation_history')
    && str_contains($up, "CHECK (locale IN ('ms','en'))"),
    'additive schema constrains locale and separates audit history'
);
$report(
    str_contains($up, 'UNIQUE KEY uq_sp_app_translation_locale (sp_id, locale)')
    && str_contains($up, 'UNIQUE KEY uq_sp_group_translation_locale (sp_group_id, locale)'),
    'entity and locale uniqueness is enforced'
);
$report(
    !str_contains($up, 'ALTER TABLE sp_list')
    && !str_contains($up, 'ALTER TABLE sp_group')
    && str_contains($down, 'DROP TABLE sp_app_translation')
    && !str_contains($down, 'DROP TABLE sp_list'),
    'original metadata schema is not modified by up or down migration'
);
$report(
    str_contains($repository, 'sp_name_original')
    && str_contains($repository, 'sp_description_original')
    && str_contains($repository, "'metadata_fallback'"),
    'repository preserves original metadata and explicit fallback'
);
$report(
    str_contains($repository, 'translation_version=:expected_version')
    && str_contains($repository, 'ML7_METADATA_STALE')
    && str_contains($repository, 'metadata_translation_history'),
    'writes are optimistic, audited and stale-safe'
);
$report(
    str_contains($endpoint, 'localizeGroups($all_groups_info,oneid_current_locale())')
    && str_contains($endpoint, 'localizeGroups(array_values($groups),oneid_current_locale())'),
    'User and Administrator read paths are locale aware'
);
$report(
    str_contains($security, "'admin_save_metadata_translation'")
    && str_contains($security, "'SECURITY_CONFIGURATION_CHANGE'"),
    'metadata write requires security configuration step-up'
);
$report(
    str_contains($admin, 'modal_metadata_translations')
    && str_contains($admin, 'admin_metadata_translation_preview')
    && str_contains($admin, 'admin_save_metadata_translation'),
    'Administrator guarded translation-management UI is wired'
);
$report(
    !preg_match('/\bSET\b[^;]*(?:sp_domain|sp_group_id|sp_sso_support)\s*=/is', $repository),
    'repository cannot mutate URL, category assignment or SSO configuration'
);
$report(
    str_contains($repository, 'ML7_METADATA_NO_CHANGES')
    && str_contains($repository, 'translation_exists')
    && str_contains($repository, 'originalMetadata'),
    'editor exposes original context and suppresses no-op version history'
);
$report(
    str_contains($admin, 'metadataReadSequence')
    && str_contains($admin, 'metadataReadRequest.abort()')
    && str_contains($admin, 'translations.by_locale'),
    'Administrator editor rejects stale reads and displays locale-scoped coverage'
);
$report(
    str_contains($admin, 'select2.min.js?v=4.0.3')
    && str_contains($admin, "entitySelect.select2({")
    && str_contains($admin, "dropdownParent:$('#modal_metadata_translations')"),
    'record selector uses a modal-safe searchable Select2 control'
);
$report(
    !str_contains($repository, 'SYNC_')
    && !str_contains($repository, 'ODL_')
    && !str_contains($repository, 'AdminStepUp'),
    'External Sync implementation remains independent from metadata wiring'
);

passthru('php ' . escapeshellarg($root . '/tests/characterization/ml7_bilingual_metadata.php'), $status);
$report($status === 0, 'ML7 characterization passes');
passthru('php ' . escapeshellarg($root . '/tools/ml7_metadata_schema_rehearsal.php'), $status);
$report($status === 0, 'ML7 up/down and repository rehearsal passes');

echo "RESULT checks={$checks} failed={$failed}\n";
exit($failed === 0 ? 0 : 1);
