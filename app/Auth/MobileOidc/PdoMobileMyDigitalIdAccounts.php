<?php
declare(strict_types=1);
namespace OneId\App\Auth\MobileOidc;
use PDO;
use RuntimeException;
use OneId\App\Auth\MyDigitalId\{MyDigitalIdIdentityProtector,MyDigitalIdVerifiedIdentity,PdoMyDigitalIdIdentityRepository};

/** Mobile matching only. Never creates, moves or replaces the web's one-to-one links. */
final class PdoMobileMyDigitalIdAccounts implements MobileMyDigitalIdAccounts
{
    private PdoMyDigitalIdIdentityRepository $links;
    public function __construct(private PDO $pdo, private MyDigitalIdIdentityProtector $protector)
    { $this->links=new PdoMyDigitalIdIdentityRepository($pdo); }

    public function resolve(MyDigitalIdVerifiedIdentity $verified): array
    {
        $proof=['subject'=>$this->protector->subjectHmac('https://sso.digital-id.my/realms/upnm',$verified->subject),
            'nric'=>$this->protector->nricHmac($verified->nric),'key'=>$this->protector->keyId];
        return $this->links->transactional(function() use($verified,$proof): array {
            $q=$this->pdo->prepare("SELECT u_id FROM user_tbl WHERE avail_status=1 AND
                CASE WHEN TRIM(COALESCE(data3,''))<>''
                THEN REPLACE(REPLACE(TRIM(COALESCE(data4,'')),'-',''),' ','')
                ELSE REPLACE(REPLACE(TRIM(COALESCE(data2,'')),'-',''),' ','') END=:nric
                ORDER BY u_id LIMIT 10 FOR UPDATE");
            $q->execute(['nric'=>$verified->nric]);$ids=$q->fetchAll(PDO::FETCH_COLUMN);
            if(count($ids)>=10)throw new RuntimeException('MOBILE_MYDID_AMBIGUOUS');
            return ['ids'=>array_values(array_filter($ids,fn($id)=>$this->allows((string)$id,$proof))), 'proof'=>$proof];
        });
    }

    public function allows(string $userId,array $proof): bool
    {
        if(!$this->pdo->inTransaction())throw new RuntimeException('MOBILE_MYDID_LOCK_REQUIRED');
        if(($proof['key']??null)!==$this->protector->keyId || !is_string($proof['subject']??null) || !is_string($proof['nric']??null))return false;
        $q=$this->pdo->prepare("SELECT u_id,nric_hmac,hmac_key_id,identity_status FROM user_federated_identity WHERE provider_code='mydigitalid' AND issuer='https://sso.digital-id.my/realms/upnm' AND subject_hmac=:s LIMIT 2 FOR UPDATE");
        $q->execute(['s'=>$proof['subject']]);$links=$q->fetchAll(PDO::FETCH_ASSOC);
        if(count($links)>1)return false;
        $subjectLink=$links[0]??null;
        if($subjectLink && (($subjectLink['identity_status']??'')!=='ACTIVE' || ($subjectLink['hmac_key_id']??'')!==$this->protector->keyId || !hash_equals((string)$subjectLink['nric_hmac'],$proof['nric'])
            || !$this->matches((string)$subjectLink['u_id'],$proof['nric'],false)))return false;
        $q=$this->pdo->prepare("SELECT issuer,subject_hmac,nric_hmac,hmac_key_id,identity_status FROM user_federated_identity WHERE provider_code='mydigitalid' AND u_id=:u LIMIT 2 FOR UPDATE");
        $q->execute(['u'=>$userId]);$userLinks=$q->fetchAll(PDO::FETCH_ASSOC);
        if(count($userLinks)>1)return false;
        $userLink=$userLinks[0]??null;
        if($userLink && (($userLink['identity_status']??'')!=='ACTIVE' || ($userLink['hmac_key_id']??'')!==$this->protector->keyId || ($userLink['issuer']??'')!=='https://sso.digital-id.my/realms/upnm' || !hash_equals((string)$userLink['subject_hmac'],$proof['subject'])
            || !hash_equals((string)$userLink['nric_hmac'],$proof['nric'])))return false;
        return $this->matches($userId,$proof['nric'],true);
    }

    private function matches(string $id,string $expected,bool $active): bool
    {
        $q=$this->pdo->prepare('SELECT avail_status,data2,data3,data4 FROM user_tbl WHERE u_id=:u FOR UPDATE');
        $q->execute(['u'=>$id]);$r=$q->fetch(PDO::FETCH_ASSOC);
        if(!$r || ($active && (int)$r['avail_status']!==1))return false;
        $nric=preg_replace('/[\s-]+/','',(string)$r[trim((string)$r['data3'])!==''?'data4':'data2']);
        return preg_match('/^\d{12}$/D',$nric)===1 && hash_equals($expected,$this->protector->nricHmac($nric));
    }
}
