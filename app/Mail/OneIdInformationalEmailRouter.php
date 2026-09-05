<?php
declare(strict_types=1);
namespace OneId\App\Mail;
use PDO;
use RuntimeException;
final class OneIdInformationalEmailRouter
{
    /** @return array{email:string,name:string,pilot:bool} */
    public static function route(string $email,string $name):array
    {
        if(!function_exists('oneid_admin_email_notification_delivery_mode')||\oneid_admin_email_notification_delivery_mode()!=='PILOT')return ['email'=>$email,'name'=>$name,'pilot'=>false];
        $pilotId=trim((string)\oneid_config('ONEID_ADMIN_EMAIL_NOTIFICATION_PILOT_USER_ID',''));
        if($pilotId==='')throw new RuntimeException('INFORMATIONAL_EMAIL_PILOT_INVALID');
        $pdo=new PDO(\DB_DSN,\DB_USERNAME,\DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $statement=$pdo->prepare('SELECT u_id,data1,data5,avail_status FROM user_tbl WHERE u_id=:id LIMIT 1');$statement->execute([':id'=>$pilotId]);$pilot=$statement->fetch(PDO::FETCH_ASSOC);
        if(!is_array($pilot)||(int)($pilot['avail_status']??0)!==1||filter_var($pilot['data5']??'',FILTER_VALIDATE_EMAIL)===false)throw new RuntimeException('INFORMATIONAL_EMAIL_PILOT_INVALID');
        return ['email'=>(string)$pilot['data5'],'name'=>trim((string)$pilot['data1'])?:$pilotId,'pilot'=>true];
    }
}
