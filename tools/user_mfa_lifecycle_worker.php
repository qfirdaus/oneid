<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
require_once dirname(__DIR__).'/lib/config.php';
require_once dirname(__DIR__).'/app/Admin/SsoConfigurationException.php';
require_once dirname(__DIR__).'/app/Admin/UserMfaLifecycleService.php';
require_once dirname(__DIR__).'/app/Notification/AdminEmailNotificationException.php';
require_once dirname(__DIR__).'/app/Notification/AdminEmailNotificationRepository.php';
require_once dirname(__DIR__).'/app/Notification/AdminEmailNotificationDispatcher.php';
require_once dirname(__DIR__).'/app/Notification/AdminEmailNotificationPdoComposer.php';
$apply=in_array('--apply',$argv,true);$check=in_array('--check',$argv,true);if((int)$apply+(int)$check!==1){fwrite(STDERR,"Usage: php tools/user_mfa_lifecycle_worker.php --check | --apply\n");exit(2);}
$pdo=new PDO(DB_DSN,DB_USERNAME,DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$environment=strtolower((string)oneid_config('ONEID_ENVIRONMENT',''));$limit=(int)oneid_config('ONEID_USER_MFA_LIFECYCLE_WORKER_LIMIT','25');if(!in_array($environment,['local','staging','production'],true)||$limit<1||$limit>100)throw new RuntimeException('USER_MFA_WORKER_RUNTIME_INVALID');
$due=$pdo->prepare("SELECT COUNT(*) FROM user_mfa_policy_change_requests WHERE environment=:environment AND request_status='ACTIVE' AND ((requested_mode='EMERGENCY_BYPASS' AND expires_at<=NOW(6)) OR (transition_strategy='GRACE' AND grace_until<=NOW(6)))");$due->execute([':environment'=>$environment]);$count=(int)$due->fetchColumn();if($check){echo "RESULT mode=check environment={$environment} due={$count} mutation=0\n";exit(0);}if(!filter_var(oneid_config('ONEID_USER_MFA_LIFECYCLE_WORKER_ENABLED','false'),FILTER_VALIDATE_BOOLEAN))throw new RuntimeException('USER_MFA_WORKER_DISABLED');
$notify=static fn(string$event,string$user,string$correlation,string$seed,array$details):?int=>\OneId\App\Notification\AdminEmailNotificationPdoComposer::queue($pdo,$event,$user,$correlation,$seed,$details);
$service=new \OneId\App\Admin\UserMfaLifecycleService($pdo,$notify,$environment);$warnings=$service->queueDueWarnings($environment);$processed=0;while($processed<$limit&&$service->processNext($environment)!==null)$processed++;echo "RESULT mode=apply environment={$environment} processed={$processed} warnings={$warnings}\n";
