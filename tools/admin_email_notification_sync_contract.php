<?php

declare(strict_types=1);

$root=dirname(__DIR__);
$q=(string)file_get_contents($root.'/lib/q_func.php');
$composer=(string)file_get_contents($root.'/app/Notification/AdminEmailNotificationComposer.php');
$dispatcher=(string)file_get_contents($root.'/app/Notification/AdminEmailNotificationDispatcher.php');
$checks=[];
$check=static function(bool $ok,string $label)use(&$checks):void{$checks[]=[$ok,$label];};

$check(str_contains($q,'function oneid_queue_sync_admin_notification('),'sync notification uses one bounded helper');
$check(str_contains($dispatcher,"{16,32}"),'correlation validation matches the CHAR(32) schema contract');
$check(substr_count($q,"'SYNC_COMPLETED'")>=2,'pilot and successful bulk sync queue completion events');
$check(str_contains($q,"'SYNC_WARNING'"),'audit warning queues a warning event');
$check(substr_count($q,"'SYNC_FAILED'")>=3,'pilot, full and operational failures queue failure events');
$check(substr_count($q,"'notification_queued'")>=6,'all sync apply responses expose queue status separately');
$check(str_contains($q,'code=NOTIFICATION_QUEUE_FAILED')&&str_contains($q,'catch (\\Throwable $notificationException)'),'notification failure is logged and remains non-blocking');
$check(str_contains($q,"'Header ID'")&&str_contains($q,"'Correlation ID'")&&str_contains($q,"'Diagnostic code'"),'messages contain safe operational references');
$check(!str_contains($q,"'Password' =>")&&!str_contains($q,"'Token' =>"),'sync notification summary does not expose secrets');
foreach(['SYNC_COMPLETED','SYNC_WARNING','SYNC_FAILED'] as $event){
    $check(substr_count($composer,"'{$event}'")===2,"{$event} has exact BM and English copy");
    $check(str_contains($dispatcher,"'{$event}'"),"{$event} remains dispatcher-approved");
}

$failed=0;
foreach($checks as [$ok,$label]){echo($ok?'PASS ':'FAIL ').$label.PHP_EOL;if(!$ok)$failed++;}
echo 'RESULT checks='.count($checks).' failed='.$failed.PHP_EOL;
exit($failed===0?0:1);
