<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
require_once dirname(__DIR__).'/lib/config.php';
require_once dirname(__DIR__).'/lib/readonly_odbc.php';
require_once dirname(__DIR__).'/bootstrap/sync_runtime.php';

const CHANGE='ONEID-SOURCE-ISOLATION-20260723-01';
const BACKUP='ONEID-UAT-BACKUP-20260723-02';
const DIGEST='25bbb78c5ab62b980064adf1c79a2a5d686df491ca7230f2fca636e6c5cb3c94';
const EXPECTED=1061;
try{
 if((getenv('ONEID_STAFF_BACKFILL_EXECUTION')?:'')!=='AUTHORIZE_STAFF_HR_BACKFILL'
  ||(getenv('ONEID_STAFF_BACKFILL_CHANGE')?:'')!==CHANGE
  ||(getenv('ONEID_STAFF_BACKFILL_BACKUP')?:'')!==BACKUP)
  throw new RuntimeException('STAFF_BACKFILL_AUTHORIZATION_REQUIRED');
 $now=new DateTimeImmutable('now',new DateTimeZone('Asia/Kuala_Lumpur'));
 $start=new DateTimeImmutable('2026-07-23 23:45:00 Asia/Kuala_Lumpur');
 $end=new DateTimeImmutable('2026-07-23 23:59:00 Asia/Kuala_Lumpur');
 if($now<$start||$now>$end)throw new RuntimeException('STAFF_BACKFILL_OUTSIDE_WINDOW');
 $pdo=new PDO(DB_DSN,DB_USERNAME,DB_PASSWORD,[
  PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
 ]);
 $source=(new \OneId\App\Sync\Odl\StaffSource())->fetchAll();
 $users=$pdo->query('SELECT u_id,u_category,data4,avail_status,account_source,sync_protected FROM user_tbl')->fetchAll();
 $members=$pdo->query('SELECT u_id,source_code,external_user_id FROM user_external_identity')->fetchAll();
 $previewer=new \OneId\App\Sync\Provenance\StaffProvenancePreview();
 $candidates=$previewer->candidatesForApprovedBackfill($source,$users,$members,EXPECTED,DIGEST);
 $userFingerprint=static function(PDO$p):array{
  $row=$p->query("SELECT COUNT(*) rows_count,
   COALESCE(SUM(CRC32(CONCAT_WS('|',u_id,u_category,avail_status,
    account_source,sync_protected,u_changes_hash))),0) checksum_value
   FROM user_tbl")->fetch();
  return [(int)$row['rows_count'],(string)$row['checksum_value']];
 };
 $before=$userFingerprint($pdo);
 $pdo->beginTransaction();
 $pdo->exec("INSERT INTO external_source(source_code,source_name,source_family,
  lifecycle_state,is_required,avail_status) VALUES
  ('STAFF_HR','Human Resources Staff','staff','dormant',0,1)");
 $insert=$pdo->prepare("INSERT INTO user_external_identity(
  u_id,source_code,external_user_id,source_active,source_hash,
  first_seen_at,last_seen_at,last_sync_at) VALUES(
  :u_id,'STAFF_HR',:external,1,:hash,NOW(),NOW(),NOW())");
 foreach($candidates as$c)$insert->execute([
  ':u_id'=>$c['u_id'],':external'=>$c['external_user_id'],':hash'=>$c['source_hash']
 ]);
 $count=(int)$pdo->query("SELECT COUNT(*) FROM user_external_identity
  WHERE source_code='STAFF_HR' AND source_active=1")->fetchColumn();
 $after=$userFingerprint($pdo);
 if($count!==EXPECTED||$before!==$after)throw new RuntimeException('STAFF_BACKFILL_RECONCILIATION_MISMATCH');
 $pdo->commit();
 echo json_encode(['status'=>1,'source'=>'STAFF_HR','registered'=>'dormant',
  'memberships'=>$count,'user_table_unchanged'=>true,'change_id'=>CHANGE,
  'backup_reference'=>BACKUP,'plan_digest'=>DIGEST,
  'user_mutations'=>0,'scheduler'=>false],JSON_PRETTY_PRINT),PHP_EOL;
}catch(Throwable$e){
 if(isset($pdo)&&$pdo instanceof PDO&&$pdo->inTransaction())$pdo->rollBack();
 printf("RESULT applied=no code=%s user_mutations=0 scheduler=false\n",
  preg_replace('/[^A-Z0-9_]/','',$e->getMessage())?:'STAFF_BACKFILL_FAILED');
 exit(1);
}
