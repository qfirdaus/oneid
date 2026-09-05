<?php

declare(strict_types=1);

if(PHP_SAPI!=='cli'){exit(2);}
require_once dirname(__DIR__).'/lib/config.php';
require_once dirname(__DIR__).'/app/Notification/AdminEmailNotificationException.php';
require_once dirname(__DIR__).'/app/Notification/AdminEmailNotificationRepository.php';
require_once dirname(__DIR__).'/app/Notification/AdminEmailNotificationDispatcher.php';
require_once dirname(__DIR__).'/app/Notification/AdminEmailNotificationPdoComposer.php';
require_once dirname(__DIR__).'/app/Admin/UserMfaExemptionExpiryService.php';
$environment=(string)oneid_config('ONEID_ENVIRONMENT','');if(!in_array($environment,['local','staging','production'],true))throw new RuntimeException('MFA_EXPIRY_ENVIRONMENT_INVALID');
$limit=isset($argv[1])?filter_var($argv[1],FILTER_VALIDATE_INT):50;if(!is_int($limit)||$limit<1||$limit>500){fwrite(STDERR,"Usage: php tools/user_mfa_exemption_expiry_worker.php [1-500]\n");exit(2);}
$pdo=new PDO(DB_DSN,DB_USERNAME,DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$callback=static fn(string $event,string $user,string $correlation,string $seed,array $details=[]):?int=>\OneId\App\Notification\AdminEmailNotificationPdoComposer::queue($pdo,$event,$user,$correlation,$seed,$details);
$result=(new \OneId\App\Admin\UserMfaExemptionExpiryService($pdo,$callback))->run($limit);
printf("RESULT environment=%s expired=%d notifications=%d\n",$environment,$result['expired'],$result['notifications']);
