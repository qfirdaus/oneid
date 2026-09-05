<?php

declare(strict_types=1);

if(PHP_SAPI!=='cli'){exit(2);}

require_once dirname(__DIR__).'/config/runtime.php';
require_once dirname(__DIR__).'/app/Notification/AdminEmailNotificationException.php';
require_once dirname(__DIR__).'/app/Notification/AdminEmailNotificationComposer.php';

$files=[
    'security'=>(string)file_get_contents(dirname(__DIR__).'/app/User/UserSecurityActionService.php'),
    'acl'=>(string)file_get_contents(dirname(__DIR__).'/app/User/UserAclManagementService.php'),
    'session'=>(string)file_get_contents(dirname(__DIR__).'/app/Admin/ActiveSessionRevocationService.php'),
    'database'=>(string)file_get_contents(dirname(__DIR__).'/lib/Database.php'),
];
$checks=[];
$checks['password reset and account status queue exact events']=str_contains($files['security'],"'PASSWORD_RESET_BY_ADMIN'")
    &&str_contains($files['security'],"'ACCOUNT_DEACTIVATED'")&&str_contains($files['security'],"'ACCOUNT_REACTIVATED'");
$checks['account action notification is queued before commit']=strpos($files['security'],'queueUserEvent(')<strpos($files['security'],'$this->operation->commit()');
$checks['ACL grant revoke and uplift queue exact events']=str_contains($files['acl'],"'ACCOUNT_ACCESS_REVOKED'")
    &&str_contains($files['acl'],"'ACCOUNT_ACCESS_GRANTED'")&&str_contains($files['acl'],'$action===\'deny\'');
$checks['ACL notification is queued before commit']=strpos($files['acl'],'queueUserEvent(')<strpos($files['acl'],'$this->operation->commit()');
$checks['session revoke queues without token material']=str_contains($files['session'],"'SESSION_REVOKED'")
    &&!preg_match('/queueUserEvent\([\s\S]{0,500}token_id/',$files['session']);
$checks['session notification is queued before commit']=strpos($files['session'],'queueUserEvent(')<strpos($files['session'],'$this->operation->commit()');
$checks['recipient is resolved server side']=str_contains($files['database'],'admin_email_notification_recipient')
    &&str_contains($files['database'],'FROM user_tbl U WHERE U.u_id=:user_id');
$checks['outbox insert uses caller database connection']=str_contains($files['database'],'admin_email_notification_enqueue')
    &&str_contains($files['database'],'admin_email_notification_outbox');

final class PriorityOneNotificationFixture{
    public array $queued=[];
    public function admin_email_notification_recipient(string $id):array{return ['u_id'=>$id,'data1'=>'Pengguna Ujian','data5'=>'user@example.test','notification_locale'=>'ms'];}
    public function admin_email_notification_enqueue(array $message):int{$this->queued[]=$message;return count($this->queued);}
}
putenv('ONEID_ADMIN_EMAIL_NOTIFICATION_ENABLED=true');
putenv('ONEID_ADMIN_EMAIL_NOTIFICATION_DELIVERY_MODE=PILOT');
putenv('ONEID_ADMIN_EMAIL_NOTIFICATION_PILOT_USER_ID=820705025923');
putenv('ONEID_ADMIN_EMAIL_NOTIFICATION_LIVE_APPROVED=false');
$fixture=new PriorityOneNotificationFixture();
$id=\OneId\App\Notification\AdminEmailNotificationComposer::queueUserEvent($fixture,'SESSION_REVOKED','user-1','abcdef0123456789','event-1',['Device'=>'Browser','Reference'=>'abcdef0123456789']);
$payload=json_decode((string)($fixture->queued[0]['payload_json']??''),true);
$checks['enabled composer creates localized safe payload']=$id===1&&is_array($payload)
    &&isset($payload['details']['Peranti'])&&!str_contains((string)$fixture->queued[0]['payload_json'],'token_id');
$duplicate=\OneId\App\Notification\AdminEmailNotificationComposer::queueUserEvent($fixture,'SESSION_REVOKED','user-1','abcdef0123456789','event-1');
$checks['same event seed produces same idempotency key']=$duplicate===2
    &&hash_equals($fixture->queued[0]['idempotency_key'],$fixture->queued[1]['idempotency_key']);
putenv('ONEID_ADMIN_EMAIL_NOTIFICATION_ENABLED');
putenv('ONEID_ADMIN_EMAIL_NOTIFICATION_DELIVERY_MODE');
putenv('ONEID_ADMIN_EMAIL_NOTIFICATION_PILOT_USER_ID');
putenv('ONEID_ADMIN_EMAIL_NOTIFICATION_LIVE_APPROVED');

$failed=0;foreach($checks as $label=>$ok){printf("%s %s\n",$ok?'PASS':'FAIL',$label);$failed+=$ok?0:1;}
printf("RESULT checks=%d failed=%d\n",count($checks),$failed);exit($failed===0?0:1);
