<?php

/**
 * Read-only S4E deactivation investigation.
 *
 * Default output contains digests only. `--reveal` requires an interactive
 * confirmation and prints user IDs to the operator terminal only; never copy
 * revealed output into Git, tickets, logs or chat.
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
    fwrite(STDERR, "This will display user IDs locally. Do not copy the output.\n");
    fwrite(STDERR, "Type REVEAL to continue: ");
    $confirmation = trim((string) fgets(STDIN));
    if (!hash_equals('REVEAL', $confirmation)) {
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

$deactivations = array_values(array_filter(
    $plan->actions,
    static fn(array $action): bool => ($action['action'] ?? '') === 'DEACTIVATE'
));
$newActions = array_values(array_filter(
    $plan->actions,
    static fn(array $action): bool => ($action['action'] ?? '') === 'NEW'
));

$identities = static function (array $row): array {
    $values = [];
    foreach (['u_id', 'data2', 'data4'] as $field) {
        $value = trim((string) ($row[$field] ?? ''));
        if ($value !== '') {
            $values[$value] = true;
        }
    }
    return array_keys($values);
};

printf(
    "READ_ONLY source=%d new=%d update=%d deactivate=%d reactivate=%d plan=%s...\n",
    $plan->sourceRows,
    $counts['New'],
    $counts['Update'],
    $counts['Deactivate'],
    $counts['Reactivate'],
    substr($plan->planHash(), 0, 12)
);

foreach ($deactivations as $index => $action) {
    $userId = trim((string) ($action['u_id'] ?? ''));
    $oldRow = is_array($action['row'] ?? null) ? $action['row'] : [];
    $oldIdentityMap = array_fill_keys($identities($oldRow), true);
    $linkedNewIds = [];

    foreach ($newActions as $newAction) {
        $newRow = is_array($newAction['row'] ?? null) ? $newAction['row'] : [];
        foreach ($identities($newRow) as $identity) {
            if (isset($oldIdentityMap[$identity])) {
                $linkedNewIds[] = trim((string) ($newAction['u_id'] ?? ''));
                break;
            }
        }
    }
    $linkedNewIds = array_values(array_unique(array_filter($linkedNewIds)));

    printf(
        "candidate=%d uid_digest=%s source=%s protected=%d linked_new=%d\n",
        $index + 1,
        substr(hash('sha256', $userId), 0, 16),
        (string) ($oldRow['account_source'] ?? 'unknown'),
        (int) ($oldRow['sync_protected'] ?? 0),
        count($linkedNewIds)
    );

    foreach ($linkedNewIds as $linkedIndex => $linkedId) {
        printf(
            "candidate=%d linked_new_digest_%d=%s\n",
            $index + 1,
            $linkedIndex + 1,
            substr(hash('sha256', $linkedId), 0, 16)
        );
    }

    if ($reveal) {
        printf("candidate=%d user_id=%s\n", $index + 1, $userId);
        foreach ($linkedNewIds as $linkedIndex => $linkedId) {
            printf(
                "candidate=%d linked_new_user_id_%d=%s\n",
                $index + 1,
                $linkedIndex + 1,
                $linkedId
            );
        }
    }
}

printf(
    "RESULT candidates=%d reveal=%s mutation_statements=0\n",
    count($deactivations),
    $reveal ? 'yes' : 'no'
);

