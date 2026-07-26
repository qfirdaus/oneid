<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

require_once dirname(__DIR__) . '/lib/config.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

use OneId\App\Auth\MyDigitalId\MyDigitalIdIdentityProtector;
use OneId\App\Auth\MyDigitalId\MyDigitalIdPersistenceException;
use OneId\App\Auth\MyDigitalId\PdoMyDigitalIdIdentityRepository;

$root = dirname(__DIR__);
$suffix = strtolower(bin2hex(random_bytes(6)));
$database = 'oneid_mydid_f2_rehearsal_' . $suffix;
if (preg_match('/\Aoneid_mydid_f2_rehearsal_[a-f0-9]{12}\z/', $database) !== 1) {
    fwrite(STDERR, "FAIL invalid rehearsal database name\n");
    exit(1);
}

$pdo = new PDO(
    DB_DSN,
    DB_USERNAME,
    DB_PASSWORD,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
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
            avail_status INT NOT NULL,
            data1 VARCHAR(100) NOT NULL,
            data2 VARCHAR(100) NOT NULL,
            data4 VARCHAR(100) NOT NULL,
            PRIMARY KEY (u_id)
        ) ENGINE=InnoDB
          DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"
    );
    $pdo->exec(
        "INSERT INTO user_tbl
            (u_id,avail_status,data1,data2,data4)
         VALUES
            ('F2-PILOT',1,'Fixture User','','900101011234'),
            ('F2-OTHER',1,'Other Fixture','','800101011234')"
    );
    $beforeUsers = (string) $pdo->query(
        "SELECT SHA2(GROUP_CONCAT(
            CONCAT_WS('|',u_id,avail_status,data1,data2,data4)
            ORDER BY u_id SEPARATOR '\n'
         ),256) FROM user_tbl"
    )->fetchColumn();

    $up = (string) file_get_contents(
        $root . '/docs/migrations/20260726_mydigitalid_f2_identity_audit_up.sql'
    );
    if (trim($up) === '') {
        throw new RuntimeException('MYDID_F2_UP_MIGRATION_EMPTY');
    }
    $pdo->exec($up);

    $tables = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA=DATABASE()
           AND TABLE_NAME IN ('user_federated_identity','federated_auth_event')"
    )->fetchColumn();
    $foreignKeys = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA=DATABASE()
           AND TABLE_NAME IN ('user_federated_identity','federated_auth_event')"
    )->fetchColumn();
    $checks = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA=DATABASE()
           AND CONSTRAINT_TYPE='CHECK'
           AND TABLE_NAME IN ('user_federated_identity','federated_auth_event')"
    )->fetchColumn();
    $forbiddenColumns = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA=DATABASE()
           AND TABLE_NAME IN ('user_federated_identity','federated_auth_event')
           AND COLUMN_NAME IN ('nric','nama','name','access_token','refresh_token',
                               'id_token','authorization_code','client_secret')"
    )->fetchColumn();
    $afterMigrationUsers = (string) $pdo->query(
        "SELECT SHA2(GROUP_CONCAT(
            CONCAT_WS('|',u_id,avail_status,data1,data2,data4)
            ORDER BY u_id SEPARATOR '\n'
         ),256) FROM user_tbl"
    )->fetchColumn();

    printf(
        "PASS forward tables=%d foreign_keys=%d checks=%d forbidden_columns=%d user_unchanged=%s\n",
        $tables,
        $foreignKeys,
        $checks,
        $forbiddenColumns,
        hash_equals($beforeUsers, $afterMigrationUsers) ? 'yes' : 'no'
    );
    if (
        $tables !== 2
        || $foreignKeys !== 3
        || $checks !== 3
        || $forbiddenColumns !== 0
        || !hash_equals($beforeUsers, $afterMigrationUsers)
    ) {
        throw new RuntimeException('MYDID_F2_FORWARD_RECONCILIATION_FAILED');
    }

    $protector = MyDigitalIdIdentityProtector::fromBase64(
        base64_encode(random_bytes(32)),
        'f2-fixture-v1'
    );
    $issuer = 'https://sso.digital-id.my/realms/upnm';
    $subjectHmac = $protector->subjectHmac($issuer, 'fixture-subject');
    $nricHmac = $protector->nricHmac('900101-01-1234');
    $repository = new PdoMyDigitalIdIdentityRepository($pdo);
    $timestamp = new DateTimeImmutable('2026-07-26 12:00:00.000000');
    $rollbackSubject = $protector->subjectHmac($issuer, 'rollback-fixture-subject');
    try {
        $repository->transactional(
            static function (PdoMyDigitalIdIdentityRepository $transactionalRepository) use (
                $protector,
                $rollbackSubject,
                $timestamp
            ): void {
                $transactionalRepository->createActiveLink(
                    'F2-OTHER',
                    $rollbackSubject,
                    $protector->nricHmac('800101011234'),
                    $protector->keyId,
                    $timestamp
                );
                throw new RuntimeException('EXPECTED_REHEARSAL_ROLLBACK');
            }
        );
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() !== 'EXPECTED_REHEARSAL_ROLLBACK') {
            throw $exception;
        }
    }
    $transactionRollbackPassed = $repository->findActiveBySubject($rollbackSubject) === null;

    $identityId = $repository->createActiveLink(
        'F2-PILOT',
        $subjectHmac,
        $nricHmac,
        $protector->keyId,
        $timestamp
    );
    $link = $repository->findActiveBySubject($subjectHmac);
    $repository->touchSuccessfulLogin(
        $identityId,
        'F2-PILOT',
        $nricHmac,
        $timestamp->modify('+1 second')
    );
    $successCorrelation = bin2hex(random_bytes(16));
    $eventId = $repository->recordEvent([
        'identity_id' => $identityId,
        'u_id' => 'F2-PILOT',
        'outcome' => 'SUCCESS',
        'reason_code' => 'MYDID_LOGIN_SUCCESS',
        'subject_hmac' => $subjectHmac,
        'nric_hmac' => $nricHmac,
        'hmac_key_id' => $protector->keyId,
        'ip_hmac' => $protector->contextHmac('ip', '192.0.2.10'),
        'user_agent_hmac' => $protector->contextHmac('user-agent', 'F2 fixture'),
        'session_id_hmac' => $protector->contextHmac('session-id', 'fixture-session'),
        'correlation_id' => $successCorrelation,
        'occurred_at' => $timestamp->modify('+1 second'),
    ]);
    $rejectedEventId = $repository->recordEvent([
        'identity_id' => null,
        'u_id' => null,
        'outcome' => 'REJECTED',
        'reason_code' => 'MYDID_USER_NOT_FOUND',
        'subject_hmac' => $protector->subjectHmac($issuer, 'unmatched-fixture-subject'),
        'nric_hmac' => $protector->nricHmac('700101011234'),
        'hmac_key_id' => $protector->keyId,
        'correlation_id' => bin2hex(random_bytes(16)),
        'occurred_at' => $timestamp->modify('+2 seconds'),
    ]);
    $duplicateCorrelationBlocked = false;
    try {
        $repository->recordEvent([
            'identity_id' => $identityId,
            'u_id' => 'F2-PILOT',
            'outcome' => 'SUCCESS',
            'reason_code' => 'MYDID_LOGIN_SUCCESS',
            'correlation_id' => $successCorrelation,
            'occurred_at' => $timestamp->modify('+3 seconds'),
        ]);
    } catch (MyDigitalIdPersistenceException $exception) {
        $duplicateCorrelationBlocked = $exception->reason === 'MYDID_EVENT_RECORD_FAILED';
    }

    $duplicateBlocked = false;
    try {
        $repository->createActiveLink(
            'F2-OTHER',
            $subjectHmac,
            $protector->nricHmac('800101011234'),
            $protector->keyId,
            $timestamp
        );
    } catch (MyDigitalIdPersistenceException $exception) {
        $duplicateBlocked = $exception->reason === 'MYDID_LINK_CREATE_FAILED';
    }
    $mismatchBlocked = false;
    try {
        $repository->touchSuccessfulLogin(
            $identityId,
            'F2-PILOT',
            $protector->nricHmac('800101011234'),
            $timestamp->modify('+2 seconds')
        );
    } catch (MyDigitalIdPersistenceException $exception) {
        $mismatchBlocked = $exception->reason === 'MYDID_ACTIVE_LINK_MISMATCH';
    }

    $stored = $pdo->query(
        'SELECT login_count,CHAR_LENGTH(subject_hmac) subject_length,
                CHAR_LENGTH(nric_hmac) nric_length
         FROM user_federated_identity WHERE identity_id=' . $identityId
    )->fetch(PDO::FETCH_ASSOC);
    $events = (int) $pdo->query('SELECT COUNT(*) FROM federated_auth_event')->fetchColumn();
    $afterRepositoryUsers = (string) $pdo->query(
        "SELECT SHA2(GROUP_CONCAT(
            CONCAT_WS('|',u_id,avail_status,data1,data2,data4)
            ORDER BY u_id SEPARATOR '\n'
         ),256) FROM user_tbl"
    )->fetchColumn();

    printf(
        "PASS repository transaction_rollback=%s link=%s login_count=%d events=%s duplicate_link_blocked=%s duplicate_correlation_blocked=%s mismatch_blocked=%s hmac_only=%s user_unchanged=%s\n",
        $transactionRollbackPassed ? 'yes' : 'no',
        is_array($link) && (int) ($link['identity_id'] ?? 0) === $identityId ? 'yes' : 'no',
        (int) ($stored['login_count'] ?? -1),
        $eventId > 0 && $rejectedEventId > 0 && $events === 2 ? 'yes' : 'no',
        $duplicateBlocked ? 'yes' : 'no',
        $duplicateCorrelationBlocked ? 'yes' : 'no',
        $mismatchBlocked ? 'yes' : 'no',
        (int) ($stored['subject_length'] ?? 0) === 64
            && (int) ($stored['nric_length'] ?? 0) === 64 ? 'yes' : 'no',
        hash_equals($beforeUsers, $afterRepositoryUsers) ? 'yes' : 'no'
    );
    if (
        !is_array($link)
        || !$transactionRollbackPassed
        || (int) ($link['identity_id'] ?? 0) !== $identityId
        || (int) ($stored['login_count'] ?? -1) !== 1
        || $eventId < 1
        || $rejectedEventId < 1
        || $events !== 2
        || !$duplicateBlocked
        || !$duplicateCorrelationBlocked
        || !$mismatchBlocked
        || (int) ($stored['subject_length'] ?? 0) !== 64
        || (int) ($stored['nric_length'] ?? 0) !== 64
        || !hash_equals($beforeUsers, $afterRepositoryUsers)
    ) {
        throw new RuntimeException('MYDID_F2_REPOSITORY_RECONCILIATION_FAILED');
    }

    $down = (string) file_get_contents(
        $root . '/docs/migrations/20260726_mydigitalid_f2_identity_audit_down.sql'
    );
    if (trim($down) === '') {
        throw new RuntimeException('MYDID_F2_DOWN_MIGRATION_EMPTY');
    }
    $pdo->exec($down);
    $tablesAfter = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA=DATABASE()
           AND TABLE_NAME IN ('user_federated_identity','federated_auth_event')"
    )->fetchColumn();
    $afterRollbackUsers = (string) $pdo->query(
        "SELECT SHA2(GROUP_CONCAT(
            CONCAT_WS('|',u_id,avail_status,data1,data2,data4)
            ORDER BY u_id SEPARATOR '\n'
         ),256) FROM user_tbl"
    )->fetchColumn();
    printf(
        "PASS rollback tables=%d user_unchanged=%s\n",
        $tablesAfter,
        hash_equals($beforeUsers, $afterRollbackUsers) ? 'yes' : 'no'
    );
    if ($tablesAfter !== 0 || !hash_equals($beforeUsers, $afterRollbackUsers)) {
        throw new RuntimeException('MYDID_F2_ROLLBACK_RECONCILIATION_FAILED');
    }
} finally {
    if ($created) {
        $pdo->exec('USE information_schema');
        $pdo->exec("DROP DATABASE {$quotedDatabase}");
    }
}

echo "RESULT checks=3 failed=0 live_schema_mutations=0 rehearsal_database_removed=yes raw_pii_output=0\n";
