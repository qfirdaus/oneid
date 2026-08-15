<?php

declare(strict_types=1);

namespace OneId\App\Admin;

final class AdminReportReference
{
    private const SESSION_KEY='oneid_admin_report_refs';
    private const TTL_SECONDS=900;
    private const MAX_REFERENCES=30;

    /** @param array<string,mixed> $session */
    public static function issue(array &$session,string $adminId,string $reportKey,?int $now=null):string
    {
        if(trim($adminId)===''||!AdminReportCatalogue::isReady($reportKey))throw new \InvalidArgumentException('REPORT_NOT_AVAILABLE');
        $now??=time();$references=self::active($session,$now);$token=bin2hex(random_bytes(32));
        $references[$token]=['admin_id'=>trim($adminId),'report_key'=>$reportKey,'expires_at'=>$now+self::TTL_SECONDS];
        if(count($references)>self::MAX_REFERENCES){uasort($references,static fn(array $a,array $b):int=>(int)$a['expires_at']<=>(int)$b['expires_at']);$references=array_slice($references,-self::MAX_REFERENCES,null,true);}
        $session[self::SESSION_KEY]=$references;return $token;
    }

    /** @param array<string,mixed> $session */
    public static function resolve(array &$session,string $token,string $adminId,?int $now=null):string
    {
        if(preg_match('/\A[a-f0-9]{64}\z/',$token)!==1)throw new \InvalidArgumentException('REPORT_REFERENCE_MALFORMED');
        $now??=time();$references=self::active($session,$now);$session[self::SESSION_KEY]=$references;$reference=$references[$token]??null;
        if(!is_array($reference))throw new \RuntimeException('REPORT_REFERENCE_EXPIRED');
        if(!hash_equals((string)$reference['admin_id'],trim($adminId)))throw new \RuntimeException('REPORT_REFERENCE_FORBIDDEN');
        $key=(string)($reference['report_key']??'');if(!AdminReportCatalogue::isReady($key))throw new \RuntimeException('REPORT_NOT_AVAILABLE');return $key;
    }

    /** @param array<string,mixed> $session @return array<string,array<string,mixed>> */
    private static function active(array $session,int $now):array
    {
        $stored=$session[self::SESSION_KEY]??[];if(!is_array($stored))return[];
        return array_filter($stored,static fn(mixed $r):bool=>is_array($r)&&(int)($r['expires_at']??0)>=$now);
    }
}
