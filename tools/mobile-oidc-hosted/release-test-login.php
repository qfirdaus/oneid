<?php
declare(strict_types=1);
// Release only the approved staging test identifier at the observed tester address.
if (PHP_SAPI !== 'cli' || posix_geteuid() !== 0 || ($argv[1] ?? '') !== '--apply') {
    fwrite(STDERR, "Usage: sudo php tools/mobile-oidc-hosted/release-test-login.php --apply\n"); exit(2);
}
$root = dirname(__DIR__, 2);
if (realpath($root) !== '/var/www/oneid-uat') exit(2);
require $root.'/bootstrap/runtime_file.php';
require $root.'/config/runtime.php';
require $root.'/lib/secrets.php';
try {
    $config = require $root.'/.private/mobile-oidc-hosted.php';
    if (($config['environment'] ?? '') !== 'staging' || ($config['issuer'] ?? '') !== 'https://oneid-uat.upnm.edu.my' || empty($config['binding_key'])) throw new RuntimeException();
    $pdo = new PDO(oneid_secret('ONEID_DB_DSN'), oneid_secret('ONEID_DB_USERNAME'), oneid_secret('ONEID_DB_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $target = $pdo->query('SELECT DATABASE() db,@@hostname host')->fetch(PDO::FETCH_ASSOC);
    if ($target['db'] !== 'oneiddb' || $target['host'] !== 'mysql8-DEV') throw new RuntimeException();
    $pdo->beginTransaction();
    if ($pdo->query('SELECT singleton_id FROM mobile_oidc_mutex WHERE singleton_id=1 FOR UPDATE')->fetchColumn() === false) throw new RuntimeException();
    $q = $pdo->prepare('SELECT data3,avail_status FROM user_tbl WHERE u_id=? FOR UPDATE');
    $q->execute(['S1TEST-20260714']); $account = $q->fetch(PDO::FETCH_ASSOC);
    if (!$account || $account['data3'] !== '0000-00' || (int)$account['avail_status'] !== 1) throw new RuntimeException();
    $key = 'rate:'.hash_hmac('sha256', 'password-identifier-ip:0000-00:172.16.4.60', $config['binding_key']);
    $q = $pdo->prepare('DELETE FROM mobile_oidc_records WHERE record_key=?'); $q->execute([$key]);
    $changed = $q->rowCount();
    $pdo->commit();
    echo "PASS: test identifier login counter cleared ($changed record). Password, MFA, sessions and shared IP limit unchanged.\n";
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, "STOP: release failed; no secrets printed.\n"); exit(1);
}
