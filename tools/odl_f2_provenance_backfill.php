<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

require_once dirname(__DIR__) . '/lib/config.php';
require_once dirname(__DIR__) . '/lib/readonly_odbc.php';
require_once dirname(__DIR__) . '/app/Sync/ExternalRowNormalizer.php';
require_once dirname(__DIR__)
    . '/app/Sync/Provenance/ProvenanceBackfillPreview.php';

use OneId\App\Sync\ExternalRowNormalizer;
use OneId\App\Sync\Provenance\ProvenanceBackfillPreview;

const ODL_F2_SOURCE_CODE = 'STUDENT_UG';
const ODL_F2_EXPECTED_SOURCE_ROWS = 5452;
const ODL_F2_EXPECTED_CANDIDATES = 5423;
const ODL_F2_EXPECTED_REVIEW_FINDINGS = 29;
const ODL_F2_EXPECTED_DIGEST =
    '5b5185e2a79d1b46127298fbd3f60303647cf69db71b19c4f0cc4c9b12a7874b';
const ODL_F2_CHANGE_ID = 'ONEID-ODL-F2-20260723-01';

$mode = $argv[1] ?? '--check';
if (!in_array($mode, ['--check', '--apply', '--rollback'], true)) {
    fwrite(
        STDERR,
        "Usage: php tools/odl_f2_provenance_backfill.php [--check|--apply|--rollback]\n"
    );
    exit(2);
}
if (!function_exists('odbc_connect')) {
    fwrite(STDERR, "FAIL ODBC_EXTENSION_UNAVAILABLE\n");
    exit(1);
}

$fetchExternalRows = static function (): array {
    $connection = odbc_connect(
        oneid_secret('ONEID_STUDENT_SYNC_ODBC_DSN'),
        oneid_secret('ONEID_STUDENT_SYNC_ODBC_USERNAME'),
        oneid_secret('ONEID_STUDENT_SYNC_ODBC_PASSWORD')
    );
    if (!$connection) {
        throw new RuntimeException('EXTERNAL_STUDENT_CONNECTION_FAILED');
    }

    try {
        $sql = 'SELECT nama as data1,no_matrik as data4,"" as data3,'
            . 'ISNULL(nokp,"") as data2,ISNULL(email,"") as data5,'
            . 'nama_ptj as data6,program as data7,"" as data8,"" as data9,'
            . '"" as data10,"" as data11,"" as data12,'
            . '"Pelajar" as ext_data_source_category '
            . 'FROM v210_sso_student_aktif';
        $statement = oneid_readonly_odbc_exec($connection, $sql);
        if ($statement === false) {
            throw new RuntimeException('EXTERNAL_STUDENT_QUERY_FAILED');
        }

        $rows = [];
        while ($row = odbc_fetch_array($statement)) {
            $rows[] = ExternalRowNormalizer::normalize($row);
        }

        return $rows;
    } finally {
        odbc_close($connection);
    }
};

$pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$fetchUsers = static fn(): array => $pdo->query(
    'SELECT u_id,data2,avail_status,account_source,sync_protected
     FROM user_tbl'
)->fetchAll(PDO::FETCH_ASSOC);
$fetchMemberships = static fn(): array => $pdo->query(
    'SELECT u_id,source_code,external_user_id
     FROM user_external_identity'
)->fetchAll(PDO::FETCH_ASSOC);
$previewer = new ProvenanceBackfillPreview();

