<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(2);
require_once dirname(__DIR__, 2) . '/bootstrap/sync_runtime.php';

use OneId\App\Sync\Provenance\StaffProvenancePreview;
use OneId\App\Sync\Adapters\SourceScopedSyncPersistenceAdapter;
use OneId\App\Sync\Contracts\SyncPersistenceInterface;

final class IsolationFixture implements SyncPersistenceInterface {
    public array $writes=[];
    public function begin():void{} public function commit():void{}
    public function rollback():void{} public function createHeader(int $type):int{return 1;}
    public function activeUsers():array{return[
        ['u_id'=>'S1','u_category'=>2,'account_source'=>'external','sync_protected'=>0],
        ['u_id'=>'UG1','u_category'=>10,'account_source'=>'external','sync_protected'=>0],
        ['u_id'=>'ODL1','u_category'=>10,'account_source'=>'external','sync_protected'=>0],
        ['u_id'=>'M1','u_category'=>10,'account_source'=>'manual','sync_protected'=>1],
    ];}
    public function inactiveUserIds():array{return[];}
    public function deactivateUser(string $u):void{$this->writes[]='deactivate:'.$u;}
    public function updateUser(string $u,array$r,string$h):void{$this->writes[]='update:'.$u;}
    public function updateHeaderStatus(int$a,int$b,string$c,int$d):void{}
    public function stageExternalUser(int$a,array$b):int{return 1;}
    public function insertExternalUser(array$a,int$b,string$c,string$d):void{$this->writes[]='insert:'.($a['data4']??'');}
    public function markStagedUser(int$a,int$b,int$c):void{} public function appendChanges(array$a):void{}
    public function updateSummary(int$a,int$b,int$c,int$d,int$e,string$f):void{}
    public function header(int$a):array{return[];}
}
$ok=[];$f=new IsolationFixture();
$staff=new SourceScopedSyncPersistenceAdapter($f,[2,3],static fn()=>['S1']);
$ug=new SourceScopedSyncPersistenceAdapter($f,[10,11,12],static fn()=>['UG1']);
$ok['Staff excludes UG and ODL but retains manual collision guard']=
    array_column($staff->activeUsers(),'u_id')===['S1','M1'];
$ok['UG excludes Staff and ODL but retains manual collision guard']=
    array_column($ug->activeUsers(),'u_id')===['UG1','M1'];
$blocked=false;try{$ug->updateUser('ODL1',[],'h');}catch(RuntimeException$e){
    $blocked=$e->getMessage()==='SYNC_SOURCE_OWNERSHIP_VIOLATION';
}
$ok['write boundary rejects cross-source update']=$blocked&&$f->writes===[];
$membership=[];
$guarded=new SourceScopedSyncPersistenceAdapter(
    $f,[10],static fn()=>['UG1'],static fn()=>['UG-INACTIVE'],
    static function(string$user):void{if($user==='ODL1')throw new RuntimeException('collision');},
    static function(string$user,string$external,string$hash)use(&$membership):void{
        $membership=[$user,$external,$hash];
    }
);
$ok['inactive reactivation scope is source-bound']=$guarded->inactiveUserIds()===['UG-INACTIVE'];
$guarded->insertExternalUser(['data4'=>'UG2'],10,'password','hash');
$ok['new account records source membership transactionally']=
    $membership===['UG2','UG2','hash'];
$p=(new StaffProvenancePreview())->preview(
    [['data4'=>'IC1','ext_data_source_category'=>'Akademik']],
    [
        ['u_id'=>'S1','u_category'=>2,'data4'=>'IC1','account_source'=>'external','sync_protected'=>0],
        ['u_id'=>'ODL-SAME','u_category'=>10,'data4'=>'IC1','account_source'=>'external','sync_protected'=>0],
    ],
    []
);
$ok['Staff Preview is aggregate-only candidate']=$p['candidate_memberships']===1
    &&$p['can_apply']===false&&$p['mutation_statements']===0
    &&!str_contains(json_encode($p),'IC1');
$approved=(new StaffProvenancePreview())->candidatesForApprovedBackfill(
    [['data4'=>'IC1','ext_data_source_category'=>'Akademik']],
    [
        ['u_id'=>'S1','u_category'=>2,'data4'=>'IC1','account_source'=>'external','sync_protected'=>0],
        ['u_id'=>'ODL-SAME','u_category'=>10,'data4'=>'IC1','account_source'=>'external','sync_protected'=>0],
    ],
    [],1,(string)$p['plan_digest']
);
$ok['Staff extractor cannot select student account with same identity']=
    count($approved)===1&&$approved[0]['u_id']==='S1';
$hyphen=(new StaffProvenancePreview())->preview(
    [['data4'=>'IC-2','ext_data_source_category'=>'Pentadbiran']],
    [['u_id'=>'IC-2','u_category'=>3,'data4'=>'IC-2','account_source'=>'external','sync_protected'=>0]],
    [['u_id'=>'IC-2','source_code'=>'STAFF_HR','external_user_id'=>'IC2']]
);
$ok['Staff membership reconciliation normalizes user and external IDs']=
    $hyphen['existing_memberships']===1&&$hyphen['membership_conflicts']===0;
$numeric=(new StaffProvenancePreview())->preview(
    [['data4'=>'900101011234','ext_data_source_category'=>'Akademik']],
    [['u_id'=>'900101011234','u_category'=>2,'data4'=>'900101011234','account_source'=>'external','sync_protected'=>0]],
    [['u_id'=>'900101011234','source_code'=>'STAFF_HR','external_user_id'=>'900101011234']]
);
$ok['Numeric-only Staff identity retains string comparison']=
    $numeric['existing_memberships']===1&&$numeric['membership_conflicts']===0;
$failed=0;foreach($ok as$label=>$pass){$failed+=$pass?0:1;printf("%s %s\n",$pass?'PASS':'FAIL',$label);}
printf("RESULT checks=%d failed=%d\n",count($ok),$failed);exit($failed===0?0:1);
