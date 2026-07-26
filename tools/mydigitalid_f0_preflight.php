<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

require_once dirname(__DIR__) . '/lib/config.php';

const MYDID_F0_ISSUER = 'https://sso.digital-id.my/realms/upnm';
const MYDID_F0_DISCOVERY = MYDID_F0_ISSUER . '/.well-known/openid-configuration';
const MYDID_F0_PILOT_STAFF_REFERENCE = '0530-09';
const MYDID_F0_UAT_URL = 'https://oneid-uat.upnm.edu.my/';

$checks = 0;
$failures = 0;
$check = static function (bool $passed, string $description, string $detail = '') use (&$checks, &$failures): void {
    $checks++;
    if (!$passed) {
        $failures++;
    }
    printf(
        "%s %-42s%s\n",
        $passed ? 'PASS' : 'FAIL',
        $description,
        $detail === '' ? '' : ' ' . $detail
    );
};

$resolved = gethostbynamel('sso.digital-id.my');
$check(
    is_array($resolved) && $resolved !== [],
    'provider DNS resolves',
    'addresses=' . (is_array($resolved) ? count(array_unique($resolved)) : 0)
);

$curl = curl_init(MYDID_F0_DISCOVERY);
if ($curl === false) {
    $check(false, 'OIDC discovery is reachable');
    $discoveryBody = '';
    $httpCode = 0;
    $tlsVerifyResult = -1;
} else {
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);
    $response = curl_exec($curl);
    $discoveryBody = is_string($response) ? $response : '';
    $httpCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $tlsVerifyResult = (int) curl_getinfo($curl, CURLINFO_SSL_VERIFYRESULT);
    curl_close($curl);
    $check(
        $httpCode === 200 && $tlsVerifyResult === 0 && $discoveryBody !== '',
        'OIDC discovery is reachable with valid TLS',
        "http={$httpCode} tls_verify={$tlsVerifyResult}"
    );
}

try {
    $discovery = json_decode($discoveryBody, true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException) {
    $discovery = [];
}

$expected = [
    'issuer' => MYDID_F0_ISSUER,
    'authorization_endpoint' => MYDID_F0_ISSUER . '/protocol/openid-connect/auth',
    'token_endpoint' => MYDID_F0_ISSUER . '/protocol/openid-connect/token',
    'userinfo_endpoint' => MYDID_F0_ISSUER . '/protocol/openid-connect/userinfo',
    'jwks_uri' => MYDID_F0_ISSUER . '/protocol/openid-connect/certs',
    'end_session_endpoint' => MYDID_F0_ISSUER . '/protocol/openid-connect/logout',
];
foreach ($expected as $field => $value) {
    $check(
        isset($discovery[$field]) && hash_equals($value, (string) $discovery[$field]),
        'discovery ' . $field . ' matches baseline'
    );
}
$check(
    in_array('code', $discovery['response_types_supported'] ?? [], true)
        && in_array('authorization_code', $discovery['grant_types_supported'] ?? [], true),
    'provider advertises Authorization Code Flow'
);
$check(
    in_array('S256', $discovery['code_challenge_methods_supported'] ?? [], true),
    'provider advertises PKCE S256'
);

$uatAddresses = gethostbynamel('oneid-uat.upnm.edu.my');
$check(
    is_array($uatAddresses) && $uatAddresses !== [],
    'UAT domain resolves from this host',
    'addresses=' . (is_array($uatAddresses) ? count(array_unique($uatAddresses)) : 0)
);

$uatCurl = curl_init(MYDID_F0_UAT_URL);
if ($uatCurl === false) {
    $check(false, 'UAT HTTPS endpoint is reachable');
} else {
    curl_setopt_array($uatCurl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
    ]);
    curl_exec($uatCurl);
    $uatHttpCode = (int) curl_getinfo($uatCurl, CURLINFO_RESPONSE_CODE);
    $uatTlsVerifyResult = (int) curl_getinfo($uatCurl, CURLINFO_SSL_VERIFYRESULT);
    curl_close($uatCurl);
    $check(
        $uatHttpCode >= 200 && $uatHttpCode < 400 && $uatTlsVerifyResult === 0,
        'UAT HTTPS endpoint is reachable with valid TLS',
        "http={$uatHttpCode} tls_verify={$uatTlsVerifyResult}"
    );
}

$timeOutput = [];
$timeCode = 1;
exec('timedatectl show -p NTPSynchronized -p Timezone 2>/dev/null', $timeOutput, $timeCode);
$timeStatus = implode("\n", $timeOutput);
$check(
    $timeCode === 0
        && str_contains($timeStatus, 'NTPSynchronized=yes')
        && str_contains($timeStatus, 'Timezone=Asia/Kuala_Lumpur'),
    'server clock is NTP-synchronized in expected timezone'
);

