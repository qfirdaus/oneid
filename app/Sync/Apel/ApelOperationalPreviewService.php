<?php
declare(strict_types=1);
namespace OneId\App\Sync\Apel;

use OneId\App\Sync\Adapters\LegacySyncPolicy;
use OneId\App\Sync\SyncPlanner;
use OneId\App\Sync\SyncSafetyPolicy;

final class ApelOperationalPreviewService
{
    public function __construct(private readonly ApelOperationalConfig$config,private readonly ApelStudentSource$source,private readonly \PDO$pdo){}
    /** @return array<string,mixed> */
    public function preview():array{
        $this->config->assertPreviewEnabled();$rows=$this->source->fetchAll();$active=$this->activeScope();$inactive=$this->inactiveSourceIds();
        $plan=(new SyncPlanner(new LegacySyncPolicy(),true,true))->plan($rows,$active,$inactive);
        $baseline=self::baseline();$decision=(new SyncSafetyPolicy(requiredSourceCode:ApelSourceDefinition::SOURCE_CODE))->assess($rows,$active,$plan,$baseline);
        $blocking=$decision->blockingCodes;$counts=$plan->legacyCounts();
        $out=['status'=>1,'mode'=>'apel_operational_preview','source'=>ApelSourceDefinition::SOURCE_CODE,'can_apply'=>false,
            'apply_enabled'=>$this->config->canApply(),'cron_enabled'=>false,'counts'=>$counts,'source_rows'=>$plan->sourceRows,
            'active_sync_scope'=>count(array_filter($active,static fn(array$r):bool=>($r['account_source']??'')!=='manual'||(int)($r['sync_protected']??0)!==1)),
            'discarded_invalid'=>$plan->discardedInvalid,'discarded_excluded'=>$plan->discardedExcluded,'protected_manual_users'=>$plan->protectedManualUsers,
            'discarded_protected_collisions'=>$plan->discardedProtectedCollisions,'blocking_codes'=>$blocking,'warnings'=>array_values(array_unique(array_merge($plan->warnings,$decision->warnings))),
            'risk_level'=>$blocking===[]?'normal':'blocked','safety_metrics'=>$decision->metrics,'plan_hash'=>$plan->planHash(),'sample'=>$plan->safeProjection(),'mutation_statements'=>0];
        $out['preview_digest']=hash('sha256',json_encode($out,JSON_UNESCAPED_SLASHES)?:'');return$out;
    }
    /** @return list<array<string,mixed>> */
    private function activeScope():array{return$this->pdo->query("SELECT u.u_id,u.u_category,u.avail_status,u.data1,u.data2,u.data3,u.data4,u.data5,u.data6,u.data7,u.data8,u.data9,u.data10,u.data11,u.data12,u.u_changes_hash,'1' source,u.account_source,u.sync_protected
      FROM user_tbl u LEFT JOIN user_external_identity i ON i.u_id=u.u_id AND i.source_code='STUDENT_APEL' AND i.source_active=1
      WHERE u.avail_status=1 AND (i.id IS NOT NULL OR (u.account_source='manual' AND u.sync_protected=1))")->fetchAll();}
    /** @return list<string> */
    private function inactiveSourceIds():array{$q=$this->pdo->query("SELECT i.u_id FROM user_external_identity i JOIN user_tbl u ON u.u_id=i.u_id WHERE i.source_code='STUDENT_APEL' AND u.avail_status=0");return$q->fetchAll(\PDO::FETCH_COLUMN,0)?:[];}
    private static function baseline():int{$raw='';try{$raw=trim((string)\oneid_secret('ONEID_APEL_SHADOW_BASELINE_ROWS'));}catch(\Throwable){}if(preg_match('/^[1-9][0-9]*$/',$raw)!==1)throw new \RuntimeException('APEL_OPERATIONAL_BASELINE_INVALID');return(int)$raw;}
}
