<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
require_once dirname(__DIR__).'/lib/config.php';
require_once dirname(__DIR__).'/lib/readonly_odbc.php';
require_once dirname(__DIR__).'/bootstrap/sync_runtime.php';
const F8_CHANGE='ONEID-ODL-F8-20260724-01';
const F8_BACKUP='ONEID-UAT-BACKUP-20260724-01';
const F8_PLAN='4215ca6cae2b56374a4c3df591483b8dc076fc78a51a9f4336c84f544255ac17';
const F8_PREVIEW='f3cfc6855a01769490eba24dee1ea696d58a23527d45fbacd73a70110b03529e';
try{
 if((getenv('ONEID_ODL_F8_EXECUTION')?:'')!=='AUTHORIZE_F8_CONTROLLED_FULL_APPLY'
  ||(getenv('ONEID_ODL_F8_CHANGE_ID')?:'')!==F8_CHANGE
  ||(getenv('ONEID_ODL_F8_BACKUP')?:'')!==F8_BACKUP)
  throw new RuntimeException('ODL_F8_EXECUTION_AUTHORIZATION_REQUIRED');
 $now=new DateTimeImmutable('now',new DateTimeZone('Asia/Kuala_Lumpur'));
 if($now<new DateTimeImmutable('2026-07-24 00:30:00 Asia/Kuala_Lumpur')
  ||$now>new DateTimeImmutable('2026-07-24 01:00:00 Asia/Kuala_Lumpur'))
  throw new RuntimeException('ODL_F8_OUTSIDE_CHANGE_WINDOW');
 $config=\OneId\App\Sync\Odl\OdlFullConfig::fromPrivateRuntime();
 if(!$config->previewEnabled||!$config->applyEnabled)
  throw new RuntimeException('ODL_FULL_APPLY_NOT_AUTHORIZED');
 $pdo=new PDO(DB_DSN,DB_USERNAME,DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
 $source=new \OneId\App\Sync\Odl\OdlStudentSource(\OneId\App\Sync\Odl\OdlSourceConfig::fromPrivateRuntime());
 $reader=new \OneId\App\Sync\Odl\OdlShadowPreviewReader($pdo);
 $fresh=static fn()=>(new \OneId\App\Sync\Odl\OdlFullPlanner())->plan(
  $source->fetchAll(),$reader->users(),$reader->memberships(),$config);
 $plan=$fresh();if(!hash_equals(F8_PLAN,$plan->planHash()))
  throw new RuntimeException('ODL_F8_PLAN_HASH_MISMATCH');
 $preview=['status'=>1,'mode'=>'odl_controlled_full_preview','can_apply'=>false,
  'execution_authorized'=>false,'source'=>'STUDENT_ODL_PG',
  'source_rows'=>$plan->sourceRows,'counts'=>$plan->counts(),
  'plan_hash'=>$plan->planHash(),'actions'=>$plan->safeActions(),
  'blocking_codes'=>[],'risk_level'=>'normal','mutation_statements'=>0,
  'automatic_scheduler'=>false];
 $preview['preview_digest']=hash('sha256',json_encode($preview,JSON_UNESCAPED_SLASHES)?:'');
 if(!hash_equals(F8_PREVIEW,$preview['preview_digest']))
  throw new RuntimeException('ODL_F8_PREVIEW_DIGEST_MISMATCH');
 $constraint=(string)$pdo->query("SELECT CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='chk_external_identity_event_type'")->fetchColumn();
 if(!str_contains($constraint,'FULL_NEW'))throw new RuntimeException('ODL_F8_EVENT_SCHEMA_REQUIRED');
 $writer=new \OneId\App\Sync\Adapters\PdoOdlFullPersistenceAdapter($pdo);
 if(!$writer->acquireLock())throw new RuntimeException('ODL_FULL_ALREADY_RUNNING');
 $started=false;
 try{
  $plan=$fresh();if(!hash_equals(F8_PLAN,$plan->planHash()))
   throw new RuntimeException('ODL_F8_FRESH_PLAN_MISMATCH');
  $correlation=bin2hex(random_bytes(8));
  $before=[
   'users'=>(int)$pdo->query("SELECT COUNT(*) FROM user_tbl")->fetchColumn(),
   'odl'=>(int)$pdo->query("SELECT COUNT(*) FROM user_external_identity WHERE source_code='STUDENT_ODL_PG'")->fetchColumn(),
   'other'=>(int)$pdo->query("SELECT COUNT(*) FROM user_external_identity WHERE source_code<>'STUDENT_ODL_PG'")->fetchColumn(),
  ];
  $writer->begin();$started=true;
  $passwords=new \OneId\App\Sync\Adapters\SecureInitialPasswordFactory();
  foreach($plan->newActions as$a){
   $writer->insertStudent($a['row'],$passwords->createHash(),$a['change_hash']);
   $writer->insertMembership($a['u_id'],$a['u_id'],$a['change_hash']);
   $writer->appendEvent($correlation,$a['u_id'],$a['u_id']);
  }
  $actual=$writer->reconciliation($correlation);
  $after=[
   'users'=>(int)$pdo->query("SELECT COUNT(*) FROM user_tbl")->fetchColumn(),
   'odl'=>(int)$pdo->query("SELECT COUNT(*) FROM user_external_identity WHERE source_code='STUDENT_ODL_PG'")->fetchColumn(),
   'other'=>(int)$pdo->query("SELECT COUNT(*) FROM user_external_identity WHERE source_code<>'STUDENT_ODL_PG'")->fetchColumn(),
  ];
  if($actual!==['users'=>50,'memberships'=>50,'events'=>50]
   ||$after!==['users'=>$before['users']+50,'odl'=>$before['odl']+50,'other'=>$before['other']])
   throw new RuntimeException('ODL_F8_RECONCILIATION_MISMATCH');
  $writer->commit();$started=false;
  echo json_encode(['status'=>1,'mode'=>'odl_controlled_full_apply',
   'correlation_id'=>$correlation,'new'=>50,'memberships'=>50,'events'=>50,
   'source_rows'=>53,'keep'=>3,'other_actions'=>0,'plan_hash'=>F8_PLAN,
   'preview_digest'=>F8_PREVIEW,'backup_reference'=>F8_BACKUP,
   'automatic_scheduler'=>false,'production'=>false],JSON_PRETTY_PRINT),PHP_EOL;
 }catch(Throwable$e){if($started)$writer->rollback();throw$e;}
 finally{$writer->releaseLock();}
}catch(Throwable$e){
 printf("RESULT applied=no code=%s scheduler=false production=false\n",
  preg_replace('/[^A-Z0-9_]/','',$e->getMessage())?:'ODL_F8_APPLY_FAILED');exit(1);
}
