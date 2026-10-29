<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
$root=dirname(__DIR__);require_once $root.'/lib/config.php';
try{
 $stamp=(new DateTimeImmutable('now',new DateTimeZone('Asia/Kuala_Lumpur')))->format('Ymd-His');
 $reference='ONEID-UAT-APEL-F6-BACKUP-'.$stamp;$directory=$root.'/storage/backups/apel-f6-'.$stamp;
 if(!mkdir($directory,0700,true)&&!is_dir($directory))throw new RuntimeException('APEL_F6_BACKUP_DIRECTORY_FAILED');chmod($directory,0700);
 $pdo=new PDO(DB_DSN,DB_USERNAME,DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
 $tables=['user_tbl','user_external_identity','user_external_identity_event'];$manifest=['reference'=>$reference,'created_at'=>(new DateTimeImmutable())->format(DATE_ATOM),'tables'=>[]];
 $pdo->beginTransaction();
 foreach($tables as$table){$schema=$pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM);$rows=$pdo->query("SELECT * FROM `$table`")->fetchAll();
  $payload=json_encode(['table'=>$table,'create_sql'=>(string)($schema[1]??''),'rows'=>$rows],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);$file=$directory.'/'.$table.'.json.gz';
  if(file_put_contents($file,gzencode($payload,9),LOCK_EX)===false)throw new RuntimeException('APEL_F6_BACKUP_WRITE_FAILED');chmod($file,0600);
  $manifest['tables'][$table]=['rows'=>count($rows),'file'=>basename($file),'sha256'=>hash_file('sha256',$file)];unset($rows,$payload);
 }
 $pdo->commit();$manifestFile=$directory.'/manifest.json';file_put_contents($manifestFile,json_encode($manifest,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n",LOCK_EX);chmod($manifestFile,0600);
 printf("BACKUP_REFERENCE=%s\nBACKUP_DIRECTORY=%s\nMANIFEST_SHA256=%s\nRESULT backup=ready tables=3\n",$reference,$directory,hash_file('sha256',$manifestFile));
}catch(Throwable$e){if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();printf("RESULT backup=failed code=%s\n",preg_replace('/[^A-Z0-9_]/','',$e->getMessage())?:'APEL_F6_BACKUP_FAILED');exit(1);}
