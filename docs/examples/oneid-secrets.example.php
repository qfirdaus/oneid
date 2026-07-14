<?php

// Template sahaja. Salin ke lokasi di luar document root dan isi melalui
// saluran pengurusan secrets yang diluluskan. Jangan commit nilai sebenar.
return [
    'ONEID_DB_DSN' => '',
    'ONEID_DB_USERNAME' => '',
    'ONEID_DB_PASSWORD' => '',
    'ONEID_SMTP_USERNAME' => '',
    'ONEID_SMTP_PASSWORD' => '',
    'ONEID_STAFF_ODBC_DSN' => '',
    'ONEID_STAFF_ODBC_USERNAME' => '',
    'ONEID_STAFF_ODBC_PASSWORD' => '',
    'ONEID_STUDENT_SYNC_ODBC_DSN' => '',
    'ONEID_STUDENT_SYNC_ODBC_USERNAME' => '',
    'ONEID_STUDENT_SYNC_ODBC_PASSWORD' => '',
    'ONEID_STUDENT_LOOKUP_ODBC_DSN' => '',
    'ONEID_STUDENT_LOOKUP_ODBC_USERNAME' => '',
    'ONEID_STUDENT_LOOKUP_ODBC_PASSWORD' => '',
    'ONEID_SKP_ODBC_DSN' => '',
    'ONEID_SKP_ODBC_USERNAME' => '',
    'ONEID_SKP_ODBC_PASSWORD' => '',
    'ONEID_IDMS_ODBC_CONNECTION' => '',
    'ONEID_IDMS_ODBC_USERNAME' => '',
    'ONEID_IDMS_ODBC_PASSWORD' => '',
    'ONEID_DIAG_AGENT_TOKEN' => '',
    'ONEID_DIAG_MYSQL_DSN' => '',
    'ONEID_DIAG_MYSQL_USERNAME' => '',
    'ONEID_DIAG_MYSQL_PASSWORD' => '',
    'ONEID_DIAG_SYBASE_USERNAME' => '',
    'ONEID_DIAG_SYBASE_PASSWORD' => '',
    'ONEID_DIAG_SYBASE_ODBC_DSN' => '',
    'ONEID_DIAGNOSTIC_TOKEN' => '',
    // Fasa 6A: kekalkan "observe" sehingga semua consumer bagi sesuatu
    // endpoint selesai onboarding dan lulus UAT.
    'ONEID_API_AUTH_MODE' => 'observe',
    'ONEID_API_RATE_LIMIT_PER_MINUTE' => '600',
    // JSON object keyed by client ID. Simpan SHA-256 secret, bukan secret mentah.
    // Contoh struktur didokumenkan dalam FASA_6A_PELAKSANAAN_DAN_ROLLBACK.md.
    'ONEID_API_CLIENTS_JSON' => '{}',
];
