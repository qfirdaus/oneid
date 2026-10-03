<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli' || !in_array($argv[1]??'', ['--start','--resume','--open','--stop'],true) || posix_geteuid()!==0) exit(2);
$root=dirname(__DIR__,2);
if(realpath($root)!=='/var/www/oneid-uat')exit(2);
require_once $root.'/app/Auth/MobileOidc/bootstrap.php';
try{
 $path=$root.'/.private/mobile-oidc-hosted.php';$config=require $path;
 if($config['environment']!=='staging'||$config['issuer']!=='https://oneid-uat.upnm.edu.my')throw new RuntimeException('STAGING_REQUIRED');
 $pdo=new PDO($config['dsn'],$config['db_user'],$config['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 $target=$pdo->query('SELECT DATABASE() db,@@hostname host')->fetch(PDO::FETCH_ASSOC);
 if($target['db']!=='oneiddb'||$target['host']!=='mysql8-DEV')throw new RuntimeException('TARGET_CHANGED');
 $pdo->exec('SET SESSION innodb_lock_wait_timeout=5');
 if(in_array($argv[1],['--start','--resume','--open'],true)){
  if($argv[1]!=='--open' && $config['enabled']!==false)throw new RuntimeException('PILOT_MUST_BE_OFF');
  if($argv[1]==='--start' && (int)$pdo->query('SELECT COUNT(*) FROM mobile_oidc_records')->fetchColumn()!==0)throw new RuntimeException('FRESH_PILOT_REQUIRED');
  if($argv[1]==='--resume' && (($config['pilot_identifiers']??null)!==['0530-09'] || $config['issuer']!=='https://oneid-uat.upnm.edu.my'))throw new RuntimeException('PREVIOUS_CONTROLLED_PILOT_REQUIRED');
  $names=$pdo->query('SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE()')->fetchAll(PDO::FETCH_COLUMN);
  if(array_diff(\OneId\App\Auth\MobileOidc\LifecycleSchema::triggerNames(),$names))throw new RuntimeException('MISSING_TRIGGER');
  if($argv[1]!=='--open'){
  $q=$pdo->query("SELECT u_id FROM user_tbl WHERE data3='0530-09' AND avail_status=1 AND password_change_required=0");
  if(count($q->fetchAll())!==1)throw new RuntimeException('TEST_ACCOUNT_INVALID');
  }
  umask(0077);$backup=$root.'/.private/mobile-pilot-before-'.gmdate('Ymd-His').'.php';
  if(!copy($path,$backup))throw new RuntimeException('BACKUP_FAILED');
  $pdo->beginTransaction();
  $off=$pdo->query('SELECT observer_enabled FROM mobile_oidc_control WHERE singleton_id=1 FOR UPDATE')->fetchColumn();
  if($argv[1]!=='--open' && (int)$off!==0)throw new RuntimeException('OBSERVER_ALREADY_ACTIVE');
  if((int)$off===0){
  $pdo->exec('UPDATE mobile_oidc_identity_epoch SET deleted=1,security_version=security_version+1');
  $pdo->exec('INSERT INTO mobile_oidc_identity_epoch(u_id,generation) SELECT u_id,HEX(RANDOM_BYTES(24)) FROM user_tbl ON DUPLICATE KEY UPDATE generation=VALUES(generation),security_version=security_version+1,deleted=0');
  }
  $pdo->exec('UPDATE mobile_oidc_control SET observer_enabled=1 WHERE singleton_id=1');$pdo->commit();
  $config['enabled']=true;
  if($argv[1]==='--open'){unset($config['pilot_until'],$config['pilot_identifiers']);$config['access_mode']='open-staging';}
  else{$config['pilot_until']=time()+7200;$config['pilot_identifiers']=['0530-09'];}
  $config['clients']['oneid-uat-controlled-browser']=['https://oneid-uat.upnm.edu.my/mobile-test/callback'];
 }else{
  $config['enabled']=false;unset($config['pilot_until']);
  $pdo->exec('UPDATE mobile_oidc_control SET observer_enabled=0 WHERE singleton_id=1');
 }
 // Snapshot existing config privately; no secrets emitted to console.
 $tmp=$path.'.'.bin2hex(random_bytes(8));umask(0077);
 if(file_put_contents($tmp,"<?php\nreturn ".var_export($config,true).";\n")===false)throw new RuntimeException('WRITE_FAILED');
 chmod($tmp,0640);chgrp($tmp,'www-data');if(!rename($tmp,$path))throw new RuntimeException('RENAME_FAILED');
 if($argv[1]==='--open')echo "PASS: staging enabled for all eligible accounts; no pilot deadline; observer ON.\n";
 else echo $argv[1]!=='--stop'?"PASS: epoch reconciled; observer ON; only pilot identifier/client enabled for two hours.\n":"PASS: hosted and observer OFF.\n";
}catch(Throwable $e){if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();fwrite(STDERR,'STOP: '.get_class($e)."; no credentials printed.\n");exit(1);}
