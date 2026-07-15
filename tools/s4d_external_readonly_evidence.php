<?php

/**
 * S4D external source read evidence.
 *
 * This tool deliberately contains only SELECT statements. It proves that the
 * runtime credentials can read the two authoritative production views; it
 * does not claim that write grants are absent. Grant evidence remains a DBA
 * responsibility when metadata is not visible to the runtime login.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(2);
}

require_once dirname(__DIR__) . '/lib/secrets.php';
require_once dirname(__DIR__) . '/lib/readonly_odbc.php';

$targets = [
    [
        'label' => 'staff/ehrmdb',
        'dsn' => 'ONEID_STAFF_ODBC_DSN',
        'username' => 'ONEID_STAFF_ODBC_USERNAME',
        'password' => 'ONEID_STAFF_ODBC_PASSWORD',
        'query' => 'SELECT COUNT(*) AS row_count FROM ehrmdb.dbo.SSO_Staf_Aktif',
    ],
    [
        'label' => 'student/asisdb',
        'dsn' => 'ONEID_STUDENT_SYNC_ODBC_DSN',
        'username' => 'ONEID_STUDENT_SYNC_ODBC_USERNAME',
        'password' => 'ONEID_STUDENT_SYNC_ODBC_PASSWORD',
        'query' => 'SELECT COUNT(*) AS row_count FROM v210_sso_student_aktif',
    ],
];

$failed = 0;
$total = 0;
foreach ($targets as $target) {
    $connection = @odbc_connect(
        oneid_secret($target['dsn']),
        oneid_secret($target['username']),
        oneid_secret($target['password'])
    );
    if ($connection === false) {
        $failed++;
        printf("FAIL %-20s SELECT-only connectivity failed\n", $target['label']);
        continue;
    }

    $result = @oneid_readonly_odbc_exec($connection, $target['query']);
    $row = $result === false ? false : odbc_fetch_array($result);
    $count = is_array($row) ? (int) array_values($row)[0] : -1;
    odbc_close($connection);

    $ok = $count >= 0;
    if (!$ok) {
        $failed++;
    } else {
        $total += $count;
    }
    printf(
        "%s %-20s statement=SELECT rows=%s mutation_statements=0\n",
        $ok ? 'PASS' : 'FAIL',
        $target['label'],
        $ok ? (string) $count : '-'
    );
}

printf("EVIDENCE total_external_rows=%d grant_exclusivity=DBA_EVIDENCE_REQUIRED\n", $total);
printf("RESULT checks=%d failed=%d\n", count($targets), $failed);
exit($failed === 0 ? 0 : 1);
