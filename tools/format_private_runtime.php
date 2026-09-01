<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

$mode = $argv[1] ?? '--check';
if (!in_array($mode, ['--check', '--apply'], true)) {
    fwrite(STDERR, "Usage: php tools/format_private_runtime.php [--check|--apply] [path]\n");
    exit(2);
}

$root = dirname(__DIR__);
$path = $argv[2] ?? $root . '/.private/runtime.php';
$realPath = realpath($path);
if ($realPath === false || !is_file($realPath) || !is_readable($realPath)) {
    fwrite(STDERR, "Private runtime file is unavailable.\n");
    exit(1);
}

$originalPermissions = fileperms($realPath);
$originalOwner = fileowner($realPath);
$originalGroup = filegroup($realPath);
if ($originalPermissions === false || $originalOwner === false || $originalGroup === false) {
    throw new RuntimeException('Unable to read private runtime ownership or permissions.');
}
$originalMode = $originalPermissions & 0777;

$values = require $realPath;
if (!is_array($values)) {
    fwrite(STDERR, "Private runtime must return an array.\n");
    exit(1);
}

$sections = [
    'Aplikasi Utama' => [
        'comment' => 'Identiti deployment, URL awam, zon masa dan behavior debug asas.',
        'match' => static fn(string $key): bool => in_array($key, [
            'ONEID_APP_URL','ONEID_ENVIRONMENT','ONEID_APP_DEBUG','ONEID_TIMEZONE','ONEID_DB_CHARSET',
        ], true),
    ],
    'Database OneID' => [
        'comment' => 'Sambungan database utama. Nilai credential mesti kekal dalam fail private ini sahaja.',
        'match' => static fn(string $key): bool => str_starts_with($key, 'ONEID_DB_')
            && $key !== 'ONEID_DB_CHARSET',
    ],
    'SSO dan Integration API' => [
        'comment' => 'Endpoint IDP/dashboard, compatibility sample dan kawalan authentication/rate limit API.',
        'match' => static fn(string $key): bool => str_starts_with($key, 'ONEID_SSO_')
            || str_starts_with($key, 'ONEID_API_'),
    ],
    'Session dan Keselamatan Legacy' => [
        'comment' => 'Feature flag popup session user dan tarikh akhir compatibility password legacy.',
        'match' => static fn(string $key): bool => in_array($key, [
            'ONEID_USER_SESSION_WARNING_ENABLED','ONEID_LEGACY_MD5_DEADLINE',
        ], true),
    ],
    'Locale dan Multibahasa' => [
        'comment' => 'Aktivasi locale serta approval, backup, window dan baseline migration ML1.',
        'match' => static fn(string $key): bool => str_starts_with($key, 'ONEID_ML')
            || str_contains($key, 'LOCALE'),
    ],
    'SMTP dan E-mel' => [
        'comment' => 'Mail transport OneID. Password SMTP ialah rahsia dan tidak boleh dicatat dalam log.',
        'match' => static fn(string $key): bool => str_starts_with($key, 'ONEID_SMTP_'),
    ],
    'MyDigital ID' => [
        'comment' => 'OIDC, PKCE, callback/logout, key identity protection dan gate migration MyDigital ID.',
        'match' => static fn(string $key): bool => str_starts_with($key, 'ONEID_MYDID_'),
    ],
    'Administrator 2FA dan TOTP' => [
        'comment' => 'Issuer/keyring TOTP, step-up limits dan polisi akses sensitif Administrator.',
        'match' => static fn(string $key): bool => str_starts_with($key, 'ONEID_TOTP_')
            || str_starts_with($key, 'ONEID_STEP_UP_')
            || str_starts_with($key, 'ONEID_ACTIVE_SESSION_'),
    ],
    'User Login MFA' => [
        'comment' => 'Mode, faktor, TTL, rate limit dan gate deployment MFA pengguna.',
        'match' => static fn(string $key): bool => str_starts_with($key, 'ONEID_USER_MFA_'),
    ],
    'Sync Orchestrator' => [
        'comment' => 'Gate preview/apply, had perubahan, expected count, plan hash dan identiti scheduler sync.',
        'match' => static fn(string $key): bool => str_starts_with($key, 'ONEID_SYNC_')
            || str_starts_with($key, 'ONEID_REHEARSAL_'),
    ],
    'Sumber Data Luaran' => [
        'comment' => 'Credential ODBC bagi Staff, Student, SKP dan IDMS. Setiap sumber diasingkan.',
        'match' => static fn(string $key): bool => str_starts_with($key, 'ONEID_STAFF_')
            || str_starts_with($key, 'ONEID_STUDENT_')
            || str_starts_with($key, 'ONEID_SKP_')
            || str_starts_with($key, 'ONEID_IDMS_'),
    ],
    'ODL' => [
        'comment' => 'Sambungan ODL, baseline shadow, pilot, full sync dan operational Preview/Apply gates.',
        'match' => static fn(string $key): bool => str_starts_with($key, 'ONEID_ODL_'),
    ],
    'Diagnostik Terkawal' => [
        'comment' => 'Credential dan token diagnostik. Gunakan hanya pada tooling yang diluluskan.',
        'match' => static fn(string $key): bool => str_starts_with($key, 'ONEID_DIAG'),
    ],
];

