<?php

declare(strict_types=1);

namespace OneId\App\Admin;

use PDO;
use Throwable;

final class UserMfaExemptionExpiryService
{
    private readonly ?\Closure $notification;
    public function __construct(private readonly PDO $pdo,?callable $notification=null){$this->notification=$notification===null?null:\Closure::fromCallable($notification);}

    /** @return array{expired:int,notifications:int} */
    public function run(int $limit=50):array
    {
        if($limit<1||$limit>500)throw new \InvalidArgumentException('MFA_EXPIRY_LIMIT_INVALID');
        $expired=$notifications=0;
        while($expired<$limit){
            $started=false;
            try{$this->pdo->beginTransaction();$started=true;
                $select=$this->pdo->query("SELECT exemption_id,u_id,expires_at,change_reference FROM user_login_mfa_exemptions
                    WHERE exemption_status='ACTIVE' AND expires_at<=NOW(6) ORDER BY expires_at,exemption_id LIMIT 1 FOR UPDATE SKIP LOCKED");
                $row=$select->fetch(PDO::FETCH_ASSOC);if(!is_array($row)){$this->pdo->commit();break;}
                $correlation=bin2hex(random_bytes(16));
                $update=$this->pdo->prepare("UPDATE user_login_mfa_exemptions SET exemption_status='EXPIRED'
                    WHERE exemption_id=:id AND exemption_status='ACTIVE' AND expires_at<=NOW(6)");
                $update->execute([':id'=>$row['exemption_id']]);if($update->rowCount()!==1)throw new \RuntimeException('MFA_EXPIRY_STALE');
                $audit=$this->pdo->prepare("INSERT INTO syslog(log_type,log_detail,ip_addr,datetime) VALUES(64,:detail,'127.0.0.1',NOW())");
                $audit->execute([':detail'=>sprintf('action=user_mfa_exemption_auto_expire user=%s reference=%s correlation=%s',(string)$row['u_id'],(string)$row['change_reference'],$correlation)]);
                if($audit->rowCount()!==1)throw new \RuntimeException('MFA_EXPIRY_AUDIT_FAILED');
                $notificationId=$this->notification===null?null:($this->notification)('MFA_EXEMPTION_EXPIRED',(string)$row['u_id'],$correlation,$correlation,['Valid until'=>(string)$row['expires_at'],'Reference'=>(string)$row['change_reference']]);
                $this->pdo->commit();$started=false;$expired++;$notifications+=$notificationId===null?0:1;
            }catch(Throwable $e){if($started&&$this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
        }
        return ['expired'=>$expired,'notifications'=>$notifications];
    }
}
