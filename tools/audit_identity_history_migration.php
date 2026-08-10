<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

$root = dirname(__DIR__);
require_once $root . '/lib/config.php';
set_exception_handler(static function (Throwable $exception): void {
    fwrite(STDERR, 'FAIL ' . get_class($exception) . ': ' . $exception->getMessage() . PHP_EOL);
    exit(1);
});

$apply = in_array('--apply', $argv, true);
$pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$icPattern = '/(?<!\d)\d{6}-?\d{2}-?\d{4}(?!\d)/';
$identityMap = [];
foreach ($pdo->query('SELECT u_id,data2,data3,data4 FROM user_tbl') as $user) {
    $staff = trim((string) ($user['data3'] ?? ''));
    $matric = trim((string) ($user['data4'] ?? ''));
    $public = preg_match('/\A\d{4}-\d{2}\z/', $staff) === 1
        ? $staff
        : ((preg_match('/\A(?!\d{12}\z)[A-Za-z0-9._\/-]{1,30}\z/', $matric) === 1) ? $matric : '[ID_REDACTED]');
    foreach (['u_id', 'data2', 'data4'] as $source) {
        $candidate = str_replace('-', '', trim((string) ($user[$source] ?? '')));
        if (preg_match('/\A\d{12}\z/', $candidate) === 1) {
            $identityMap[$candidate] = $public;
        }
    }
}
$resolve = static fn(string $value): string => $identityMap[str_replace('-', '', trim($value))] ?? '[ID_REDACTED]';
$sanitize = static function (string $detail) use ($icPattern, $resolve): string {
    return preg_replace_callback($icPattern, static fn(array $match): string => $resolve($match[0]), $detail) ?? $detail;
};

// Only public audit columns without user_tbl foreign keys belong here.
$targets = [
    ['configuration_change_history', 'history_id', 'actor_id'],
    ['login_banner_history', 'history_id', 'actor_id'],
    ['metadata_translation_history', 'history_id', 'actor_id'],
    ['login_banner', 'banner_id', 'created_by'],
    ['login_banner', 'banner_id', 'updated_by'],
    ['login_banner_translation', ['banner_id', 'locale'], 'created_by'],
    ['login_banner_translation', ['banner_id', 'locale'], 'updated_by'],
    ['login_banner_asset', 'asset_id', 'created_by'],
    ['login_banner_locale_asset', ['banner_id', 'environment', 'locale'], 'mapped_by'],
    ['sp_app_translation', 'translation_id', 'created_by'],
    ['sp_app_translation', 'translation_id', 'updated_by'],
    ['sp_group_translation', 'translation_id', 'created_by'],
    ['sp_group_translation', 'translation_id', 'updated_by'],
    ['sp_app_asset', ['sp_id', 'environment'], 'updated_by'],
];

$tableExists = static function (string $table) use ($pdo): bool {
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?'
    );
    $statement->execute([$table]);
    return (int) $statement->fetchColumn() === 1;
};
$columnExists = static function (string $table, string $column) use ($pdo): bool {
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?'
    );
    $statement->execute([$table, $column]);
    return (int) $statement->fetchColumn() === 1;
};
$quote = static fn(string $identifier): string => '`' . str_replace('`', '``', $identifier) . '`';

$changes = [];
if ($tableExists('syslog')) {
    $statement = $pdo->query("SELECT id,log_detail FROM syslog WHERE log_detail REGEXP '[0-9]{6}-?[0-9]{2}-?[0-9]{4}'");
    foreach ($statement as $row) {
        $before = (string) $row['log_detail'];
        $after = $sanitize($before);
        if ($after !== $before) {
            $changes[] = ['table' => 'syslog', 'keys' => ['id' => (int) $row['id']], 'column' => 'log_detail', 'before' => $before, 'after' => $after];
        }
    }
}

