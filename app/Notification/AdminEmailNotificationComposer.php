<?php

declare(strict_types=1);

namespace OneId\App\Notification;

final class AdminEmailNotificationComposer
{
    /** @param array<string,string> $details */
    public static function queueUserEvent(
        object $operation,string $eventName,string $userId,string $correlationId,string $idempotencySeed,array $details=[]
    ): ?int {
        if(!function_exists('oneid_admin_email_notification_delivery_mode'))return null;
        $mode=\oneid_admin_email_notification_delivery_mode();
        if($mode==='OFF')return null;
        if(!method_exists($operation,'admin_email_notification_recipient')||!method_exists($operation,'admin_email_notification_enqueue')){
            throw new AdminEmailNotificationException('NOTIFICATION_OPERATION_UNAVAILABLE');
        }
        $recipient=$operation->admin_email_notification_recipient($userId);
        if(!is_array($recipient)||filter_var($recipient['data5']??'',FILTER_VALIDATE_EMAIL)===false)return null;
        if($mode==='PILOT'){
            $pilotId=(string)\oneid_config('ONEID_ADMIN_EMAIL_NOTIFICATION_PILOT_USER_ID','');
            $recipient=$operation->admin_email_notification_recipient($pilotId);
            if(!is_array($recipient)||filter_var($recipient['data5']??'',FILTER_VALIDATE_EMAIL)===false){
                throw new AdminEmailNotificationException('NOTIFICATION_PILOT_INVALID');
            }
        }
        $locale=in_array($recipient['notification_locale']??'',['ms','en'],true)?(string)$recipient['notification_locale']:'ms';
        $copy=self::copy($eventName,$locale);
        if($mode==='PILOT')$copy['subject']=\oneid_admin_email_notification_pilot_prefix().$copy['subject'];
        if($locale==='ms'){
            $labels=['User ID'=>'ID Pengguna','Action time'=>'Masa tindakan','Reference'=>'Rujukan','Application'=>'Aplikasi','Device'=>'Peranti',
                'Sync mode'=>'Mod sync','Source'=>'Sumber','Header ID'=>'ID header','New'=>'Baharu','Updated'=>'Dikemas kini',
                'Deactivated'=>'Dinyahaktifkan','Reactivated'=>'Diaktifkan semula','Audit marker'=>'Penanda audit',
                'Correlation ID'=>'ID korelasi','Diagnostic code'=>'Kod diagnostik'];
            $localized=[];foreach($details as $label=>$value)$localized[$labels[$label]??$label]=$value;$details=$localized;
        }
        $payload=[
            'subject'=>$copy['subject'],'context_label'=>$copy['context'],'badge'=>$copy['badge'],
            'headline'=>$copy['headline'],'introduction'=>$copy['introduction'],
            'details'=>$details,'notice'=>$copy['notice'],
        ];
        return $operation->admin_email_notification_enqueue([
            'event_name'=>$eventName,'recipient_user_id'=>(string)$recipient['u_id'],
            'recipient_email'=>(string)$recipient['data5'],
            'recipient_name'=>trim((string)$recipient['data1'])!==''?(string)$recipient['data1']:(string)$recipient['u_id'],
            'locale'=>$locale,'payload_json'=>json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
            'idempotency_key'=>hash('sha256',$eventName.'|'.$userId.'|'.$idempotencySeed),
            'correlation_id'=>$correlationId,
        ]);
    }

