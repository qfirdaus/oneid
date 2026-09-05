<?php
declare(strict_types=1);
$root=dirname(__DIR__);$q=(string)file_get_contents($root.'/lib/q_func.php');$c=(string)file_get_contents($root.'/app/Notification/AdminEmailNotificationComposer.php');$d=(string)file_get_contents($root.'/app/Notification/AdminEmailNotificationDispatcher.php');
$checks=[];$check=static function(bool $ok,string $label)use(&$checks):void{$checks[]=[$ok,$label];};
foreach(['APPLICATION_CHANGED','LOGIN_BANNER_CHANGED','SYSTEM_LOCALE_CHANGED','METADATA_CHANGED'] as $event){$check(substr_count($c,"'{$event}'")===2,"{$event} has BM and English copy");$check(str_contains($d,"'{$event}'"),"{$event} is dispatcher-approved");$check(str_contains($q,"'{$event}'"),"{$event} is wired to an admin mutation");}
$check(str_contains($q,"\$oneidGuardedAction!=='admin_login_banner_list'"),'banner reads do not queue notifications');
$check(str_contains($q,"==='ML5_DEFAULT_LOCALE_UPDATED'"),'unchanged locale does not queue a notification');
$check(str_contains($q,"!=='ML7_METADATA_NO_CHANGES'"),'unchanged metadata does not queue a notification');
$check(str_contains($q,"if(!isset(\$_POST['admin_get_archived_apps']))"),'archived application reads do not queue notifications');
$check(str_contains($q,'code=NOTIFICATION_QUEUE_FAILED'),'delivery queue failure remains non-blocking');
$failed=0;foreach($checks as[$ok,$label]){echo($ok?'PASS ':'FAIL ').$label.PHP_EOL;if(!$ok)$failed++;}echo 'RESULT checks='.count($checks).' failed='.$failed.PHP_EOL;exit($failed?1:0);
