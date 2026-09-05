<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { exit(2); }

require_once dirname(__DIR__) . '/lib/config.php';
require_once dirname(__DIR__) . '/lib/secrets.php';
require_once dirname(__DIR__) . '/lib/src/Exception.php';
require_once dirname(__DIR__) . '/lib/src/PHPMailer.php';
require_once dirname(__DIR__) . '/lib/src/SMTP.php';
require_once dirname(__DIR__) . '/app/Mail/OneIdEmailTemplate.php';
require_once dirname(__DIR__) . '/app/Notification/AdminEmailNotificationException.php';
require_once dirname(__DIR__) . '/app/Notification/AdminEmailNotificationRepository.php';
require_once dirname(__DIR__) . '/app/Notification/AdminEmailNotificationMailer.php';

use OneId\App\Notification\AdminEmailNotificationMailer;
use OneId\App\Notification\AdminEmailNotificationRepository;

$deliveryMode=oneid_admin_email_notification_delivery_mode();
if ($deliveryMode === 'OFF') {
    echo "SKIPPED admin e-mail notifications are disabled\n";
    exit(0);
}

$limit = isset($argv[1]) ? filter_var($argv[1], FILTER_VALIDATE_INT) : 25;
if (!is_int($limit) || $limit < 1 || $limit > 100) {
    fwrite(STDERR, "Usage: php tools/admin_email_notification_worker.php [1-100]\n");
    exit(2);
}
$maxAttempts = filter_var(oneid_config('ONEID_ADMIN_EMAIL_NOTIFICATION_MAX_ATTEMPTS', '5'), FILTER_VALIDATE_INT);
$retrySeconds = filter_var(oneid_config('ONEID_ADMIN_EMAIL_NOTIFICATION_RETRY_SECONDS', '300'), FILTER_VALIDATE_INT);
if (!is_int($maxAttempts) || $maxAttempts < 1 || $maxAttempts > 20
    || !is_int($retrySeconds) || $retrySeconds < 30 || $retrySeconds > 86400) {
    throw new RuntimeException('ADMIN_EMAIL_NOTIFICATION_RUNTIME_INVALID');
}

$pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$repository = new AdminEmailNotificationRepository($pdo);
$mailer = new AdminEmailNotificationMailer();
$pilotRecipient=null;
if($deliveryMode==='PILOT'){
    $pilotRecipient=$repository->recipient((string)oneid_config('ONEID_ADMIN_EMAIL_NOTIFICATION_PILOT_USER_ID',''));
    if(!is_array($pilotRecipient)||filter_var($pilotRecipient['data5']??'',FILTER_VALIDATE_EMAIL)===false){
        throw new RuntimeException('ADMIN_EMAIL_NOTIFICATION_PILOT_INVALID');
    }
}
$workerToken = bin2hex(random_bytes(16));
$processed = $sent = $failed = 0;
while ($processed < $limit) {
    $row = $repository->claim($workerToken, new DateTimeImmutable('now', new DateTimeZone('UTC')));
    if ($row === null) { break; }
    if($deliveryMode==='PILOT'&&(!hash_equals((string)$pilotRecipient['u_id'],(string)$row['recipient_user_id'])
        ||!hash_equals(strtolower((string)$pilotRecipient['data5']),strtolower((string)$row['recipient_email'])))){
        $repository->complete((int)$row['notification_id'],$workerToken,'SUPPRESSED','PILOT_RECIPIENT_MISMATCH',null,
            $maxAttempts,$retrySeconds,(string)$row['correlation_id']);
        $processed++;continue;
    }
    $result = $mailer->send($row);
    $outcome = $result['sent'] ? 'SENT' : 'FAILED';
    $repository->complete((int)$row['notification_id'],$workerToken,$outcome,$result['error_code'],
        $result['message_id'],$maxAttempts,$retrySeconds,(string)$row['correlation_id']);
    $processed++; $sent += $result['sent'] ? 1 : 0; $failed += $result['sent'] ? 0 : 1;
}
printf("RESULT processed=%d sent=%d failed=%d\n", $processed, $sent, $failed);
exit($failed === 0 ? 0 : 1);