    /** @return array{subject:string,context:string,badge:string,headline:string,introduction:string,notice:string} */
    private static function copy(string $event,string $locale): array
    {
        $bm=[
            'ACCOUNT_CREATED'=>['Akaun OneID anda telah diwujudkan','Akaun diwujudkan','Administrator telah mewujudkan akaun OneID anda.','Gunakan proses pemulihan kata laluan yang diluluskan untuk menetapkan kata laluan pertama.'],
            'ACCOUNT_PROFILE_CHANGED'=>['Profil OneID anda berubah','Profil dikemas kini','Administrator telah mengubah nama atau kategori akaun anda.','Hubungi sokongan jika perubahan ini tidak dijangka.'],
            'USER_PASSWORD_CHANGED'=>['Kata laluan OneID anda berubah','Kata laluan berjaya diubah','Kata laluan akaun anda telah diubah melalui portal OneID.','Hubungi sokongan segera jika anda tidak melakukan tindakan ini.'],
            'INITIAL_PASSWORD_SET'=>['Kata laluan pertama OneID telah ditetapkan','Kata laluan pertama ditetapkan','Kata laluan pertama akaun anda berjaya ditetapkan.','Log masuk semula menggunakan kata laluan baharu anda.'],
            'PASSWORD_RESET_COMPLETED'=>['Pemulihan kata laluan OneID selesai','Kata laluan baharu ditetapkan','Proses pemulihan kata laluan anda telah selesai.','Hubungi sokongan segera jika anda tidak melakukan tindakan ini.'],
            'USER_MFA_TOTP_ENABLED'=>['Authenticator OneID diaktifkan','Authenticator diaktifkan','TOTP authenticator telah ditambah pada akaun anda.','Buang faktor ini segera jika anda tidak melakukan tindakan ini.'],
            'USER_MFA_PREFERENCE_CHANGED'=>['Kaedah MFA OneID berubah','Pilihan MFA dikemas kini','Kaedah pengesahan pilihan akaun anda telah berubah.','Semak tetapan keselamatan jika perubahan ini tidak dijangka.'],
            'USER_MFA_TOTP_REVOKED'=>['Authenticator OneID dibuang','Authenticator dibuang','Faktor TOTP telah dibuang dan sesi berkaitan ditamatkan.','Hubungi sokongan jika anda tidak melakukan tindakan ini.'],
            'MYDIGITALID_LINKED'=>['MyDigital ID dipautkan ke OneID','MyDigital ID dipautkan','Identiti MyDigital ID telah dipautkan ke akaun OneID anda.','Hubungi sokongan segera jika anda tidak melakukan tindakan ini.'],
            'LOGIN_SECURITY_WARNING'=>['Amaran keselamatan login OneID','Percubaan login berulang','Beberapa percubaan login gagal telah dikesan pada akaun anda.','Tukar kata laluan dan hubungi sokongan jika aktiviti ini bukan milik anda.'],
            'PASSWORD_RESET_BY_ADMIN'=>['Kata laluan OneID anda telah ditetapkan semula','Kata laluan ditetapkan semula','Administrator telah menetapkan semula kata laluan akaun anda.','Sila gunakan proses penetapan kata laluan melalui OTP sebelum log masuk semula.'],
            'ACCOUNT_DEACTIVATED'=>['Akaun OneID anda dinyahaktifkan','Akaun dinyahaktifkan','Administrator telah menyahaktifkan akaun OneID anda.','Hubungi perkhidmatan sokongan OneID jika tindakan ini tidak dijangka.'],
            'ACCOUNT_REACTIVATED'=>['Akaun OneID anda diaktifkan semula','Akaun diaktifkan semula','Administrator telah mengaktifkan semula akaun OneID anda.','Anda boleh menggunakan semula akaun berdasarkan polisi akses semasa.'],
            'ACCOUNT_ACCESS_GRANTED'=>['Akses aplikasi OneID diberikan','Akses aplikasi diberikan','Administrator telah memberikan akses aplikasi kepada akaun anda.','Log masuk ke OneID untuk melihat aplikasi yang tersedia.'],
            'ACCOUNT_ACCESS_REVOKED'=>['Akses aplikasi OneID berubah','Akses aplikasi ditarik balik','Administrator telah menarik balik akses aplikasi daripada akaun anda.','Hubungi pentadbir jika akses ini masih diperlukan.'],
            'SESSION_REVOKED'=>['Sesi OneID anda ditamatkan','Sesi ditamatkan','Administrator telah menamatkan satu sesi OneID anda.','Log masuk semula jika anda masih memerlukan akses. Hubungi sokongan jika tindakan ini tidak dikenali.'],
            'MAINTENANCE_CHANGED'=>['Konfigurasi Maintenance Mode OneID berubah','Maintenance Mode dikemas kini','Konfigurasi Maintenance Mode telah diubah oleh administrator.','Semak masa, mod dan rujukan perubahan dalam portal pentadbiran.'],
            'SECURITY_POLICY_CHANGED'=>['Polisi keselamatan OneID berubah','Polisi keselamatan dikemas kini','Satu polisi keselamatan OneID telah diubah oleh administrator.','Semak butiran dan audit trail perubahan dalam portal pentadbiran.'],
            'SYNC_COMPLETED'=>['External Sync OneID selesai','External Sync selesai','Proses External Sync OneID telah selesai dengan jayanya.','Semak ringkasan operasi dan audit trail dalam portal pentadbiran.'],
            'SYNC_WARNING'=>['External Sync OneID selesai dengan amaran','External Sync memerlukan semakan','Proses External Sync selesai tetapi satu amaran operasi telah direkodkan.','Semak ringkasan, correlation ID dan audit trail sebelum tindakan seterusnya.'],
            'SYNC_FAILED'=>['External Sync OneID gagal','External Sync tidak berjaya','Percubaan External Sync tidak dapat diselesaikan.','Semak kod diagnostik dan correlation ID dalam audit sistem sebelum mencuba semula.'],
            'APPLICATION_CHANGED'=>['Konfigurasi aplikasi OneID berubah','Aplikasi dikemas kini','Konfigurasi sebuah aplikasi OneID telah diubah oleh administrator.','Semak butiran dan audit trail perubahan dalam portal pentadbiran.'],
            'LOGIN_BANNER_CHANGED'=>['Login Banner OneID berubah','Login Banner dikemas kini','Kandungan atau susunan Login Banner telah diubah oleh administrator.','Semak kandungan yang diterbitkan dan audit trail dalam portal pentadbiran.'],
            'SYSTEM_LOCALE_CHANGED'=>['Bahasa lalai OneID berubah','Bahasa lalai dikemas kini','Bahasa lalai sistem OneID telah diubah oleh administrator.','Semak tetapan bahasa dan audit trail dalam portal pentadbiran.'],
            'METADATA_CHANGED'=>['Metadata dwibahasa OneID berubah','Metadata dikemas kini','Terjemahan metadata aplikasi atau kategori telah diubah oleh administrator.','Semak kandungan dwibahasa dan audit trail dalam portal pentadbiran.'],
        ];
        $en=[
            'ACCOUNT_CREATED'=>['Your OneID account was created','Account created','An administrator created your OneID account.','Use the approved password recovery process to set your first password.'],
            'ACCOUNT_PROFILE_CHANGED'=>['Your OneID profile changed','Profile updated','An administrator changed your account name or category.','Contact support if you did not expect this change.'],
            'USER_PASSWORD_CHANGED'=>['Your OneID password changed','Password changed','Your account password was changed through the OneID portal.','Contact support immediately if you did not perform this action.'],
            'INITIAL_PASSWORD_SET'=>['Your initial OneID password was set','Initial password set','Your initial account password was set successfully.','Sign in again using your new password.'],
            'PASSWORD_RESET_COMPLETED'=>['OneID password recovery completed','New password set','Your password recovery process was completed.','Contact support immediately if you did not perform this action.'],
            'USER_MFA_TOTP_ENABLED'=>['OneID authenticator enabled','Authenticator enabled','A TOTP authenticator was added to your account.','Remove this factor immediately if you did not perform this action.'],
            'USER_MFA_PREFERENCE_CHANGED'=>['Your OneID MFA method changed','MFA preference updated','Your preferred verification method was changed.','Review your security settings if this was unexpected.'],
            'USER_MFA_TOTP_REVOKED'=>['OneID authenticator removed','Authenticator removed','Your TOTP factor was removed and related sessions ended.','Contact support if you did not perform this action.'],
            'MYDIGITALID_LINKED'=>['MyDigital ID linked to OneID','MyDigital ID linked','A MyDigital ID identity was linked to your OneID account.','Contact support immediately if you did not perform this action.'],
            'LOGIN_SECURITY_WARNING'=>['OneID sign-in security warning','Repeated sign-in attempts','Several failed sign-in attempts were detected for your account.','Change your password and contact support if this activity was not yours.'],
            'PASSWORD_RESET_BY_ADMIN'=>['Your OneID password was reset','Password reset','An administrator reset the password for your account.','Complete the OTP password setup process before signing in again.'],
            'ACCOUNT_DEACTIVATED'=>['Your OneID account was deactivated','Account deactivated','An administrator deactivated your OneID account.','Contact OneID support if you did not expect this action.'],
            'ACCOUNT_REACTIVATED'=>['Your OneID account was reactivated','Account reactivated','An administrator reactivated your OneID account.','You may use the account again subject to the current access policy.'],
            'ACCOUNT_ACCESS_GRANTED'=>['OneID application access granted','Application access granted','An administrator granted application access to your account.','Sign in to OneID to view the available application.'],
            'ACCOUNT_ACCESS_REVOKED'=>['Your OneID application access changed','Application access revoked','An administrator revoked application access from your account.','Contact an administrator if you still require this access.'],
            'SESSION_REVOKED'=>['Your OneID session was ended','Session ended','An administrator ended one of your OneID sessions.','Sign in again if access is still required. Contact support if you do not recognize this action.'],
            'MAINTENANCE_CHANGED'=>['OneID Maintenance Mode configuration changed','Maintenance Mode updated','An administrator changed the Maintenance Mode configuration.','Review the mode, timing and change reference in the administration portal.'],
            'SECURITY_POLICY_CHANGED'=>['OneID security policy changed','Security policy updated','An administrator changed a OneID security policy.','Review the details and audit trail in the administration portal.'],
            'SYNC_COMPLETED'=>['OneID External Sync completed','External Sync completed','The OneID External Sync process completed successfully.','Review the operation summary and audit trail in the administration portal.'],
            'SYNC_WARNING'=>['OneID External Sync completed with a warning','External Sync requires review','The External Sync process completed but an operational warning was recorded.','Review the summary, correlation ID and audit trail before the next action.'],
            'SYNC_FAILED'=>['OneID External Sync failed','External Sync unsuccessful','The External Sync attempt could not be completed.','Review the diagnostic code and correlation ID in the system audit before retrying.'],
            'APPLICATION_CHANGED'=>['OneID application configuration changed','Application updated','An administrator changed a OneID application configuration.','Review the details and audit trail in the administration portal.'],
            'LOGIN_BANNER_CHANGED'=>['OneID Login Banner changed','Login Banner updated','An administrator changed Login Banner content or ordering.','Review the published content and audit trail in the administration portal.'],
            'SYSTEM_LOCALE_CHANGED'=>['OneID default language changed','Default language updated','An administrator changed the OneID system default language.','Review the language setting and audit trail in the administration portal.'],
            'METADATA_CHANGED'=>['OneID bilingual metadata changed','Metadata updated','An administrator changed an application or category metadata translation.','Review the bilingual content and audit trail in the administration portal.'],
        ];
        $selected=($locale==='en'?$en:$bm)[$event]??null;
        if(!is_array($selected))throw new AdminEmailNotificationException('NOTIFICATION_COPY_UNAVAILABLE');
        return ['subject'=>$selected[0],'context'=>$locale==='en'?'Account Security':'Keselamatan Akaun',
            'badge'=>$locale==='en'?'Security notice':'Notis keselamatan','headline'=>$selected[1],
            'introduction'=>$selected[2],'notice'=>$selected[3]];
    }
}
