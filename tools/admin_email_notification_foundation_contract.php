<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { exit(2); }

$root=dirname(__DIR__);
$runtime=(string)file_get_contents($root.'/config/runtime.php');
$template=(string)file_get_contents($root.'/app/Mail/OneIdEmailTemplate.php');
$repository=(string)file_get_contents($root.'/app/Notification/AdminEmailNotificationRepository.php');
$dispatcher=(string)file_get_contents($root.'/app/Notification/AdminEmailNotificationDispatcher.php');
$mailer=(string)file_get_contents($root.'/app/Notification/AdminEmailNotificationMailer.php');
$worker=(string)file_get_contents($root.'/tools/admin_email_notification_worker.php');
$checks=[
    'runtime remains disabled by default'=>str_contains($runtime,"'ONEID_ADMIN_EMAIL_NOTIFICATION_ENABLED' => 'false'"),
    'template renders escaped structured details'=>str_contains($template,'public static function notification')
        && str_contains($template,'self::escape($label)')&&str_contains($template,'self::escape($value)'),
    'dispatcher allowlists events and validates recipient'=>str_contains($dispatcher,'private const EVENTS')
        && str_contains($dispatcher,'FILTER_VALIDATE_EMAIL'),
    'dispatcher creates deterministic idempotency key'=>str_contains($dispatcher,"hash('sha256'")
        && str_contains($dispatcher,'$eventName')&&str_contains($dispatcher,'$idempotencySeed'),
    'repository claims with row lock'=>str_contains($repository,'LIMIT 1 FOR UPDATE')
        && str_contains($repository,"delivery_status='PROCESSING'"),
    'repository records every delivery attempt'=>str_contains($repository,'admin_email_notification_delivery_history')
        && str_contains($repository,'attempt_number'),
    'worker is bounded and runtime gated'=>str_contains($worker,'$limit > 100')
        && str_contains($worker,'oneid_admin_email_notification_delivery_mode'),
    'mailer uses shared branded template'=>str_contains($mailer,'OneIdEmailTemplate::notification')
        && str_contains($mailer,'$mail->msgHTML($html)'),
];
$failed=0;foreach($checks as $label=>$ok){printf("%s %s\n",$ok?'PASS':'FAIL',$label);$failed+=$ok?0:1;}
printf("RESULT checks=%d failed=%d\n",count($checks),$failed);exit($failed===0?0:1);