$pdo = new PDO(
    DB_DSN,
    DB_USERNAME,
    DB_PASSWORD,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$check(true, 'OneID database read-only connection succeeds');

$pilotStatement = $pdo->prepare(
    'SELECT u_id,avail_status,data4
     FROM user_tbl
     WHERE data3=:staff_reference'
);
$pilotStatement->execute([':staff_reference' => MYDID_F0_PILOT_STAFF_REFERENCE]);
$pilotRows = $pilotStatement->fetchAll(PDO::FETCH_ASSOC);
$pilot = $pilotRows[0] ?? [];
$pilotNric = preg_replace('/[-\s]+/', '', (string) ($pilot['data4'] ?? '')) ?? '';

$check(
    count($pilotRows) === 1,
    'pilot staff reference resolves exactly once',
    'rows=' . count($pilotRows)
);
$check(
    count($pilotRows) === 1 && (int) ($pilot['avail_status'] ?? 0) === 1,
    'pilot OneID account is active'
);
$check(
    preg_match('/^\d{12}$/D', $pilotNric) === 1,
    'pilot staff NRIC has normalized 12-digit shape'
);

$pilotMatches = 0;
if ($pilotNric !== '') {
    $pilotMatchStatement = $pdo->prepare(
        'SELECT COUNT(*)
         FROM user_tbl
         WHERE REPLACE(REPLACE(TRIM(data4), "-", ""), " ", "")=:nric
            OR REPLACE(REPLACE(TRIM(data2), "-", ""), " ", "")=:nric'
    );
    $pilotMatchStatement->execute([':nric' => $pilotNric]);
    $pilotMatches = (int) $pilotMatchStatement->fetchColumn();
}
$check(
    $pilotMatches === 1,
    'pilot NRIC has exactly one cross-field account match',
    "matches={$pilotMatches}"
);

$activeIdentitySql = <<<'SQL'
WITH identities AS (
    SELECT 'staff' population,
           REPLACE(REPLACE(TRIM(data4), '-', ''), ' ', '') identity_value
    FROM user_tbl
    WHERE avail_status=1
      AND TRIM(COALESCE(data3, ''))<>''
      AND REPLACE(REPLACE(TRIM(COALESCE(data4, '')), '-', ''), ' ', '')
          REGEXP '^[0-9]{12}$'
    UNION ALL
    SELECT 'student' population,
           REPLACE(REPLACE(TRIM(data2), '-', ''), ' ', '') identity_value
    FROM user_tbl
    WHERE avail_status=1
      AND TRIM(COALESCE(data3, ''))=''
      AND REPLACE(REPLACE(TRIM(COALESCE(data2, '')), '-', ''), ' ', '')
          REGEXP '^[0-9]{12}$'
)
SQL;

$activeSummary = $pdo->query(
    $activeIdentitySql
    . ' SELECT COUNT(*) identity_rows,COUNT(DISTINCT identity_value) unique_identities
        FROM identities'
)->fetch(PDO::FETCH_ASSOC);
$activeRows = (int) ($activeSummary['identity_rows'] ?? 0);
$activeUnique = (int) ($activeSummary['unique_identities'] ?? 0);
$check(
    $activeRows > 0 && $activeRows === $activeUnique,
    'active eligible NRIC population has no collision',
    "rows={$activeRows} unique={$activeUnique}"
);

$allIdentitySummary = $pdo->query(
    <<<'SQL'
SELECT COUNT(*) identity_groups,
       SUM(copies>1) duplicate_groups,
       COALESCE(SUM(CASE WHEN copies>1 THEN copies ELSE 0 END),0) rows_in_duplicate_groups
FROM (
    SELECT identity_value,COUNT(*) copies
    FROM (
        SELECT REPLACE(REPLACE(TRIM(data4), '-', ''), ' ', '') identity_value
        FROM user_tbl
        WHERE TRIM(COALESCE(data3, ''))<>''
          AND REPLACE(REPLACE(TRIM(COALESCE(data4, '')), '-', ''), ' ', '')
              REGEXP '^[0-9]{12}$'
        UNION ALL
        SELECT REPLACE(REPLACE(TRIM(data2), '-', ''), ' ', '') identity_value
        FROM user_tbl
        WHERE TRIM(COALESCE(data3, ''))=''
          AND REPLACE(REPLACE(TRIM(COALESCE(data2, '')), '-', ''), ' ', '')
              REGEXP '^[0-9]{12}$'
    ) identity_rows
    GROUP BY identity_value
) identity_groups
SQL
)->fetch(PDO::FETCH_ASSOC);
printf(
    "INFO historical_identity_groups=%d historical_duplicate_groups=%d historical_rows_in_duplicate_groups=%d\n",
    (int) ($allIdentitySummary['identity_groups'] ?? 0),
    (int) ($allIdentitySummary['duplicate_groups'] ?? 0),
    (int) ($allIdentitySummary['rows_in_duplicate_groups'] ?? 0)
);

$pdo = null;
$pilotNric = '';
$pilot = [];
$pilotRows = [];

printf("RESULT checks=%d failures=%d mutation_statements=0 raw_nric_output=0\n", $checks, $failures);
exit($failures === 0 ? 0 : 1);
