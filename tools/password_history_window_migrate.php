<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}
require_once dirname(__DIR__) . '/lib/config.php';

$mode = $argv[1] ?? '--check';
if (!in_array($mode, ['--check', '--apply'], true)) {
    fwrite(STDERR, "Usage: php tools/password_history_window_migrate.php [--check|--apply]\n");
    exit(2);
}

$pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$limit = oneid_password_history_limit();
$excessSql = "SELECT COUNT(*) FROM (
    SELECT id,ROW_NUMBER() OVER(PARTITION BY user_id ORDER BY id DESC) AS history_rank
    FROM user_password_history
) ranked WHERE history_rank>:history_limit";
$query = $pdo->prepare($excessSql);
$query->execute([':history_limit'=>$limit]);
$excess = (int) $query->fetchColumn();
printf("PASSWORD_HISTORY_WINDOW recent_total=%d historical_limit=%d excess=%d mode=%s\n",oneid_password_reuse_window(),$limit,$excess,$mode);
if ($mode === '--check') {
    exit($excess === 0 ? 0 : 1);
}
if ($excess > 0) {
    $delete = $pdo->prepare("DELETE FROM user_password_history WHERE id IN (
        SELECT id FROM (
            SELECT id,ROW_NUMBER() OVER(PARTITION BY user_id ORDER BY id DESC) AS history_rank
            FROM user_password_history
        ) ranked WHERE history_rank>:history_limit
    )");
    $delete->execute([':history_limit'=>$limit]);
    if ($delete->rowCount() !== $excess) {
        fwrite(STDERR,"FAIL password history prune count mismatch\n");
        exit(1);
    }
}
echo "PASS password history window applied removed={$excess}\n";
