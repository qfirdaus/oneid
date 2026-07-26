<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

require_once dirname(__DIR__) . '/lib/config.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

use OneId\App\Auth\MyDigitalId\MyDigitalIdAccountLinkingService;
use OneId\App\Auth\MyDigitalId\MyDigitalIdIdentityProtector;
use OneId\App\Auth\MyDigitalId\MyDigitalIdVerifiedIdentity;
use OneId\App\Auth\MyDigitalId\PdoMyDigitalIdAccountMatcher;
use OneId\App\Auth\MyDigitalId\PdoMyDigitalIdIdentityRepository;

$root = dirname(__DIR__);
$database = 'oneid_mydid_f4_rehearsal_' . strtolower(bin2hex(random_bytes(6)));
if (preg_match('/\Aoneid_mydid_f4_rehearsal_[a-f0-9]{12}\z/', $database) !== 1) {
    exit(1);
}
$pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$quotedDatabase = '`' . $database . '`';
$created = false;

$identity = static fn(string $subject, string $name, string $nric): MyDigitalIdVerifiedIdentity =>
    new MyDigitalIdVerifiedIdentity($subject, $name, $nric, 'a.b.c');

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
            u_type INT NOT NULL DEFAULT 0,
            avail_status INT NOT NULL,
            password_change_required INT NOT NULL DEFAULT 0,
            data1 VARCHAR(100) NOT NULL,
            data2 VARCHAR(100) NOT NULL,
            data3 VARCHAR(100) NOT NULL,
            data4 VARCHAR(100) NOT NULL,
            PRIMARY KEY (u_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"
    );
    $pdo->exec(
        "INSERT INTO user_tbl
            (u_id,u_type,avail_status,password_change_required,data1,data2,data3,data4)
         VALUES
            ('STAFF-A',0,1,0,'Canonical Staff','','0530-09','900101-01-1234'),
            ('STUDENT-A',0,1,0,'Canonical Student','910101011234','','A100'),
            ('INACTIVE-A',0,0,0,'Inactive User','','0999-01','920101011234'),
            ('DUPLICATE-A',0,1,0,'Duplicate A','','0888-01','930101011234'),
            ('DUPLICATE-B',0,1,0,'Duplicate B','930101011234','','B200')"
    );
    $beforeUsers = (string) $pdo->query(
        "SELECT SHA2(GROUP_CONCAT(CONCAT_WS('|',u_id,u_type,avail_status,
                    password_change_required,data1,data2,data3,data4)
                 ORDER BY u_id SEPARATOR '\n'),256)
         FROM user_tbl"
    )->fetchColumn();
    $migration = (string) file_get_contents(
        $root . '/docs/migrations/20260726_mydigitalid_f2_identity_audit_up.sql'
    );
    $pdo->exec($migration);

    $protector = MyDigitalIdIdentityProtector::fromBase64(
        base64_encode(str_repeat("\x44", 32)),
        'f4-fixture-v1'
    );
    $service = new MyDigitalIdAccountLinkingService(
        new PdoMyDigitalIdIdentityRepository($pdo),
        new PdoMyDigitalIdAccountMatcher($pdo),
        $protector
    );
    $at = new DateTimeImmutable('2026-07-26 14:00:00.000000');
    $context = [
        'ip' => '192.0.2.40',
        'user_agent' => 'F4 isolated rehearsal',
        'session_id' => 'f4-session',
    ];

    $staff = $service->authenticate(
        $identity('subject-staff', 'Provider Name Must Not Replace', '900101011234'),
        $at,
        $context
    );
    $staffRepeat = $service->authenticate(
        $identity('subject-staff', 'Another Provider Name', '900101011234'),
        $at->modify('+1 second'),
        $context
    );
    $subjectTakeover = $service->authenticate(
        $identity('different-subject', 'Canonical Staff', '900101011234'),
        $at->modify('+2 seconds'),
        $context
    );
    $student = $service->authenticate(
        $identity('subject-student', 'Provider Student', '910101011234'),
        $at->modify('+3 seconds'),
        $context
    );
    $inactive = $service->authenticate(
        $identity('subject-inactive', 'Inactive', '920101011234'),
        $at->modify('+4 seconds'),
        $context
    );
    $missing = $service->authenticate(
        $identity('subject-missing', 'Missing', '940101011234'),
        $at->modify('+5 seconds'),
        $context
    );
    $ambiguous = $service->authenticate(
        $identity('subject-ambiguous', 'Ambiguous', '930101011234'),
        $at->modify('+6 seconds'),
        $context
    );

    $afterUsers = (string) $pdo->query(
        "SELECT SHA2(GROUP_CONCAT(CONCAT_WS('|',u_id,u_type,avail_status,
                    password_change_required,data1,data2,data3,data4)
                 ORDER BY u_id SEPARATOR '\n'),256)
         FROM user_tbl"
    )->fetchColumn();
    $links = (int) $pdo->query('SELECT COUNT(*) FROM user_federated_identity')->fetchColumn();
    $events = (int) $pdo->query('SELECT COUNT(*) FROM federated_auth_event')->fetchColumn();
    $staffLoginCount = (int) $pdo->query(
        "SELECT login_count FROM user_federated_identity WHERE u_id='STAFF-A'"
    )->fetchColumn();
    $rawIdentityColumns = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA=DATABASE()
           AND TABLE_NAME IN ('user_federated_identity','federated_auth_event')
           AND COLUMN_NAME IN ('nric','nama','name','access_token','refresh_token','id_token')"
    )->fetchColumn();
    $reasons = $pdo->query(
        'SELECT reason_code,COUNT(*) total
         FROM federated_auth_event GROUP BY reason_code'
    )->fetchAll(PDO::FETCH_KEY_PAIR);

    $checks = [
        $staff->allowed && ($staff->user['u_id'] ?? '') === 'STAFF-A',
        $staffRepeat->allowed && $staffRepeat->identityId === $staff->identityId,
        !$subjectTakeover->allowed && $subjectTakeover->reason === 'MYDID_IDENTITY_MISMATCH',
        $student->allowed && ($student->user['u_id'] ?? '') === 'STUDENT-A',
        !$inactive->allowed && $inactive->reason === 'MYDID_USER_INACTIVE',
        !$missing->allowed && $missing->reason === 'MYDID_USER_NOT_FOUND',
        !$ambiguous->allowed && $ambiguous->reason === 'MYDID_IDENTITY_AMBIGUOUS',
        $links === 2 && $events === 7 && $staffLoginCount === 2,
        (int) ($reasons['MYDID_LOGIN_SUCCESS'] ?? 0) === 3,
        $rawIdentityColumns === 0,
        hash_equals($beforeUsers, $afterUsers),
    ];
    $failed = count(array_filter($checks, static fn(bool $passed): bool => !$passed));
    printf(
        "%s checks=%d failures=%d links=%d events=%d user_unchanged=%s raw_pii_output=0 live_schema_mutations=0\n",
        $failed === 0 ? 'PASS' : 'FAIL',
        count($checks),
        $failed,
        $links,
        $events,
        hash_equals($beforeUsers, $afterUsers) ? 'yes' : 'no'
    );
    if ($failed !== 0) {
        exit(1);
    }
} finally {
    if ($created) {
        $pdo->exec('USE information_schema');
        $pdo->exec("DROP DATABASE {$quotedDatabase}");
    }
}

echo "RESULT rehearsal_database_removed=yes auto_registration=0 profile_overwrite=0\n";
