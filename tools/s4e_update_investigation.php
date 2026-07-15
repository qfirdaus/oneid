<?php

/**
 * Read-only S4E update investigation.
 *
 * Default output contains stable identity/change digests and field names only.
 * --reveal requires interactive confirmation and prints raw values locally.
 * No approval, transaction, audit writer or Apply path is loaded.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(2);
}

$root = dirname(__DIR__);
require_once $root . '/lib/config.php';
require_once $root . '/lib/external_data_source_API.php';
require_once $root . '/bootstrap/sync_runtime.php';

$reveal = in_array('--reveal', $argv, true);
if ($reveal) {
    if (!function_exists('stream_isatty') || !stream_isatty(STDIN)) {
        fwrite(STDERR, "REFUSED: --reveal requires an interactive terminal.\n");
        exit(2);
    }
    fwrite(STDERR, "This will display user IDs and changed values locally. Do not copy the output.\n");
    fwrite(STDERR, "Type REVEAL-UPDATES to continue: ");
    $confirmation = trim((string) fgets(STDIN));
    if (!hash_equals('REVEAL-UPDATES', $confirmation)) {
        fwrite(STDERR, "Cancelled.\n");
        exit(2);
    }
}

$externalRows = EXTERNAL_DATA_SOURCE_GET_ALL_USER();
$activeUsers = $operation->sync_get_all_sso_user();
$inactiveUserIds = $operation->sync_get_inactive_user_ids();
$planner = new \OneId\App\Sync\SyncPlanner(
    new \OneId\App\Sync\Adapters\LegacySyncPolicy()
);
$plan = $planner->plan($externalRows, $activeUsers, $inactiveUserIds);
$counts = $plan->legacyCounts();
$updates = array_values(array_filter(
    $plan->actions,
    static fn(array $action): bool => ($action['action'] ?? '') === 'UPDATE'
));
usort($updates, static fn(array $left, array $right): int => strcmp(
    hash('sha256', trim((string) ($left['u_id'] ?? ''))),
    hash('sha256', trim((string) ($right['u_id'] ?? '')))
));

printf(
    "READ_ONLY source=%d new=%d update=%d deactivate=%d reactivate=%d plan=%s...\n",
    $plan->sourceRows,
    $counts['New'],
    $counts['Update'],
    $counts['Deactivate'],
    $counts['Reactivate'],
    substr($plan->planHash(), 0, 12)
);

$manifest = [];
foreach ($updates as $index => $action) {
    $userId = trim((string) ($action['u_id'] ?? ''));
    $changedFields = array_values(array_filter(array_map(
        'trim',
        explode(',', (string) ($action['changed_fields'] ?? ''))
    )));
    sort($changedFields, SORT_STRING);
    $uidDigest = hash('sha256', $userId);
    $changeFingerprint = hash('sha256', $uidDigest . '|' . implode(',', $changedFields));
    $manifest[] = $changeFingerprint;

    printf(
        "candidate=%d uid_digest=%s changed_fields=%s change_fingerprint=%s pilot_selected=%s\n",
        $index + 1,
        substr($uidDigest, 0, 16),
        $changedFields === [] ? '-' : implode(',', $changedFields),
        substr($changeFingerprint, 0, 16),
        $index === 0 ? 'yes' : 'no'
    );

    if ($reveal) {
        printf("candidate=%d user_id=%s\n", $index + 1, $userId);
        $oldData = is_array($action['old_data'] ?? null) ? $action['old_data'] : [];
        $newData = is_array($action['new_data'] ?? null) ? $action['new_data'] : [];
        foreach ($changedFields as $field) {
            printf(
                "candidate=%d field=%s old=%s new=%s\n",
                $index + 1,
                $field,
                (string) ($oldData[$field] ?? ''),
                (string) ($newData[$field] ?? '')
            );
        }
    }
}
sort($manifest, SORT_STRING);
printf("MANIFEST digest=%s count=%d\n", hash('sha256', implode("\n", $manifest)), count($manifest));
printf(
    "RESULT candidates=%d reveal=%s mutation_statements=0\n",
    count($updates),
    $reveal ? 'yes' : 'no'
);
