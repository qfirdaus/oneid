<?php

declare(strict_types=1);

namespace OneId\App\Notification;

use DateTimeImmutable;
use DateTimeZone;

final class AdminEmailNotificationDispatcher
{
    private const EVENTS = [
        'NOTIFICATION_DELIVERY_TEST',
        'ACCOUNT_CREATED','PASSWORD_RESET_BY_ADMIN','ACCOUNT_DEACTIVATED','ACCOUNT_REACTIVATED',
        'ACCOUNT_PROFILE_CHANGED','ACCOUNT_ACCESS_GRANTED','ACCOUNT_ACCESS_REVOKED','SESSION_REVOKED',
        'MFA_EXEMPTION_GRANTED','MFA_EXEMPTION_REVOKED','MFA_EXEMPTION_EXPIRED','MAINTENANCE_DEVELOPER_GRANTED',
        'MAINTENANCE_DEVELOPER_REVOKED','MAINTENANCE_DEVELOPER_EXPIRED','MAINTENANCE_CHANGED',
        'SECURITY_POLICY_CHANGED','SYNC_COMPLETED','SYNC_WARNING','SYNC_FAILED','APPLICATION_CHANGED',
        'LOGIN_BANNER_CHANGED','SYSTEM_LOCALE_CHANGED','METADATA_CHANGED',
    ];

    public function __construct(private readonly AdminEmailNotificationRepository $repository) {}

    /** @param array<string,string> $details */
    public function enqueue(
        string $eventName,string $recipientEmail,string $recipientName,?string $recipientUserId,
        string $subject,string $headline,string $introduction,array $details,string $notice,
        string $correlationId,string $idempotencySeed,string $locale='ms'
    ): ?int {
        $mode=\oneid_admin_email_notification_delivery_mode();
        if($mode==='OFF')return null;
        if($mode==='PILOT'){
            $pilot=$this->repository->recipient((string)\oneid_config('ONEID_ADMIN_EMAIL_NOTIFICATION_PILOT_USER_ID',''));
            if(!is_array($pilot)||filter_var($pilot['data5']??'',FILTER_VALIDATE_EMAIL)===false)throw new AdminEmailNotificationException('NOTIFICATION_PILOT_INVALID');
            $recipientUserId=(string)$pilot['u_id'];$recipientEmail=(string)$pilot['data5'];
            $recipientName=trim((string)$pilot['data1'])!==''?(string)$pilot['data1']:(string)$pilot['u_id'];
            $locale=in_array($pilot['locale']??'',['ms','en'],true)?(string)$pilot['locale']:'ms';
            $subject=\oneid_admin_email_notification_pilot_prefix().$subject;
        }
        $eventName=strtoupper(trim($eventName));
        if(!in_array($eventName,self::EVENTS,true))throw new AdminEmailNotificationException('NOTIFICATION_EVENT_INVALID');
        $recipientEmail=trim($recipientEmail);$recipientName=trim($recipientName);$correlationId=trim($correlationId);
        if(filter_var($recipientEmail,FILTER_VALIDATE_EMAIL)===false)throw new AdminEmailNotificationException('NOTIFICATION_RECIPIENT_INVALID');
        if($recipientName===''||strlen($recipientName)>255)throw new AdminEmailNotificationException('NOTIFICATION_NAME_INVALID');
        if(!in_array($locale,['ms','en'],true))$locale='ms';
        // Schema contract stores a hexadecimal correlation identifier in CHAR(32).
        if(preg_match('/\A[a-f0-9]{16,32}\z/',$correlationId)!==1)throw new AdminEmailNotificationException('NOTIFICATION_CORRELATION_INVALID');
        foreach([$subject,$headline,$introduction,$notice] as $text)if(trim($text)===''||mb_strlen($text)>1000)throw new AdminEmailNotificationException('NOTIFICATION_CONTENT_INVALID');
        if(count($details)>12)throw new AdminEmailNotificationException('NOTIFICATION_DETAILS_INVALID');
        $safeDetails=[];foreach($details as $label=>$value){if(!is_string($label)||!is_string($value)||mb_strlen($label)>80||mb_strlen($value)>500)throw new AdminEmailNotificationException('NOTIFICATION_DETAILS_INVALID');$safeDetails[trim($label)]=trim($value);}
        $idempotency=hash('sha256',$eventName.'|'.$recipientEmail.'|'.$idempotencySeed);
        return $this->repository->enqueue([
            'event_name'=>$eventName,'recipient_user_id'=>$recipientUserId,'recipient_email'=>$recipientEmail,
            'recipient_name'=>$recipientName,'locale'=>$locale,'payload'=>[
                'subject'=>$subject,'context_label'=>'OneID Administration','badge'=>'Security notice',
                'headline'=>$headline,'introduction'=>$introduction,'details'=>$safeDetails,'notice'=>$notice,
            ],'idempotency_key'=>$idempotency,'correlation_id'=>$correlationId,
            'available_at'=>(new DateTimeImmutable('now',new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u'),
        ]);
    }
}