if ($mode === '--rollback') {
    if ((getenv('ONEID_ODL_F2_CHANGE_ID') ?: '') !== ODL_F2_CHANGE_ID) {
        fwrite(STDERR, "FAIL ODL_F2_CHANGE_ID_REQUIRED\n");
        exit(1);
    }

    $pdo->beginTransaction();
    try {
        $source = $pdo->query(
            "SELECT lifecycle_state FROM external_source
             WHERE source_code='STUDENT_UG' FOR UPDATE"
        )->fetchColumn();
        $count = (int) $pdo->query(
            "SELECT COUNT(*) FROM user_external_identity
             WHERE source_code='STUDENT_UG'"
        )->fetchColumn();
        if ($source !== 'dormant' || $count !== ODL_F2_EXPECTED_CANDIDATES) {
            throw new RuntimeException('ODL_F2_ROLLBACK_GUARD_REJECTED');
        }
        $deleted = $pdo->exec(
            "DELETE FROM user_external_identity WHERE source_code='STUDENT_UG'"
        );
        $sourceDeleted = $pdo->exec(
            "DELETE FROM external_source WHERE source_code='STUDENT_UG'
             AND lifecycle_state='dormant'"
        );
        if ($deleted !== ODL_F2_EXPECTED_CANDIDATES || $sourceDeleted !== 1) {
            throw new RuntimeException('ODL_F2_ROLLBACK_RECONCILIATION_FAILED');
        }
        $pdo->commit();
        printf(
            "PASS rollback source=%s memberships_deleted=%d user_mutations=0\n",
            ODL_F2_SOURCE_CODE,
            $deleted
        );
        exit(0);
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

$externalRows = $fetchExternalRows();
$users = $fetchUsers();
$memberships = $fetchMemberships();
$safe = $previewer->preview(
    ODL_F2_SOURCE_CODE,
    $externalRows,
    $users,
    $memberships
);

echo json_encode($safe, JSON_UNESCAPED_SLASHES), "\n";
if ($mode === '--check') {
    exit(0);
}

if ((getenv('ONEID_ODL_F2_CHANGE_ID') ?: '') !== ODL_F2_CHANGE_ID) {
    fwrite(STDERR, "FAIL ODL_F2_CHANGE_ID_REQUIRED\n");
    exit(1);
}
if ((int) $safe['source_rows'] !== ODL_F2_EXPECTED_SOURCE_ROWS
    || (int) $safe['candidate_memberships'] !== ODL_F2_EXPECTED_CANDIDATES
    || (int) $safe['blocking_findings'] !== 0
    || (int) $safe['review_findings'] !== ODL_F2_EXPECTED_REVIEW_FINDINGS
    || !hash_equals(ODL_F2_EXPECTED_DIGEST, (string) $safe['plan_digest'])
) {
    fwrite(STDERR, "FAIL ODL_F2_FRESH_PREVIEW_APPROVAL_MISMATCH\n");
    exit(1);
}

$candidates = $previewer->candidatesForApprovedBackfill(
    ODL_F2_SOURCE_CODE,
    $externalRows,
    $users,
    $memberships,
    ODL_F2_EXPECTED_CANDIDATES,
    ODL_F2_EXPECTED_DIGEST
);

$lockName = 'oneid_odl_f2_provenance_backfill';
$lockQuery = $pdo->prepare('SELECT GET_LOCK(:lock_name, 0)');
$lockQuery->execute([':lock_name' => $lockName]);
if ((int) $lockQuery->fetchColumn() !== 1) {
    throw new RuntimeException('ODL_F2_BACKFILL_LOCK_UNAVAILABLE');
}

try {
    $pdo->beginTransaction();
    $existingSource = $pdo->query(
        "SELECT lifecycle_state FROM external_source
         WHERE source_code='STUDENT_UG' FOR UPDATE"
    )->fetchColumn();
    if ($existingSource !== false) {
        throw new RuntimeException('ODL_F2_SOURCE_ALREADY_REGISTERED');
    }
    $odlState = $pdo->query(
        "SELECT lifecycle_state FROM external_source
         WHERE source_code='STUDENT_ODL_PG' FOR UPDATE"
    )->fetchColumn();
    if ($odlState !== 'dormant') {
        throw new RuntimeException('ODL_F2_ODL_SOURCE_NOT_DORMANT');
    }
    if ($memberships !== []) {
        throw new RuntimeException('ODL_F2_MEMBERSHIP_BASELINE_NOT_EMPTY');
    }

    $sourceInsert = $pdo->prepare(
        'INSERT INTO external_source
            (source_code,source_name,source_family,lifecycle_state,
             is_required,avail_status)
         VALUES
            (:source_code,:source_name,:source_family,:lifecycle_state,0,1)'
    );
    $sourceInsert->execute([
        ':source_code' => ODL_F2_SOURCE_CODE,
        ':source_name' => 'ASIS Undergraduate Student',
        ':source_family' => 'student',
        ':lifecycle_state' => 'dormant',
    ]);

    $insert = $pdo->prepare(
        'INSERT INTO user_external_identity
            (u_id,source_code,external_user_id,source_active,source_hash,
             first_seen_at,last_seen_at,last_sync_at)
         VALUES
            (:u_id,:source_code,:external_user_id,1,NULL,NOW(),NOW(),NOW())'
    );
    $inserted = 0;
    foreach ($candidates as $candidate) {
        $insert->execute([
            ':u_id' => $candidate['u_id'],
            ':source_code' => ODL_F2_SOURCE_CODE,
            ':external_user_id' => $candidate['external_user_id'],
        ]);
        $inserted += $insert->rowCount();
    }

    $reconciled = (int) $pdo->query(
        "SELECT COUNT(*) FROM user_external_identity
         WHERE source_code='STUDENT_UG' AND source_active=1"
    )->fetchColumn();
    if ($inserted !== ODL_F2_EXPECTED_CANDIDATES
        || $reconciled !== ODL_F2_EXPECTED_CANDIDATES
    ) {
        throw new RuntimeException('ODL_F2_BACKFILL_RECONCILIATION_FAILED');
    }

    $pdo->commit();
    printf(
        "PASS apply source=%s memberships_inserted=%d reconciled=%d user_mutations=0\n",
        ODL_F2_SOURCE_CODE,
        $inserted,
        $reconciled
    );
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $exception;
} finally {
    $release = $pdo->prepare('SELECT RELEASE_LOCK(:lock_name)');
    $release->execute([':lock_name' => $lockName]);
}
