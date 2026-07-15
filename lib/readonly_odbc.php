<?php

/**
 * Fail-closed SQL policy for authoritative external staff/student sources.
 * Database SELECT-only grants remain mandatory; this is application defense
 * in depth and deliberately supports one plain SELECT statement only.
 */
function oneid_assert_readonly_select(string $sql): string
{
    $statement = trim($sql);
    if ($statement === '' || strlen($statement) > 100000 || str_contains($statement, "\0")) {
        throw new RuntimeException('EXTERNAL_READONLY_SQL_INVALID');
    }

    // Reject comments and statement separators so no hidden second statement
    // or comment-based token manipulation can reach the ODBC driver.
    if (str_contains($statement, ';')
        || str_contains($statement, '--')
        || str_contains($statement, '/*')
        || str_contains($statement, '*/')
        || preg_match('/(^|\s)#/', $statement) === 1
    ) {
        throw new RuntimeException('EXTERNAL_READONLY_SQL_INVALID');
    }

    if (preg_match('/\ASELECT\b/i', $statement) !== 1) {
        throw new RuntimeException('EXTERNAL_READONLY_SELECT_REQUIRED');
    }

    $forbidden = [
        'INTO', 'INSERT', 'UPDATE', 'DELETE', 'MERGE', 'UPSERT', 'REPLACE',
        'CREATE', 'ALTER', 'DROP', 'TRUNCATE', 'GRANT', 'REVOKE', 'DENY',
        'EXEC', 'EXECUTE', 'CALL', 'LOAD', 'BULK', 'OPENROWSET',
        'OPENDATASOURCE', 'WAITFOR', 'KILL', 'SHUTDOWN', 'BACKUP', 'RESTORE',
        'DBCC',
    ];
    $pattern = '/\b(?:' . implode('|', $forbidden) . ')\b/i';
    if (preg_match($pattern, $statement) === 1) {
        throw new RuntimeException('EXTERNAL_READONLY_SQL_FORBIDDEN');
    }

    return $statement;
}

function oneid_readonly_odbc_exec(mixed $connection, string $sql): mixed
{
    return odbc_exec($connection, oneid_assert_readonly_select($sql));
}

function oneid_readonly_odbc_prepare(mixed $connection, string $sql): mixed
{
    return odbc_prepare($connection, oneid_assert_readonly_select($sql));
}

