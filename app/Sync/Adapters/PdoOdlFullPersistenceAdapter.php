<?php
declare(strict_types=1);
namespace OneId\App\Sync\Adapters;

use PDO;

final class PdoOdlFullPersistenceAdapter
{
    private PdoOdlPilotPersistenceAdapter $base;
    public function __construct(private readonly PDO $pdo)
    {
        $this->base=new PdoOdlPilotPersistenceAdapter($pdo);
    }
    public function acquireLock():bool{$q=$this->pdo->prepare("SELECT GET_LOCK('oneid:odl:full',0)");$q->execute();return(int)$q->fetchColumn()===1;}
    public function releaseLock():void{$this->pdo->query("SELECT RELEASE_LOCK('oneid:odl:full')");}
    public function begin():void{$this->pdo->beginTransaction();}
    public function commit():void{$this->pdo->commit();}
    public function rollback():void{if($this->pdo->inTransaction())$this->pdo->rollBack();}
    public function insertStudent(array$row,string$password,string$hash):void{$this->base->insertStudent($row,$password,$hash);}
    public function insertMembership(string$user,string$external,string$hash):void{$this->base->insertMembership($user,$external,$hash);}
    public function appendEvent(string$correlation,string$user,string$external):void{
        $q=$this->pdo->prepare("INSERT INTO user_external_identity_event(
         correlation_id,source_code,u_id,external_user_id,event_type)
         VALUES(:c,'STUDENT_ODL_PG',:u,:e,'FULL_NEW')");
        $q->execute([':c'=>$correlation,':u'=>$user,':e'=>$external]);
        if($q->rowCount()!==1)throw new \RuntimeException('ODL_FULL_EVENT_INSERT_FAILED');
    }
    public function reconciliation(string$correlation):array{
        $q=$this->pdo->prepare("SELECT COUNT(DISTINCT u.u_id) users,
         COUNT(DISTINCT i.id) memberships,COUNT(DISTINCT e.event_id) events
         FROM user_external_identity_event e
         JOIN user_tbl u ON u.u_id=e.u_id
         JOIN user_external_identity i ON i.u_id=e.u_id AND i.source_code=e.source_code
         WHERE e.correlation_id=:c AND e.source_code='STUDENT_ODL_PG'
         AND e.event_type='FULL_NEW'");
        $q->execute([':c'=>$correlation]);$r=$q->fetch(PDO::FETCH_ASSOC)?:[];
        return['users'=>(int)($r['users']??0),'memberships'=>(int)($r['memberships']??0),'events'=>(int)($r['events']??0)];
    }
    public function rollbackCorrelation(string$correlation):int{
        $q=$this->pdo->prepare("SELECT u_id,external_user_id FROM user_external_identity_event
         WHERE correlation_id=:c AND source_code='STUDENT_ODL_PG'
         AND event_type='FULL_NEW' FOR UPDATE");$q->execute([':c'=>$correlation]);
        $rows=$q->fetchAll(PDO::FETCH_ASSOC);if(count($rows)!==50)throw new \RuntimeException('ODL_FULL_ROLLBACK_SCOPE_INVALID');
        foreach($rows as$r){
            $g=$this->pdo->prepare("SELECT COUNT(*) FROM user_tbl u
             JOIN user_external_identity i ON i.u_id=u.u_id
             WHERE u.u_id=:u AND u.u_category=10 AND u.account_source='external'
             AND u.sync_protected=0 AND i.source_code='STUDENT_ODL_PG'
             AND i.external_user_id=:e AND
             (SELECT COUNT(*) FROM user_external_identity x WHERE x.u_id=u.u_id)=1");
            $g->execute([':u'=>$r['u_id'],':e'=>$r['external_user_id']]);
            if((int)$g->fetchColumn()!==1)throw new \RuntimeException('ODL_FULL_ROLLBACK_GUARD_REJECTED');
            $d=$this->pdo->prepare("DELETE FROM user_external_identity WHERE u_id=:u
             AND source_code='STUDENT_ODL_PG' AND external_user_id=:e");
            $d->execute([':u'=>$r['u_id'],':e'=>$r['external_user_id']]);
            $u=$this->pdo->prepare("DELETE FROM user_tbl WHERE u_id=:u AND u_category=10
             AND account_source='external' AND sync_protected=0");
            $u->execute([':u'=>$r['u_id']]);
            if($d->rowCount()!==1||$u->rowCount()!==1)throw new \RuntimeException('ODL_FULL_ROLLBACK_DELETE_MISMATCH');
        }
        $q=$this->pdo->prepare("UPDATE user_external_identity_event
         SET event_type='FULL_ROLLED_BACK',rolled_back_at=NOW()
         WHERE correlation_id=:c AND event_type='FULL_NEW'");$q->execute([':c'=>$correlation]);
        if($q->rowCount()!==50)throw new \RuntimeException('ODL_FULL_ROLLBACK_EVENT_MISMATCH');
        return 50;
    }
}
