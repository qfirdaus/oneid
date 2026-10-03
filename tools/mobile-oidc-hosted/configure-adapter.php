<?php
declare(strict_types=1);
// Staging wiring only: never enable hosted routes or lifecycle observer.
if (PHP_SAPI !== 'cli' || !in_array($argv[1] ?? '', ['--apply','--verify'], true)) exit(2);
$root = dirname(__DIR__, 2);
if (realpath($root) !== '/var/www/oneid-uat') exit(2);
require_once $root . '/app/Auth/MobileOidc/bootstrap.php';
try {
    if ($argv[1] === '--apply') {
        if (!function_exists('posix_geteuid') || posix_geteuid() !== 0) throw new RuntimeException('ROOT_REQUIRED');
        $old = require $root . '/.private/mobile-oidc-hosted.php';
        if (($old['enabled'] ?? null) !== false) throw new RuntimeException('HOSTED_MUST_BE_OFF');
        $provider = json_decode(file_get_contents('/etc/oneid-mobile-uat/hydra.json'), true, 32, JSON_THROW_ON_ERROR);
        $hook = $provider['oauth2']['token_hook']['auth']['config']['value'] ?? '';
        if (!is_string($hook) || strlen($hook) < 32 || ($provider['urls']['self']['issuer'] ?? '') !== 'https://oneid-uat.upnm.edu.my/') throw new RuntimeException('PROVIDER_CONFIG_INVALID');
        $runtime = require $root . '/.private/runtime.php';
        if (($runtime['ONEID_ENVIRONMENT'] ?? '') !== 'staging') throw new RuntimeException('STAGING_REQUIRED');
        $secretPath = $root . '/.private/mobile-oidc-secrets.php';
        if (file_exists($secretPath) || is_link($secretPath)) throw new RuntimeException('ALREADY_CONFIGURED_REVIEW_FIRST');
        $sessionPath = '/var/lib/oneid-mobile-web/sessions';
        foreach (['/var/lib/oneid-mobile-web', $sessionPath] as $path) {
            if (file_exists($path) || is_link($path)) throw new RuntimeException('SESSION_PATH_ALREADY_EXISTS');
        }
        umask(0077);
        $backup = $root . '/.private/mobile-adapter-before-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.php';
        if (!copy($root . '/.private/mobile-oidc-hosted.php', $backup)) throw new RuntimeException('BACKUP_FAILED');
        chmod($backup, 0600);
        foreach (['/var/lib/oneid-mobile-web', $sessionPath] as $path) {
            if (!mkdir($path, 0700) || !chown($path, 'www-data') || !chgrp($path, 'www-data')) throw new RuntimeException('SESSION_PATH_FAILED');
        }
        $secrets = ['hook_key'=>$hook, 'binding_key'=>bin2hex(random_bytes(32))];
        $h = fopen($secretPath, 'x');
        if (!$h) throw new RuntimeException('SECRET_FILE_FAILED');
        fwrite($h, "<?php\nreturn " . var_export($secrets, true) . ";\n"); fclose($h);
        chmod($secretPath, 0640); chgrp($secretPath, 'www-data');
        $template = $root . '/deployment/mobile-oidc/hosted-config.linked.php.example';
        $temporary = $root . '/.private/mobile-oidc-hosted-' . bin2hex(random_bytes(8)) . '.tmp';
        if (!copy($template, $temporary)) throw new RuntimeException('CONFIG_WRITE_FAILED');
        chmod($temporary, 0640); chgrp($temporary, 'www-data');
        if (!rename($temporary, $root . '/.private/mobile-oidc-hosted.php')) throw new RuntimeException('CONFIG_REPLACE_FAILED');
        echo "Dormant configuration installed. Backup retained privately. Verifying as www-data.\n";
        passthru('/usr/sbin/runuser -u www-data -- /usr/bin/php ' . escapeshellarg(__FILE__) . ' --verify', $status);
        if ($status !== 0) throw new RuntimeException('VERIFICATION_FAILED_CONFIG_REMAINS_OFF');
        echo "SUCCESS: adapter wiring verified; hosted and observer remain OFF. No email sent.\n";
        exit(0);
    }
    $c = require $root . '/.private/mobile-oidc-hosted.php';
    if (($c['enabled'] ?? null) !== false || $c['environment'] !== 'staging' || $c['clients'] !== []) throw new RuntimeException('DORMANT_GUARD_FAILED');
    if (strlen($c['hook_key']) < 32 || strlen($c['binding_key']) < 32) throw new RuntimeException('KEYS_INVALID');
    \OneId\App\Auth\TotpKeyring::fromFile($c['totp_keyring']);
    if (!is_writable($c['session_path']) || (fileperms($c['session_path']) & 0077) !== 0) throw new RuntimeException('SESSION_PATH_INVALID');
    $pdo = new PDO($c['dsn'], $c['db_user'], $c['db_password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_TIMEOUT=>5]);
    $pdo->exec('SET TRANSACTION READ ONLY'); $pdo->beginTransaction();
    $r = $pdo->query('SELECT DATABASE() AS db,@@hostname AS host')->fetch(PDO::FETCH_ASSOC);
    $off = $pdo->query('SELECT observer_enabled FROM mobile_oidc_control WHERE singleton_id=1')->fetchColumn();
    $pdo->rollBack();
    if ($r['db'] !== 'oneiddb' || $r['host'] !== 'mysql8-DEV' || (string)$off !== '0') throw new RuntimeException('DATABASE_GUARD_FAILED');
    $context = stream_context_create(['http'=>['timeout'=>5,'ignore_errors'=>true]]);
    $health = @file_get_contents($c['admin_url'] . '/health/ready', false, $context);
    if ($health === false || !preg_match('~^HTTP/\S+ 200\b~', $http_response_header[0] ?? '')) throw new RuntimeException('PROVIDER_NOT_READY');
    foreach (['Exception','PHPMailer','SMTP'] as $file) require_once $root . '/lib/src/' . $file . '.php';
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP(); $mail->SMTPDebug=0; $mail->Timeout=10; $mail->SMTPAuth=true;
    $mail->Host=$c['smtp']['host']; $mail->Port=$c['smtp']['port']; $mail->SMTPSecure=$c['smtp']['encryption'];
    if (!in_array($mail->SMTPSecure,['tls','ssl'],true)) throw new RuntimeException('SMTP_TLS_REQUIRED');
    $mail->Username=$c['smtp']['username']; $mail->Password=$c['smtp']['password'];
    try { if (!$mail->smtpConnect()) throw new RuntimeException('SMTP_CONNECTION_FAILED'); }
    finally { $mail->smtpClose(); }
    echo "PASS: web-user config access, existing TOTP keyring, private session directory, staging DB observer OFF, provider readiness, SMTP TLS/auth (no email sent).\n";
} catch (Throwable $e) {
    // Provider/SMTP/PDO exception messages can contain sensitive details.
    fwrite(STDERR, 'STOP: ' . (get_class($e) === RuntimeException::class ? $e->getMessage() : get_class($e)) . ". Feature remains OFF; do not repeat apply blindly.\n");
    exit(1);
}
