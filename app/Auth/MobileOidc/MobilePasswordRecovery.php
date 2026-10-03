<?php
declare(strict_types=1);
namespace OneId\App\Auth\MobileOidc;

use PDO;

/** Separate mobile recovery challenges; never reads/writes legacy recovery sessions. */
final class MobilePasswordRecovery
{
    private PdoStateStore $store;
    public function __construct(private PDO $pdo, private PdoIdentitySource $source, private OtpDelivery $delivery, private string $key)
    { $this->store = new PdoStateStore($pdo); }

    private function enabled(): bool
    { return $this->source->available() && (int)$this->pdo->query('SELECT password_reset_email_enabled FROM sys_config WHERE singleton_key=1')->fetchColumn() === 1; }
    private function stamp(array $a): string
    { return hash_hmac('sha256', json_encode([$a['u_password'],$a['data5'],$a['mobile_generation']??null,$a['mobile_version']??null], JSON_THROW_ON_ERROR), $this->key); }
    private function account(array $r): ?array
    {
        $a = isset($r['uid']) ? $this->source->account($r['uid']) : null;
        return $a && (int)$a['avail_status'] === 1 && IdentityResolver::categories($a) !== [] && hash_equals($r['stamp'], $this->stamp($a)) ? $a : null;
    }
    private function rate(string $scope, string $value, int $limit, int $seconds): bool
    {
        $key = 'recovery-rate:'.hash_hmac('sha256', $scope.':'.$value, $this->key);
        $r = $this->store->get($key) ?? ['start'=>time(),'count'=>0];
        if ($r['start']+$seconds <= time()) $r=['start'=>time(),'count'=>0];
        $r['count']=min($limit+1,$r['count']+1); $this->store->put($key,$r);
        return $r['count'] <= $limit;
    }
    private function audit(string $result): void
    { $this->store->put('audit:'.bin2hex(random_bytes(16)), ['event'=>'MOBILE_PASSWORD_RECOVERY','outcome'=>$result,'at'=>time()]); }

    public function begin(string $txId, string $binding, string $agent): ?string
    {
        return $this->store->transaction(function() use($txId,$binding,$agent) {
            if (!$this->enabled()) return null;
            $tx=$this->store->get('tx:'.$txId);
            if (!$tx || $tx['state']!=='PASSWORD' || $tx['expires']<=time() || !hash_equals($tx['binding'],hash_hmac('sha256',$binding."\0".$agent,$this->key))) return null;
            $tx['state']='RECOVERY'; $this->store->put('tx:'.$txId,$tx);
            $id=bin2hex(random_bytes(32));
            $this->store->put('recovery:'.$id,['state'=>'IDENTITY','expires'=>time()+1200,'attempts'=>0,'tx'=>$txId]);
            return $id;
        });
    }

    public function cancel(string $id, string $txId, string $binding, string $agent): bool
    {
        return $this->store->transaction(function() use($id,$txId,$binding,$agent) {
            $r=$this->store->get('recovery:'.$id);
            $tx=$this->store->get('tx:'.$txId);
            if(!$r || ($r['tx']??null)!==$txId || in_array($r['state'],['DONE','CANCELLED'],true)
                || !$tx || $tx['state']!=='RECOVERY'
                || !hash_equals($tx['binding'],hash_hmac('sha256',$binding."\0".$agent,$this->key))) return false;
            // Consume OTP/reset proof before returning to the original authorization.
            $this->store->put('recovery:'.$id,['state'=>'CANCELLED','expires'=>time()]);
            if($tx['expires']<=time() || !$this->source->available()) return false;
            $tx['state']='PASSWORD';$this->store->put('tx:'.$txId,$tx);
            return true;
        });
    }

