<?php
declare(strict_types=1);
// Additive staging migration. Observer never enabled by this command.
if (PHP_SAPI !== 'cli' || !in_array($argv[1] ?? '', ['--check','--apply'], true)) {
    fwrite(STDERR, "Usage: php migrate-staging.php --check|--apply\n"); exit(2);
}
$root = dirname(__DIR__, 2);
if (realpath($root) !== '/var/www/oneid-uat') exit(2);
require_once $root . '/app/Auth/MobileOidc/bootstrap.php';
use OneId\App\Auth\MobileOidc\LifecycleSchema;
$pdo = null;
$locked = false;
try {
    $runtime = require $root . '/.private/runtime.php';
    $hosted = require $root . '/.private/mobile-oidc-hosted.php';
    if (($runtime['ONEID_ENVIRONMENT'] ?? '') !== 'staging' || ($hosted['enabled'] ?? null) !== false) {
        throw new RuntimeException('Staging/OFF guard failed');
    }
    $pdo = new PDO($runtime['ONEID_DB_DSN'], $runtime['ONEID_DB_USERNAME'], $runtime['ONEID_DB_PASSWORD'],
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT=>5, PDO::ATTR_EMULATE_PREPARES=>false]);
    $pdo->exec('SET SESSION lock_wait_timeout=5');
    $server = $pdo->query('SELECT DATABASE() AS db,@@hostname AS host,@@version AS version')->fetch(PDO::FETCH_ASSOC);
    // Explicit target confirmed by the staging owner on 29 September 2026.
    if ($server['db'] !== 'oneiddb' || $server['host'] !== 'mysql8-DEV' || !str_starts_with($server['version'], '8.0.')) {
        throw new RuntimeException('Reviewed database target changed');
    }
    $required = [
        'user_tbl'=>['u_id','avail_status','password_change_required','u_type','u_category','data2','data3','data4','data5','u_password'],
        'user_mfa_factors'=>['u_id','factor_type','factor_status','encrypted_secret','secret_nonce','key_version'],
        'user_external_identity'=>['u_id','source_code','source_active'],
        'user_login_mfa_exemptions'=>['u_id'], 'user_login_mfa_pilot_users'=>['u_id'],
        'user_login_mfa_policy'=>[], 'user_login_mfa_category_policy'=>[],
        'user_mfa_policy_change_requests'=>[], 'external_source'=>[],
    ];
    $schema = [];
    foreach ($required as $table=>$columns) {
        $actual = $pdo->query('SHOW COLUMNS FROM `' . $table . '`')->fetchAll(PDO::FETCH_COLUMN);
        if (array_diff($columns, $actual)) throw new RuntimeException('Missing source columns: ' . $table);
        $schema[$table] = $pdo->query('SHOW CREATE TABLE `' . $table . '`')->fetch(PDO::FETCH_NUM)[1];
    }
    $existing = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME LIKE 'mobile\\_oidc\\_%'")->fetchAll(PDO::FETCH_COLUMN);
    $triggers = $pdo->query('SELECT TRIGGER_NAME,EVENT_OBJECT_TABLE,ACTION_STATEMENT FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE()')->fetchAll(PDO::FETCH_ASSOC);
    if ($existing) throw new RuntimeException('Mobile tables already exist; review previous migration before proceeding');
    foreach ($triggers as $trigger) if (str_starts_with($trigger['TRIGGER_NAME'], 'mobile_oidc_')) throw new RuntimeException('Existing mobile trigger');
    $sql = [
        'CREATE TABLE mobile_oidc_mutex(singleton_id TINYINT UNSIGNED NOT NULL PRIMARY KEY) ENGINE=InnoDB',
        'INSERT INTO mobile_oidc_mutex VALUES(1)',
        'CREATE TABLE mobile_oidc_records(record_key VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,record_json JSON NOT NULL,updated_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6)) ENGINE=InnoDB',
        ...LifecycleSchema::statements(),
    ];
    echo "Preflight OK: confirmed staging target, source columns present, no mobile schema, hosted OFF.\n";
    if ($argv[1] === '--check') exit(0);
    // Provider installation must have completed before OneID schema changes.
    $context = stream_context_create(['http'=>['timeout'=>3,'ignore_errors'=>true]]);
    $health = @file_get_contents('http://127.0.0.1:24145/health/ready', false, $context);
    if ($health === false || !preg_match('~^HTTP/\S+ 200\b~', $http_response_header[0] ?? '')) throw new RuntimeException('Private provider not ready');
    $locked = (int)$pdo->query("SELECT GET_LOCK('oneid_mobile_staging_migration',0)")->fetchColumn() === 1;
    if (!$locked) throw new RuntimeException('Migration already running');
    umask(0077);
    $directory = $root . '/.private/mobile-schema-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4));
    if (!mkdir($directory, 0700)) throw new RuntimeException('Cannot create migration evidence');
    // Source data is not modified. Preserve source DDL/trigger metadata and exact plan.
    if (file_put_contents($directory . '/before.json', json_encode(['server'=>$server,'source_schema'=>$schema,'triggers'=>$triggers], JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)) === false
        || file_put_contents($directory . '/plan.json', json_encode($sql, JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)) === false) throw new RuntimeException('Cannot persist migration evidence');
    echo 'Schema metadata backup: ' . $directory . "\n";
    foreach ($sql as $index=>$statement) {
        $pdo->exec($statement);
        file_put_contents($directory . '/progress.log', 'completed_statement=' . $index . "\n", FILE_APPEND);
    }
    $control = $pdo->query('SELECT observer_enabled FROM mobile_oidc_control WHERE singleton_id=1')->fetchColumn();
    $names = $pdo->query("SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND TRIGGER_NAME LIKE 'mobile\\_oidc\\_%'")->fetchAll(PDO::FETCH_COLUMN);
    if ((int)$control !== 0 || array_diff(LifecycleSchema::triggerNames(), $names)) throw new RuntimeException('Post-migration verification failed');
    file_put_contents($directory . '/SUCCESS.json', json_encode(['observer_enabled'=>false,'triggers'=>count($names),'source_data_modified'=>false], JSON_PRETTY_PRINT));
    echo 'SUCCESS: 4 mobile tables and ' . count($names) . " triggers installed; observer OFF; no source identity data changed.\n";
} catch (Throwable $error) {
    // DDL auto-commits: never claim transactional rollback or drop partially-created data.
    fwrite(STDERR, 'STOP: ' . get_class($error) . '. Review private migration progress; do not rerun blindly. No observer activation requested.' . "\n");
    exit(1);
} finally {
    if ($locked && $pdo instanceof PDO) $pdo->query("SELECT RELEASE_LOCK('oneid_mobile_staging_migration')");
}
