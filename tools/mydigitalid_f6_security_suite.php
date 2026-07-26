<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

require_once dirname(__DIR__) . '/lib/config.php';

$root = dirname(__DIR__);
$pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$snapshot = static function (PDO $pdo): array {
    return [
        'users' => (int) $pdo->query('SELECT COUNT(*) FROM user_tbl')->fetchColumn(),
        'tokens' => (int) $pdo->query('SELECT COUNT(*) FROM token_tbl')->fetchColumn(),
        'f2_tables' => (int) $pdo->query(
            "SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA=DATABASE()
               AND TABLE_NAME IN ('user_federated_identity','federated_auth_event')"
        )->fetchColumn(),
        'user_schema' => (string) $pdo->query(
            "SELECT SHA2(GROUP_CONCAT(
                CONCAT_WS('|',COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,EXTRA)
                ORDER BY ORDINAL_POSITION SEPARATOR '\n'
             ),256)
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_tbl'"
        )->fetchColumn(),
    ];
};
$before = $snapshot($pdo);
$commands = [
    ['php', 'tools/mydigitalid_f0_contract.php'],
    ['php', 'tools/mydigitalid_f0_preflight.php'],
    ['php', 'tests/characterization/mydigitalid_f1_foundation.php'],
    ['php', 'tests/characterization/mydigitalid_f2_identity_protection.php'],
    ['php', 'tests/characterization/mydigitalid_f3_callback_foundation.php'],
    ['php', 'tests/characterization/mydigitalid_f4b_callback_session.php'],
    ['php', 'tests/characterization/mydigitalid_f5_ui_logout.php'],
    ['php', 'tools/mydigitalid_f1_contract.php'],
    ['php', 'tools/mydigitalid_f2_contract.php'],
    ['php', 'tools/mydigitalid_f3_contract.php'],
    ['php', 'tools/mydigitalid_f4_contract.php'],
    ['php', 'tools/mydigitalid_f4b_contract.php'],
    ['php', 'tools/mydigitalid_f5_contract.php'],
    ['php', 'tools/mydigitalid_f6_security_contract.php'],
    ['php', 'tools/mydigitalid_f2_isolated_schema_rehearsal.php'],
    ['php', 'tools/mydigitalid_f4_isolated_rehearsal.php'],
    ['php', 'tools/uc0_user_password_change_contract.php'],
    ['php', 'tools/sc0_sso_configuration_contract.php'],
    ['composer', 'validate', '--strict'],
    ['composer', 'audit', '--locked', '--no-interaction'],
];

$failures = 0;
foreach ($commands as $command) {
    $pipes = [];
    $process = proc_open(
        $command,
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        $root
    );
    if (!is_resource($process)) {
        $failures++;
        printf("FAIL %s process_start\n", implode(' ', $command));
        continue;
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($process);
    $summaryLines = preg_grep(
        '/^(RESULT|PASS checks=|No security vulnerability|\\.\\/composer\\.json is valid)/',
        preg_split('/\\R/', trim($stdout . "\n" . $stderr)) ?: []
    );
    printf(
        "%s %s%s\n",
        $exit === 0 ? 'PASS' : 'FAIL',
        implode(' ', $command),
        $summaryLines === [] ? '' : ' :: ' . implode(' | ', $summaryLines)
    );
    if ($exit !== 0) {
        $failures++;
        fwrite(STDERR, $stdout . $stderr);
    }
}

$after = $snapshot($pdo);
$mutationSafe = $before === $after;
printf(
    "%s local_mutation_guard users=%d tokens=%d f2_tables=%d\n",
    $mutationSafe ? 'PASS' : 'FAIL',
    $after['users'],
    $after['tokens'],
    $after['f2_tables']
);
if (!$mutationSafe) {
    $failures++;
}
$leftovers = (int) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.SCHEMATA
     WHERE SCHEMA_NAME LIKE 'oneid_mydid_f%_rehearsal_%'"
)->fetchColumn();
printf("%s rehearsal_cleanup leftovers=%d\n", $leftovers === 0 ? 'PASS' : 'FAIL', $leftovers);
if ($leftovers !== 0) {
    $failures++;
}

printf(
    "RESULT commands=%d failures=%d local_mutations=%d feature_activation=0\n",
    count($commands),
    $failures,
    $mutationSafe ? 0 : 1
);
exit($failures === 0 ? 0 : 1);
