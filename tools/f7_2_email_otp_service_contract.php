<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(2);
}

require_once dirname(__DIR__) . '/app/Auth/AdminStepUpException.php';
require_once dirname(__DIR__) . '/app/Auth/AdminStepUpEmailSenderInterface.php';
require_once dirname(__DIR__) . '/app/Auth/AdminStepUpEmailOtpService.php';

use OneId\App\Auth\AdminStepUpEmailOtpService;
use OneId\App\Auth\AdminStepUpEmailSenderInterface;
use OneId\App\Auth\AdminStepUpException;

final class F72Sender implements AdminStepUpEmailSenderInterface
{
    public string $otp = '';
    public bool $succeeds = true;
    public function send(string $otp, string $email, string $displayName): bool
    {
        $this->otp = $otp;
        return $this->succeeds;
    }
}

final class F72Operation
{
    public array $context = ['u_type'=>1,'avail_status'=>1,'admin_2fa_enabled'=>1,'email'=>'admin@example.test','display_name'=>'Admin'];
    public array $stats = ['cooldown_seconds'=>0,'admin_hour'=>0,'admin_day'=>0,'session_hour'=>0,'ip_hour'=>0];
    public array $challenges = [];
    public array $grants = [];
    public array $audits = [];
    public int $begins = 0;
    public int $commits = 0;
    public int $rollbacks = 0;

    public function beginTransaction(): void {$this->begins++;}
    public function commit(): void {$this->commits++;}
    public function rollback(): void {$this->rollbacks++;}
    public function admin_step_up_request_context_for_update(): array {return $this->context;}
    public function admin_step_up_request_stats(): array {return $this->stats;}
    public function admin_step_up_revoke_open_challenges($admin,$purpose,$session): int
    {
        $count=0;foreach($this->challenges as &$row){if($row['admin_user_id']===$admin&&$row['purpose']===$purpose&&$row['session_binding_hash']===$session&&$row['consumed_at']===null&&$row['revoked_at']===null){$row['revoked_at']='now';$count++;}}return$count;
    }
    public function admin_step_up_create_email_challenge(array $entry): int
    {
        $this->challenges[$entry['challenge_id']]=$entry+['factor_type'=>'EMAIL_OTP','attempts'=>0,'max_attempts'=>5,'sent_at'=>null,'consumed_at'=>null,'revoked_at'=>null,'is_expired'=>0,'admin_2fa_enabled'=>1];return 1;
    }
    public function admin_step_up_mark_challenge_sent($id): int
    {if(!isset($this->challenges[$id])||$this->challenges[$id]['revoked_at']!==null)return 0;$this->challenges[$id]['sent_at']='now';return 1;}
    public function admin_step_up_revoke_challenge($id): int
    {if(!isset($this->challenges[$id]))return 0;$this->challenges[$id]['revoked_at']='now';return 1;}
    public function admin_step_up_challenge_for_update($id): array|false{return$this->challenges[$id]??false;}
    public function admin_step_up_record_failed_attempt($id): int
    {$this->challenges[$id]['attempts']++;if($this->challenges[$id]['attempts']>=5)$this->challenges[$id]['revoked_at']='now';return 1;}
    public function admin_step_up_consume_challenge($id): int
    {if($this->challenges[$id]['consumed_at']!==null||$this->challenges[$id]['revoked_at']!==null)return 0;$this->challenges[$id]['consumed_at']='now';$this->challenges[$id]['otp_hash']=null;return 1;}
    public function admin_step_up_create_grant(array $entry): int {$this->grants[]=$entry;return 1;}
    public function syslog_record($event,$detail,$ip): int {$this->audits[]=[$event,$detail,$ip];return 1;}
}

$checks=0;$failed=0;
$report=static function(bool $ok,string $label)use(&$checks,&$failed):void{$checks++;if(!$ok)$failed++;printf("%s %s\n",$ok?'PASS':'FAIL',$label);};
$reason=static function(callable $call):string{try{$call();return'NONE';}catch(AdminStepUpException $e){return$e->reason;}};

$op=new F72Operation();$sender=new F72Sender();$service=new AdminStepUpEmailOtpService($op,$sender);
$request=$service->request('ADMIN1','ADMIN_ACCESS','session-secret','Browser/1','127.0.0.1');
$challenge=$op->challenges[$request['challenge_id']];
$report($request['code']==='STEP_UP_CHALLENGE_SENT'&&preg_match('/\A[a-f0-9]{64}\z/',$request['challenge_id'])===1,'request returns opaque challenge metadata');
$report(!array_key_exists('otp',$request)&&$sender->otp!==''&&$challenge['otp_hash']!==$sender->otp&&password_verify($sender->otp,$challenge['otp_hash']),'raw OTP is sent only to sender and hash-only persistence verifies');
$report($challenge['sent_at']!==null&&$challenge['session_binding_hash']===hash('sha256','session-secret')&&$challenge['browser_digest']===hash('sha256','Browser/1'),'sent challenge binds session and browser server-side');
$report(str_contains($request['masked_email'],'@example.test')&&!str_contains($request['masked_email'],'admin@'),'response masks destination email');

