<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { exit(2); }

require_once dirname(__DIR__) . '/lib/config.php';

$mode = $argv[1] ?? '--check';
if (!in_array($mode, ['--check', '--apply'], true)) {
    fwrite(STDERR, "Usage: php tools/sync_log_source_schema.php [--check|--apply]\n");
    exit(2);
}

$pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$readiness = static function () use ($pdo): array {
    $column = $pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='ext_data_temp_header' AND column_name='source_code'");
    $index = $pdo->query("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='ext_data_temp_header' AND index_name='idx_ext_data_temp_header_source_code'");
    return [(int) $column->fetchColumn() === 1, (int) $index->fetchColumn() >= 1];
};

[$columnReady, $indexReady] = $readiness();
if ($mode === '--apply') {
    if (!$columnReady) {
        $pdo->exec("ALTER TABLE ext_data_temp_header ADD COLUMN source_code VARCHAR(64) NULL AFTER ext_head_type");
    }
    if (!$indexReady) {
        $pdo->exec("CREATE INDEX idx_ext_data_temp_header_source_code ON ext_data_temp_header (source_code, ext_head_id)");
    }
    [$columnReady, $indexReady] = $readiness();
}

$ready = $columnReady && $indexReady;
printf(
    "%s sync_log_source column=%d index=%d mode=%s\n",
    $ready ? 'PASS' : 'FAIL',
    $columnReady ? 1 : 0,
    $indexReady ? 1 : 0,
    $mode
);
exit($ready ? 0 : 1);
