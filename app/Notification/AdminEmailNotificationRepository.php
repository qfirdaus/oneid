<?php

declare(strict_types=1);

namespace OneId\App\Notification;

use DateTimeImmutable;
use PDO;
use Throwable;

final class AdminEmailNotificationRepository
{
    public function __construct(private readonly PDO $pdo) {}

    /** @return array<string,string>|null */
    public function recipient(string $userId): ?array
    {
        $statement=$this->pdo->prepare("SELECT U.u_id,U.data1,U.data5,
            COALESCE((SELECT L.locale FROM user_locale_preference L WHERE L.u_id=U.u_id LIMIT 1),'ms') locale
            FROM user_tbl U WHERE U.u_id=:user_id AND U.avail_status=1 LIMIT 1");
        $statement->execute([':user_id'=>$userId]);$row=$statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row)?array_map(static fn($value)=>(string)$value,$row):null;
    }

    /** @param array<string,mixed> $message */
    public function enqueue(array $message): int
    {
        $sql = 'INSERT INTO admin_email_notification_outbox(
            event_name,recipient_user_id,recipient_email,recipient_name,locale,payload_json,
            idempotency_key,correlation_id,available_at
        ) VALUES(
            :event,:user_id,:email,:name,:locale,:payload,:idempotency,:correlation,:available_at
        ) ON DUPLICATE KEY UPDATE notification_id=LAST_INSERT_ID(notification_id)';
        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            ':event'=>$message['event_name'], ':user_id'=>$message['recipient_user_id'],
            ':email'=>$message['recipient_email'], ':name'=>$message['recipient_name'],
            ':locale'=>$message['locale'], ':payload'=>json_encode($message['payload'], JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
            ':idempotency'=>$message['idempotency_key'], ':correlation'=>$message['correlation_id'],
            ':available_at'=>$message['available_at'],
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /** @return array<string,mixed>|null */
    public function claim(string $token, DateTimeImmutable $now): ?array
    {
        $started = false;
        try {
            $this->pdo->beginTransaction(); $started = true;
            $select = $this->pdo->prepare("SELECT * FROM admin_email_notification_outbox
                WHERE (delivery_status='PENDING' OR (delivery_status='PROCESSING' AND locked_until<:lock_now))
                  AND available_at<=:available_now ORDER BY notification_id LIMIT 1 FOR UPDATE");
            $stamp=$now->format('Y-m-d H:i:s.u'); $select->execute([':lock_now'=>$stamp,':available_now'=>$stamp]);
            $row=$select->fetch(PDO::FETCH_ASSOC);
            if(!is_array($row)){ $this->pdo->commit(); return null; }
            $update=$this->pdo->prepare("UPDATE admin_email_notification_outbox SET delivery_status='PROCESSING',
                attempt_count=attempt_count+1,lock_token=:token,locked_until=:until WHERE notification_id=:id");
            $update->execute([':token'=>$token,':until'=>$now->modify('+2 minutes')->format('Y-m-d H:i:s.u'),':id'=>$row['notification_id']]);
            $this->pdo->commit(); $started=false;
            $row['attempt_count']=(int)$row['attempt_count']+1; $row['lock_token']=$token;
            return $row;
        } catch(Throwable $e){ if($started&&$this->pdo->inTransaction())$this->pdo->rollBack(); throw $e; }
    }

    public function complete(int $id,string $token,string $outcome,?string $errorCode,?string $messageId,int $maxAttempts,int $retrySeconds,string $correlation): void
    {
        if(!in_array($outcome,['SENT','FAILED','SUPPRESSED'],true))throw new AdminEmailNotificationException('NOTIFICATION_OUTCOME_INVALID');
        $this->pdo->beginTransaction();
        try{
            $row=$this->pdo->prepare('SELECT attempt_count FROM admin_email_notification_outbox WHERE notification_id=:id AND lock_token=:token FOR UPDATE');
            $row->execute([':id'=>$id,':token'=>$token]); $attempt=(int)$row->fetchColumn();
            if($attempt<1)throw new AdminEmailNotificationException('NOTIFICATION_CLAIM_STALE');
            $terminal=$outcome!=='FAILED'||$attempt>=$maxAttempts;
            $status=$terminal?$outcome:'PENDING';
            $retryAt=(new DateTimeImmutable('now',new \DateTimeZone('UTC')))->modify('+'.max(1,$retrySeconds).' seconds')->format('Y-m-d H:i:s.u');
            $update=$this->pdo->prepare("UPDATE admin_email_notification_outbox SET delivery_status=:status,
              available_at=IF(:status_check='PENDING',:available_at,available_at),
              sent_at=IF(:sent_status='SENT',UTC_TIMESTAMP(6),sent_at),last_error_code=:error,provider_message_id=:message,
              lock_token=NULL,locked_until=NULL WHERE notification_id=:id AND lock_token=:token");
            $update->execute([':status'=>$status,':status_check'=>$status,':sent_status'=>$status,':available_at'=>$retryAt,':error'=>$errorCode,':message'=>$messageId,':id'=>$id,':token'=>$token]);
            $history=$this->pdo->prepare('INSERT INTO admin_email_notification_delivery_history(notification_id,attempt_number,delivery_outcome,error_code,provider_message_id,correlation_id) VALUES(:id,:attempt,:outcome,:error,:message,:correlation)');
            $history->execute([':id'=>$id,':attempt'=>$attempt,':outcome'=>$outcome,':error'=>$errorCode,':message'=>$messageId,':correlation'=>$correlation]);
            $this->pdo->commit();
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }
}