$verify=$service->verify('ADMIN1','ADMIN_ACCESS',$request['challenge_id'],$sender->otp,'session-secret','Browser/1','127.0.0.1');
$report($verify['code']==='STEP_UP_VERIFIED'&&count($op->grants)===1&&$op->grants[0]['purpose']==='ADMIN_ACCESS','valid OTP atomically creates exact-purpose grant');
$report($op->challenges[$request['challenge_id']]['consumed_at']!==null&&$op->challenges[$request['challenge_id']]['otp_hash']===null,'successful verification consumes challenge and erases OTP hash');
$report($reason(fn()=>$service->verify('ADMIN1','ADMIN_ACCESS',$request['challenge_id'],$sender->otp,'session-secret','Browser/1','127.0.0.1'))==='STEP_UP_REPLAYED'&&count($op->grants)===1,'consumed challenge cannot be replayed');

$op2=new F72Operation();$sender2=new F72Sender();$service2=new AdminStepUpEmailOtpService($op2,$sender2);$r2=$service2->request('ADMIN1','SECURITY_CONFIGURATION_CHANGE','s2','B2','127.0.0.2');
$report($reason(fn()=>$service2->verify('ADMIN1','ADMIN_ACCESS',$r2['challenge_id'],$sender2->otp,'s2','B2','127.0.0.2'))==='STEP_UP_VERIFICATION_FAILED'&&count($op2->grants)===0,'purpose mismatch is rejected without grant');
$report($reason(fn()=>$service2->verify('ADMIN1','SECURITY_CONFIGURATION_CHANGE',$r2['challenge_id'],'000000','s2','B2','127.0.0.2'))==='STEP_UP_VERIFICATION_FAILED'&&$op2->challenges[$r2['challenge_id']]['attempts']===1,'wrong OTP increments bounded attempts without grant');

$op3=new F72Operation();$sender3=new F72Sender();$sender3->succeeds=false;$service3=new AdminStepUpEmailOtpService($op3,$sender3);
$report($reason(fn()=>$service3->request('ADMIN1','ADMIN_ACCESS','s3','B3','127.0.0.3'))==='STEP_UP_DELIVERY_FAILED'&&array_values($op3->challenges)[0]['revoked_at']!==null,'SMTP failure revokes unsent challenge and fails closed');

$op4=new F72Operation();$op4->stats['admin_hour']=5;$sender4=new F72Sender();$service4=new AdminStepUpEmailOtpService($op4,$sender4);
$report($reason(fn()=>$service4->request('ADMIN1','ADMIN_ACCESS','s4','B4','127.0.0.4'))==='STEP_UP_RATE_LIMITED'&&$op4->challenges===[],'hourly limit rejects before challenge mutation');
$op4->stats=['cooldown_seconds'=>30,'admin_hour'=>0,'admin_day'=>0,'session_hour'=>0,'ip_hour'=>0];
$report($reason(fn()=>$service4->request('ADMIN1','ADMIN_ACCESS','s4','B4','127.0.0.4'))==='STEP_UP_RESEND_COOLDOWN'&&$op4->challenges===[],'resend cooldown rejects before challenge mutation');

$op5=new F72Operation();$op5->context['admin_2fa_enabled']=0;$service5=new AdminStepUpEmailOtpService($op5,new F72Sender());
$report($reason(fn()=>$service5->request('ADMIN1','ADMIN_ACCESS','s5','B5','127.0.0.5'))==='STEP_UP_DISABLED'&&$op5->challenges===[],'feature OFF rejects request without challenge mutation');
$op6=new F72Operation();$sender6=new F72Sender();$service6=new AdminStepUpEmailOtpService($op6,$sender6);$r6=$service6->request('ADMIN1','ADMIN_ACCESS','s6','B6','127.0.0.6');$op6->challenges[$r6['challenge_id']]['is_expired']=1;
$report($reason(fn()=>$service6->verify('ADMIN1','ADMIN_ACCESS',$r6['challenge_id'],$sender6->otp,'s6','B6','127.0.0.6'))==='STEP_UP_EXPIRED'&&$op6->challenges[$r6['challenge_id']]['revoked_at']!==null&&in_array(41,array_column($op6->audits,0),true),'expired challenge is revoked and audited without grant');
$report(count(array_filter($op->audits,fn($a)=>in_array($a[0],[37,38,39],true)))===3&&!str_contains(json_encode($op->audits),$sender->otp),'audit records request/sent/verified outcomes without OTP material');

printf("RESULT checks=%d failed=%d\n",$checks,$failed);exit($failed===0?0:1);