foreach ($targets as [$table, $keys, $column]) {
    $keys = (array) $keys;
    if (!$tableExists($table) || !$columnExists($table, $column)) {
        continue;
    }
    $selectColumns = implode(',', array_map($quote, array_merge($keys, [$column])));
    foreach ($pdo->query('SELECT ' . $selectColumns . ' FROM ' . $quote($table)) as $row) {
        $before = trim((string) ($row[$column] ?? ''));
        if ($before === '' || preg_match($icPattern, $before) !== 1) {
            continue;
        }
        $keyValues = [];
        foreach ($keys as $key) {
            $keyValues[$key] = $row[$key];
        }
        $changes[] = [
            'table' => $table,
            'keys' => $keyValues,
            'column' => $column,
            'before' => $before,
            'after' => $resolve($before),
        ];
    }
}

$counts = [];
foreach ($changes as $change) {
    $label = $change['table'] . '.' . $change['column'];
    $counts[$label] = ($counts[$label] ?? 0) + 1;
}
ksort($counts);
printf("MODE %s\n", $apply ? 'APPLY' : 'PREVIEW');
foreach ($counts as $label => $count) {
    printf("CHANGE %-55s %d\n", $label, $count);
}
printf("TOTAL %d\n", count($changes));

if (!$apply || $changes === []) {
    exit(0);
}

$backupPath = $root . '/.private/audit-identity-migration-' . date('Ymd-His') . '.json';
$backup = json_encode([
    'created_at' => date(DATE_ATOM),
    'database' => (string) $pdo->query('SELECT DATABASE()')->fetchColumn(),
    'changes' => $changes,
], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if (file_put_contents($backupPath, $backup, LOCK_EX) === false || !chmod($backupPath, 0600)) {
    throw new RuntimeException('Unable to create protected migration backup.');
}

try {
    $syslogChanges = array_values(array_filter(
        $changes,
        static fn(array $change): bool => $change['table'] === 'syslog' && $change['column'] === 'log_detail'
    ));
    foreach (array_chunk($syslogChanges, 100) as $chunk) {
        $pdo->beginTransaction();
        $case = [];
        $ids = [];
        $parameters = [];
        foreach ($chunk as $index => $change) {
            $idPlaceholder = ':id_' . $index;
            $wherePlaceholder = ':where_id_' . $index;
            $valuePlaceholder = ':value_' . $index;
            $case[] = 'WHEN ' . $idPlaceholder . ' THEN ' . $valuePlaceholder;
            $ids[] = $wherePlaceholder;
            $parameters[$idPlaceholder] = $change['keys']['id'];
            $parameters[$wherePlaceholder] = $change['keys']['id'];
            $parameters[$valuePlaceholder] = $change['after'];
        }
        $update = $pdo->prepare(
            'UPDATE syslog SET log_detail=CASE id ' . implode(' ', $case)
            . ' END WHERE id IN (' . implode(',', $ids) . ')'
        );
        $update->execute($parameters);
        if ($update->rowCount() !== count($chunk)) {
            throw new RuntimeException('Concurrent change detected for syslog.log_detail');
        }
        $pdo->commit();
    }
    $pdo->beginTransaction();
    foreach ($changes as $change) {
        if ($change['table'] === 'syslog' && $change['column'] === 'log_detail') {
            continue;
        }
        $where = [];
        $parameters = [':after' => $change['after'], ':before' => $change['before']];
        foreach ($change['keys'] as $key => $value) {
            $placeholder = ':key_' . $key;
            $where[] = $quote($key) . '=' . $placeholder;
            $parameters[$placeholder] = $value;
        }
        $sql = 'UPDATE ' . $quote($change['table'])
            . ' SET ' . $quote($change['column']) . '=:after WHERE '
            . implode(' AND ', $where) . ' AND ' . $quote($change['column']) . '=:before';
        $update = $pdo->prepare($sql);
        $update->execute($parameters);
        if ($update->rowCount() !== 1) {
            throw new RuntimeException('Concurrent change detected for ' . $change['table'] . '.' . $change['column']);
        }
    }
    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $exception;
}

printf("APPLIED %d\n", count($changes));
printf("BACKUP %s mode=0600\n", $backupPath);
