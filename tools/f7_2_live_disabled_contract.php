<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { exit(2); }
require_once dirname(__DIR__) . '/lib/config.php';
require_once dirname(__DIR__) . '/lib/Database.php';
require_once dirname(__DIR__) . '/app/Auth/AdminStepUpException.php';
require_once dirname(__DIR__) . '/app/Auth/AdminStepUpEmailSenderInterface.php';
require_once dirname(__DIR__) . '/app/Auth/AdminStepUpEmailOtpService.php';

use OneId\App\Auth\AdminStepUpEmailOtpService;
use OneId\App\Auth\AdminStepUpEmailSenderInterface;
use OneId\App\Auth\AdminStepUpException;

final class F72DisabledSender implements AdminStepUpEmailSenderInterface
{public int $calls=0;public function send(string $otp,string $email,string $name):bool{$this->calls++;return true;}}

$pdo=new PDO(DB_DSN,DB_USERNAME,DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$admin=$pdo->query("SELECT u_id FROM user_tbl WHERE u_type=1 AND avail_status=1 AND TRIM(data5)<>'' ORDER BY u_id LIMIT 1")->fetchColumn();
if(!is_string($admin)||$admin===''){fwrite(STDERR,"FAIL no eligible admin fixture\n");exit(1);}
$count=static fn()=> (int)$pdo->query('SELECT COUNT(*) FROM admin_step_up_challenges')->fetchColumn();
$before=$count();$sender=new F72DisabledSender();$service=new AdminStepUpEmailOtpService(new Database(),$sender);$reason='NONE';
try{$service->request($admin,'ADMIN_ACCESS','f72-live-disabled-session','F7.2 disabled contract','127.0.0.1');}catch(AdminStepUpException $e){$reason=$e->reason;}
$after=$count();$flag=(int)$pdo->query('SELECT admin_2fa_enabled FROM sys_config WHERE singleton_key=1')->fetchColumn();
$ok=$flag===0&&$reason==='STEP_UP_DISABLED'&&$sender->calls===0&&$before===$after;
printf("%s feature_off=%d reason=%s sender_calls=%d rows_before=%d rows_after=%d mutation_statements=0\n",$ok?'PASS':'FAIL',$flag,$reason,$sender->calls,$before,$after);
exit($ok?0:1);
