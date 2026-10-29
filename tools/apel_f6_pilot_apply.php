<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
$root=dirname(__DIR__);require_once $root.'/lib/config.php';require_once $root.'/bootstrap/sync_runtime.php';
use OneId\App\Sync\Adapters\{PdoApelPilotPersistenceAdapter,SecureInitialPasswordFactory};
use OneId\App\Sync\Apel\{ApelPilotConfig,ApelPilotPlanner,ApelPilotWriter,ApelSourceConfig,ApelStudentSource};
use OneId\App\Sync\Contracts\SyncApprovalStoreInterface;use OneId\App\Sync\DTO\SyncApproval;use OneId\App\Sync\Odl\OdlShadowPreviewReader;use OneId\App\Sync\{SyncApprovalService,SyncPlanFingerprinter};
final class ApelF6ApprovalStore implements SyncApprovalStoreInterface{private?SyncApproval$a=null;public function save(SyncApproval$a):void{if($this->a!==null)throw new RuntimeException('APEL_F6_APPROVAL_ALREADY_ISSUED');$this->a=$a;}public function consume(string$id):?SyncApproval{$a=$this->a;$this->a=null;return$a?->approvalId===$id?$a:null;}}
function apelF6Value(string$key):string{try{return trim((string)oneid_secret($key));}catch(Throwable){return'';}}
try{
 if((getenv('ONEID_APEL_F6_EXECUTION')?:'')!=='AUTHORIZE_APEL_F6_PILOT_APPLY')throw new RuntimeException('APEL_F6_EXECUTION_AUTHORIZATION_REQUIRED');
 $change=apelF6Value('ONEID_APEL_PILOT_CHANGE_REFERENCE');$backup=apelF6Value('ONEID_APEL_PILOT_BACKUP_REFERENCE');
 if($change===''||(getenv('ONEID_APEL_F6_CHANGE_REFERENCE')?:'')!==$change)throw new RuntimeException('APEL_F6_CHANGE_REFERENCE_MISMATCH');
 if($backup===''||(getenv('ONEID_APEL_F6_BACKUP_REFERENCE')?:'')!==$backup)throw new RuntimeException('APEL_F6_BACKUP_REFERENCE_MISMATCH');
 $start=apelF6Value('ONEID_APEL_PILOT_WINDOW_START');$end=apelF6Value('ONEID_APEL_PILOT_WINDOW_END');
 if($start===''||$end==='')throw new RuntimeException('APEL_F6_CHANGE_WINDOW_MISSING');
 $now=new DateTimeImmutable('now',new DateTimeZone('Asia/Kuala_Lumpur'));$from=new DateTimeImmutable($start);$until=new DateTimeImmutable($end);
 if($now<$from||$now>$until)throw new RuntimeException('APEL_F6_OUTSIDE_CHANGE_WINDOW');
 $cfg=ApelPilotConfig::fromPrivateRuntime();if(!$cfg->previewEnabled||!$cfg->applyEnabled)throw new RuntimeException('APEL_PILOT_APPLY_NOT_AUTHORIZED');
 $pdo=new PDO(DB_DSN,DB_USERNAME,DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
 $source=new ApelStudentSource(ApelSourceConfig::fromPrivateRuntime());$reader=new OdlShadowPreviewReader($pdo);$planner=new ApelPilotPlanner($cfg->allowedIdentityDigests);
 $fresh=static fn()=>$planner->plan($source->fetchAll(),$reader->users(),$reader->memberships());$plan=$fresh();
 $expectedHash=apelF6Value('ONEID_APEL_PILOT_EXPECTED_PLAN_HASH');if(preg_match('/^[a-f0-9]{64}$/',$expectedHash)!==1||!hash_equals($expectedHash,$plan->planHash()))throw new RuntimeException('APEL_F6_PLAN_HASH_MISMATCH');
 $preview=['status'=>1,'mode'=>'apel_controlled_pilot_preview','can_apply'=>false,'execution_authorized'=>false,'counts'=>$plan->legacyCounts(),'source_rows'=>$plan->sourceRows,'plan_hash'=>$plan->planHash(),'actions'=>$plan->safeProjection(),'mutation_statements'=>0];
 $preview['preview_digest']=hash('sha256',json_encode($preview,JSON_UNESCAPED_SLASHES)?:'');$expectedDigest=apelF6Value('ONEID_APEL_PILOT_EXPECTED_PREVIEW_DIGEST');
 if(preg_match('/^[a-f0-9]{64}$/',$expectedDigest)!==1||!hash_equals($expectedDigest,$preview['preview_digest']))throw new RuntimeException('APEL_F6_PREVIEW_DIGEST_MISMATCH');
 $approval=new SyncApprovalService(new ApelF6ApprovalStore(),new SyncPlanFingerprinter(),300);$admin=(getenv('ONEID_APEL_F6_ADMIN_ID')?:'');if($admin==='')throw new RuntimeException('APEL_F6_ADMIN_ID_REQUIRED');$receipt=$approval->issue($admin,$plan,$plan->sourceRows);
 $result=(new ApelPilotWriter(new PdoApelPilotPersistenceAdapter($pdo),new SecureInitialPasswordFactory()))->applyApproved($fresh,$receipt->approvalId,$admin,$approval,true);
 echo json_encode(['status'=>1,'mode'=>'apel_controlled_pilot_apply','change_reference'=>$change,'backup_reference'=>$backup,'plan_hash'=>$expectedHash,'preview_digest'=>$expectedDigest,'result'=>$result,'automatic_scheduler'=>false,'full_apply'=>false],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),"\n";
}catch(Throwable$e){printf("RESULT applied=no code=%s automatic_scheduler=false full_apply=false\n",preg_replace('/[^A-Z0-9_]/','',$e->getMessage())?:'APEL_F6_APPLY_FAILED');exit(1);}
