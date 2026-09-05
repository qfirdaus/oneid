<?php

declare(strict_types=1);

namespace OneId\App\Notification;

use PDO;

final class AdminEmailNotificationPdoComposer
{
    /** @param array<string,string> $details */
    public static function queue(PDO $pdo,string $event,string $userId,string $correlation,string $seed,array $details=[]): ?int
    {
        $mode=\oneid_admin_email_notification_delivery_mode();if($mode==='OFF')return null;
        $repository=new AdminEmailNotificationRepository($pdo);
        $lookupId=$mode==='PILOT'?(string)\oneid_config('ONEID_ADMIN_EMAIL_NOTIFICATION_PILOT_USER_ID',''):$userId;
        $recipient=$repository->recipient($lookupId);
        if(!is_array($recipient)||filter_var($recipient['data5']??'',FILTER_VALIDATE_EMAIL)===false)return null;
        $locale=in_array($recipient['locale']??'',['ms','en'],true)?(string)$recipient['locale']:'ms';
        $copy=self::copy($event,$locale);
        if($locale==='ms'){$labels=['User ID'=>'ID Pengguna','Valid from'=>'Sah dari','Valid until'=>'Sah sehingga','Reference'=>'Rujukan','Action time'=>'Masa tindakan'];$localized=[];foreach($details as $key=>$value)$localized[$labels[$key]??$key]=$value;$details=$localized;}
        return (new AdminEmailNotificationDispatcher($repository))->enqueue(
            $event,(string)$recipient['data5'],(string)$recipient['data1'],(string)$recipient['u_id'],
            $copy['subject'],$copy['headline'],$copy['introduction'],$details,$copy['notice'],$correlation,$seed,$locale
        );
    }

    /** @return array{subject:string,headline:string,introduction:string,notice:string} */
    private static function copy(string $event,string $locale): array
    {
        $bm=[
            'MFA_EXEMPTION_GRANTED'=>['Pengecualian MFA OneID diberikan','Pengecualian MFA diberikan','Administrator telah memberikan pengecualian MFA sementara.','Gunakan tempoh ini hanya untuk tujuan yang diluluskan.'],
            'MFA_EXEMPTION_REVOKED'=>['Pengecualian MFA OneID ditamatkan','Pengecualian MFA ditamatkan','Administrator telah menamatkan pengecualian MFA anda.','Pengesahan MFA biasa kini terpakai semula.'],
            'MFA_EXEMPTION_EXPIRED'=>['Pengecualian MFA OneID tamat','Pengecualian MFA tamat','Tempoh pengecualian MFA anda telah tamat.','Pengesahan MFA biasa kini terpakai semula.'],
            'MAINTENANCE_DEVELOPER_GRANTED'=>['Akses developer maintenance OneID diberikan','Akses maintenance diberikan','Administrator telah memberikan akses sementara semasa maintenance.','Akses ini tidak memberikan keistimewaan administrator dan hanya sah dalam tempoh diluluskan.'],
            'MAINTENANCE_DEVELOPER_REVOKED'=>['Akses developer maintenance OneID ditamatkan','Akses maintenance ditamatkan','Administrator telah menarik balik akses developer maintenance.','Log masuk maintenance tidak lagi dibenarkan menggunakan grant ini.'],
            'MAINTENANCE_DEVELOPER_EXPIRED'=>['Akses developer maintenance OneID tamat','Akses maintenance tamat','Tempoh akses developer maintenance telah tamat.','Log masuk maintenance tidak lagi dibenarkan menggunakan grant ini.'],
            'SECURITY_POLICY_CHANGED'=>['Polisi keselamatan OneID berubah','Polisi keselamatan dikemas kini','Satu polisi keselamatan OneID telah diubah oleh administrator.','Semak butiran dan audit trail perubahan dalam portal pentadbiran.'],
        ];
        $en=[
            'MFA_EXEMPTION_GRANTED'=>['OneID MFA exemption granted','MFA exemption granted','An administrator granted a temporary MFA exemption.','Use this period only for the approved purpose.'],
            'MFA_EXEMPTION_REVOKED'=>['OneID MFA exemption ended','MFA exemption ended','An administrator ended your MFA exemption.','Normal MFA verification now applies again.'],
            'MFA_EXEMPTION_EXPIRED'=>['OneID MFA exemption expired','MFA exemption expired','Your temporary MFA exemption has expired.','Normal MFA verification now applies again.'],
            'MAINTENANCE_DEVELOPER_GRANTED'=>['OneID developer maintenance access granted','Maintenance access granted','An administrator granted temporary access during maintenance.','This does not grant administrator privileges and is valid only for the approved period.'],
            'MAINTENANCE_DEVELOPER_REVOKED'=>['OneID developer maintenance access ended','Maintenance access ended','An administrator revoked developer maintenance access.','Maintenance login is no longer allowed through this grant.'],
            'MAINTENANCE_DEVELOPER_EXPIRED'=>['OneID developer maintenance access expired','Maintenance access expired','Your developer maintenance access period has expired.','Maintenance login is no longer allowed through this grant.'],
            'SECURITY_POLICY_CHANGED'=>['OneID security policy changed','Security policy updated','An administrator changed a OneID security policy.','Review the details and audit trail in the administration portal.'],
        ];
        $copy=($locale==='en'?$en:$bm)[$event]??null;if(!is_array($copy))throw new AdminEmailNotificationException('NOTIFICATION_COPY_UNAVAILABLE');
        return ['subject'=>$copy[0],'headline'=>$copy[1],'introduction'=>$copy[2],'notice'=>$copy[3]];
    }
}
