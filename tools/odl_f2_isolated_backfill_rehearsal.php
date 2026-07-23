<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

require_once dirname(__DIR__) . '/lib/config.php';

$root = dirname(__DIR__);
$suffix = strtolower(bin2hex(random_bytes(6)));
$database = 'oneid_odl_f2_rehearsal_' . $suffix;
if (preg_match('/\Aoneid_odl_f2_rehearsal_[a-f0-9]{12}\z/', $database) !== 1) {
    fwrite(STDERR, "FAIL invalid rehearsal database name\n");
    exit(1);
}

$pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$quotedDatabase = '`' . $database . '`';
$created = false;

try {
    $pdo->exec(
        "CREATE DATABASE {$quotedDatabase}
         CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci"
    );
    $created = true;
    $pdo->exec("USE {$quotedDatabase}");
    $pdo->exec(
        "CREATE TABLE user_tbl (
            u_id VARCHAR(20) NOT NULL,
            u_category INT NOT NULL,
            avail_status INT NOT NULL,
            account_source VARCHAR(16) NOT NULL DEFAULT 'external',
            sync_protected TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (u_id)
        ) ENGINE=InnoDB
          DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"
    );
    $pdo->exec(
        "INSERT INTO user_tbl
            (u_id,u_category,avail_status,account_source,sync_protected)
         VALUES
            ('UG001',10,1,'external',0),
            ('UG002',10,1,'external',0),
            ('UG003',10,0,'external',0)"
    );
    $pdo->exec((string) file_get_contents(
        $root . '/docs/migrations/20260723_odl_f1_provenance_up.sql'
    ));
    $before = $pdo->query(
        "SELECT CONCAT(
            COUNT(*),'|',SUM(u_category),'|',SUM(avail_status),'|',
            SUM(sync_protected)
         ) FROM user_tbl"
    )->fetchColumn();

    $apply = static function (PDO $pdo): int {
        $pdo->exec(
            "INSERT INTO external_source
                (source_code,source_name,source_family,lifecycle_state,
                 is_required,avail_status)
             VALUES
                ('STUDENT_UG','ASIS Undergraduate Student','student',
                 'dormant',0,1)"
        );
        $insert = $pdo->prepare(
            "INSERT INTO user_external_identity
                (u_id,source_code,external_user_id,source_active,source_hash,
                 first_seen_at,last_seen_at,last_sync_at)
             VALUES
                (:u_id,'STUDENT_UG',:external_user_id,1,NULL,NOW(),NOW(),NOW())"
        );
        $count = 0;
        foreach (['UG001', 'UG002', 'UG003'] as $uId) {
            $insert->execute([':u_id' => $uId, ':external_user_id' => $uId]);
            $count += $insert->rowCount();
        }

        return $count;
    };

    $pdo->beginTransaction();
    $inserted = $apply($pdo);
    $inside = (int) $pdo->query(
        "SELECT COUNT(*) FROM user_external_identity
         WHERE source_code='STUDENT_UG'"
    )->fetchColumn();
    $pdo->rollBack();
    $afterRollback = (int) $pdo->query(
        "SELECT COUNT(*) FROM user_external_identity
         WHERE source_code='STUDENT_UG'"
    )->fetchColumn();
    $sourceAfterRollback = (int) $pdo->query(
        "SELECT COUNT(*) FROM external_source
         WHERE source_code='STUDENT_UG'"
    )->fetchColumn();
    printf(
        "PASS transaction inserted=%d inside=%d after_rollback=%d source_after_rollback=%d\n",
        $inserted,
        $inside,
        $afterRollback,
        $sourceAfterRollback
    );
    if ($inserted !== 3 || $inside !== 3 || $afterRollback !== 0 || $sourceAfterRollback !== 0) {
        throw new RuntimeException('ODL_F2_TRANSACTION_REHEARSAL_FAILED');
    }

    $pdo->beginTransaction();
    $committed = $apply($pdo);
    $pdo->commit();
    $afterCommit = (int) $pdo->query(
        "SELECT COUNT(*) FROM user_external_identity
         WHERE source_code='STUDENT_UG'"
    )->fetchColumn();
    $afterUser = $pdo->query(
        "SELECT CONCAT(
            COUNT(*),'|',SUM(u_category),'|',SUM(avail_status),'|',
            SUM(sync_protected)
         ) FROM user_tbl"
    )->fetchColumn();
    if ($committed !== 3 || $afterCommit !== 3 || !hash_equals((string) $before, (string) $afterUser)) {
        throw new RuntimeException('ODL_F2_COMMIT_REHEARSAL_FAILED');
    }

    $pdo->beginTransaction();
    $deleted = $pdo->exec(
        "DELETE FROM user_external_identity WHERE source_code='STUDENT_UG'"
    );
    $sourceDeleted = $pdo->exec(
        "DELETE FROM external_source
         WHERE source_code='STUDENT_UG' AND lifecycle_state='dormant'"
    );
    $pdo->commit();
    $finalMemberships = (int) $pdo->query(
        'SELECT COUNT(*) FROM user_external_identity'
    )->fetchColumn();
    $finalUser = $pdo->query(
        "SELECT CONCAT(
            COUNT(*),'|',SUM(u_category),'|',SUM(avail_status),'|',
            SUM(sync_protected)
         ) FROM user_tbl"
    )->fetchColumn();
    printf(
        "PASS commit_rollback committed=%d deleted=%d source_deleted=%d final_memberships=%d user_unchanged=%s\n",
        $committed,
        $deleted,
        $sourceDeleted,
        $finalMemberships,
        hash_equals((string) $before, (string) $finalUser) ? 'yes' : 'no'
    );
    if (
        $deleted !== 3
        || $sourceDeleted !== 1
        || $finalMemberships !== 0
        || !hash_equals((string) $before, (string) $finalUser)
    ) {
        throw new RuntimeException('ODL_F2_ROLLBACK_REHEARSAL_FAILED');
    }
} finally {
    if ($created) {
        $pdo->exec('USE information_schema');
        $pdo->exec("DROP DATABASE {$quotedDatabase}");
    }
}

echo "RESULT checks=2 failed=0 user_mutations=0 rehearsal_database_removed=yes\n";
