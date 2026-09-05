<?php

declare(strict_types=1);

namespace OneId\App\Notification;

use OneId\App\Mail\OneIdEmailTemplate;
use PHPMailer\PHPMailer\PHPMailer;
use Throwable;

final class AdminEmailNotificationMailer
{
    /** @param array<string,mixed> $row @return array{sent:bool,message_id:?string,error_code:?string} */
    public function send(array $row): array
    {
        try{
            $payload=json_decode((string)$row['payload_json'],true,32,JSON_THROW_ON_ERROR);
            if(!is_array($payload))throw new AdminEmailNotificationException('NOTIFICATION_PAYLOAD_INVALID');
            $locale=in_array($row['locale']??'',['ms','en'],true)?(string)$row['locale']:'ms';
            $html=OneIdEmailTemplate::notification((string)$row['recipient_name'],(string)$payload['context_label'],
                (string)$payload['badge'],(string)$payload['headline'],(string)$payload['introduction'],
                is_array($payload['details']??null)?$payload['details']:[],(string)$payload['notice'],$locale);
            $mail=new PHPMailer(true);$mail->CharSet='UTF-8';$mail->Encoding='base64';$mail->isSMTP();$mail->SMTPDebug=0;$mail->Timeout=10;
            $mail->Host=(string)\oneid_config('ONEID_SMTP_HOST');$mail->Port=(int)\oneid_config('ONEID_SMTP_PORT');
            $mail->SMTPSecure=(string)\oneid_config('ONEID_SMTP_ENCRYPTION');$mail->SMTPAuth=true;
            $mail->Username=\oneid_secret('ONEID_SMTP_USERNAME');$mail->Password=\oneid_secret('ONEID_SMTP_PASSWORD');
            $mail->setFrom(\oneid_secret('ONEID_SMTP_USERNAME'),(string)\oneid_config('ONEID_SMTP_FROM_NAME'));
            $mail->addAddress((string)$row['recipient_email'],(string)$row['recipient_name']);$mail->Subject=(string)$payload['subject'];
            $logoPath=dirname(__DIR__,2).'/public/img/logo_upnm_30.png';
            if(!is_file($logoPath)||!is_readable($logoPath))throw new AdminEmailNotificationException('NOTIFICATION_BRAND_ASSET_UNAVAILABLE');
            $mail->addEmbeddedImage($logoPath,'oneid-upnm-logo','logo_upnm_30.png','base64','image/png');
            $mail->msgHTML($html);$mail->AltBody=(string)$payload['headline']."\n\n".(string)$payload['introduction']."\n\n".(string)$payload['notice'];
            $sent=(bool)$mail->send();return ['sent'=>$sent,'message_id'=>$sent?$mail->getLastMessageID():null,'error_code'=>$sent?null:'SMTP_REJECTED'];
        }catch(Throwable $e){error_log('Admin notification mail failed id='.(int)($row['notification_id']??0).' exception='.get_class($e));return ['sent'=>false,'message_id'=>null,'error_code'=>'SMTP_DELIVERY_FAILED'];}
    }
}