$assigned = [];
$ordered = [];
foreach ($sections as $title => $definition) {
    $keys = array_values(array_filter(
        array_keys($values),
        static fn(string $key): bool => !isset($assigned[$key]) && $definition['match']($key)
    ));
    natcasesort($keys);
    $keys = array_values($keys);
    if ($keys === []) { continue; }
    foreach ($keys as $key) { $assigned[$key] = true; }
    $ordered[] = [$title, $definition['comment'], $keys];
}

$remaining = array_values(array_diff(array_keys($values), array_keys($assigned)));
natcasesort($remaining);
if ($remaining !== []) {
    $ordered[] = [
        'Lain-lain / Belum Diklasifikasikan',
        'Key sah yang belum mempunyai kumpulan khusus. Semak kategori apabila menambah konfigurasi baharu.',
        array_values($remaining),
    ];
}

$lines = [
    '<?php',
    '',
    'declare(strict_types=1);',
    '',
    '/**',
    ' * Konfigurasi private OneID untuk environment ini.',
    ' *',
    ' * AMARAN:',
    ' * - Jangan commit fail ini ke Git.',
    ' * - Jangan salin nilai credential/token ke tiket, chat atau log.',
    ' * - Ubah feature flag secara terkawal dan reload PHP-FPM selepas validasi.',
    ' */',
    'return [',
];

foreach ($ordered as [$title, $comment, $keys]) {
    $lines[] = '';
    $lines[] = '    // -------------------------------------------------------------------------';
    $lines[] = '    // ' . $title;
    $lines[] = '    // ' . $comment;
    $lines[] = '    // -------------------------------------------------------------------------';
    foreach ($keys as $key) {
        $export = var_export($values[$key], true);
        $export = str_replace("\n", "\n    ", $export);
        $lines[] = '    ' . var_export($key, true) . ' => ' . $export . ',';
    }
}
$lines[] = '];';
$lines[] = '';
$rendered = implode("\n", $lines);

$currentValues = $values;
$renderedTemp = tempnam(dirname($realPath), '.runtime-format-check-');
if ($renderedTemp === false) { throw new RuntimeException('Unable to create validation file.'); }
try {
    file_put_contents($renderedTemp, $rendered, LOCK_EX);
    chmod($renderedTemp, 0600);
    $validatedValues = require $renderedTemp;
    $expectedValues = $currentValues;
    if (is_array($validatedValues)) { ksort($validatedValues); }
    ksort($expectedValues);
    if (!is_array($validatedValues) || $validatedValues !== $expectedValues) {
        throw new RuntimeException('Formatted runtime does not preserve all keys and values.');
    }
} finally {
    @unlink($renderedTemp);
}

$alreadyFormatted = file_get_contents($realPath) === $rendered;
if ($mode === '--check') {
    echo ($alreadyFormatted ? 'PASS' : 'NEEDS_FORMAT')
        . ' private_runtime keys=' . count($values)
        . ' sections=' . count($ordered) . " values_preserved=1\n";
    exit($alreadyFormatted ? 0 : 1);
}

$backup = $realPath . '.backup-before-format-' . date('Ymd-His');
if (!copy($realPath, $backup)) { throw new RuntimeException('Unable to create runtime backup.'); }
if ((fileowner($backup) !== $originalOwner && !chown($backup, $originalOwner))
    || (filegroup($backup) !== $originalGroup && !chgrp($backup, $originalGroup))
    || !chmod($backup, $originalMode)
) {
    throw new RuntimeException('Unable to preserve runtime backup ownership or permissions.');
}
$temporary = $realPath . '.formatting-' . bin2hex(random_bytes(6));
if (file_put_contents($temporary, $rendered, LOCK_EX) === false) {
    throw new RuntimeException('Unable to write formatted runtime.');
}
if ((fileowner($temporary) !== $originalOwner && !chown($temporary, $originalOwner))
    || (filegroup($temporary) !== $originalGroup && !chgrp($temporary, $originalGroup))
    || !chmod($temporary, $originalMode)
) {
    @unlink($temporary);
    throw new RuntimeException('Unable to preserve private runtime ownership or permissions.');
}
if (!rename($temporary, $realPath)) {
    @unlink($temporary);
    throw new RuntimeException('Unable to activate formatted runtime.');
}

echo 'PASS private_runtime_formatted keys=' . count($values)
    . ' sections=' . count($ordered)
    . ' values_preserved=1 backup=' . basename($backup) . "\n";