    public function request(string $id, string $identifier, string $ip): string
    {
        $otp=str_pad((string)random_int(0,999999),6,'0',STR_PAD_LEFT);
        $mail=$this->store->transaction(function() use($id,$identifier,$ip,$otp) {
            $r=$this->store->get('recovery:'.$id);
            if (!$r || $r['state']!=='IDENTITY' || $r['expires']<=time() || !$this->enabled()) return null;
            $r['state']='OTP'; $r['otp_expires']=time()+300; $r['hash']=password_hash($otp,PASSWORD_DEFAULT);
            $this->store->put('recovery:'.$id,$r);
            $identifier=trim($identifier);
            $ipAllowed=$this->rate('ip',$ip,20,900);
            $idAllowed=$this->rate('identifier',strtolower($identifier),5,86400);
            if (!$ipAllowed || !$idAllowed || $identifier==='' || strlen($identifier)>100) return [];
            // Same identity fields used by the web recovery lookup, but reject collisions.
            $q=$this->pdo->prepare('SELECT u_id FROM user_tbl WHERE u_id=:uid OR data2=:identity ORDER BY u_id LIMIT 2 FOR UPDATE');
            $q->execute(['uid'=>$identifier,'identity'=>$identifier]); $ids=$q->fetchAll(PDO::FETCH_COLUMN);
            if(count($ids)!==1) return [];
            $a=$this->source->account((string)$ids[0]);
            if(!$a || (int)$a['avail_status']!==1 || IdentityResolver::categories($a)===[] || !filter_var($a['data5'],FILTER_VALIDATE_EMAIL)) return [];
            $cool=$this->rate('cooldown',$a['u_id'],1,60);
            $daily=$this->rate('account',$a['u_id'],5,86400);
            $q=$this->pdo->prepare('SELECT COUNT(*) total,MAX(otp_create_date) latest FROM otp_codes WHERE u_id=? AND otp_create_date >= NOW()-INTERVAL 1 DAY'); $q->execute([$a['u_id']]);$web=$q->fetch(PDO::FETCH_ASSOC);
            $mobileCount=$this->store->get('recovery-rate:'.hash_hmac('sha256','account:'.$a['u_id'],$this->key))['count'];
            if(!$cool || !$daily || (int)$web['total']+$mobileCount>5 || ($web['latest'] && strtotime($web['latest'])>time()-60)) return [];
            $r['uid']=$a['u_id'];$r['stamp']=$this->stamp($a);$r['delivered']=false;
            $this->store->put('recovery:'.$id,$r);$this->audit('requested');
            return [$a['data5'],$a['data1']];
        });
        if ($mail===null) return 'ERROR';
        if ($mail!==[]) {
            try { $sent=$this->delivery->send($mail[0],$mail[1],$otp); } catch(\Throwable) { $sent=false; }
            $this->store->transaction(function() use($id,$sent) {
                $r=$this->store->get('recovery:'.$id);
                if ($r && $r['state']==='OTP') { $r['delivered']=$sent; $this->store->put('recovery:'.$id,$r); }
                $this->audit($sent?'email_sent':'email_failed');
            });
        }
        return 'OTP'; // Generic result for nonexistent, inactive and throttled identities.
    }

    public function verify(string $id, string $code): string
    {
        return $this->store->transaction(function() use($id,$code) {
            $r=$this->store->get('recovery:'.$id);
            if (!$r || $r['state']!=='OTP' || $r['expires']<=time() || !$this->enabled()) return 'ERROR';
            $r['attempts']++;
            $valid=$r['attempts']<=5 && $r['otp_expires']>time() && preg_match('/^\d{6}$/D',$code) && password_verify($code,$r['hash']) && ($r['delivered']??false) && $this->account($r);
            if($valid){$r['state']='NEW';$r['expires']=min($r['expires'],time()+600);unset($r['hash']);}
            elseif($r['attempts']>=5 || $r['otp_expires']<=time()){$r['state']='FAILED';unset($r['hash']);}
            $this->store->put('recovery:'.$id,$r);
            return $valid?'NEW':($r['state']==='FAILED'?'ERROR':'INVALID');
        });
    }

    public function reset(string $id,string $password,string $confirmation): string
    {
        return $this->store->transaction(function() use($id,$password,$confirmation) {
            $r=$this->store->get('recovery:'.$id);
            if(!$r || $r['state']!=='NEW' || $r['expires']<=time() || !$this->enabled() || !($a=$this->account($r))) return 'ERROR';
            if(!hash_equals($password,$confirmation))return 'MISMATCH';
            if(strlen($password)>72 || !\oneid_validate_new_password($password,$a['u_id'])[0])return 'QUALITY';
            $error=$this->source->changePassword($a,$password);
            if($error!==null)return 'REUSED';
            $this->store->put('recovery:'.$id,['state'=>'DONE','expires'=>time()]);
            $this->audit('completed');
            return 'DONE';
        });
    }
}
