<?php
declare(strict_types=1);
// Deliberately no automatic resume/down DROP: MySQL DDL commits independently.
use OneId\App\Auth\MobileOidc\LifecycleSchema;
$mode=$argv[1]??'--plan';
if (PHP_SAPI!=='cli' || !in_array($mode,['--plan','--check','--apply','--disable-observer'],true)) {
    fwrite(STDERR,"Usage: php mobile-migration.php --plan|--check|--apply|--disable-observer\n"); exit(2);
}
$sql=require __DIR__.'/mobile-schema.php';
if($mode==='--plan') {
    echo "-- Additive production plan, observer OFF. Do not apply historical migrations in bulk.\nDELIMITER $$\n";
    foreach($sql as $statement)echo $statement."$$\n";
    echo "DELIMITER ;\n";exit;
}
$root=dirname(__DIR__,2);$pdo=null;$locked=false;$evidence=null;
try {
    if(realpath($root)!=='/var/www/oneid')throw new RuntimeException('PRODUCTION_ROOT_REQUIRED');
    $load=static function(string $name)use($root):array {
        $path=$root.'/.private/'.$name;
        if(!is_file($path)||is_link($path)||(fileperms($path)&0027)!==0)throw new RuntimeException('PRIVATE_CONFIG_REQUIRED');
        $data=require $path;if(!is_array($data))throw new RuntimeException('PRIVATE_CONFIG_INVALID');return $data;
    };
    $runtime=$load('runtime.php');$hosted=$load('mobile-oidc-hosted.php');$target=$load('production-release-target.php');
    if(($runtime['ONEID_ENVIRONMENT']??'')!=='production'||($hosted['environment']??'')!=='production'
        ||($hosted['enabled']??null)!==false)throw new RuntimeException('PRODUCTION_OFF_REQUIRED');
    foreach(['database_name','database_hostname'] as $key)if(!is_string($target[$key]??null)||$target[$key]==='')throw new RuntimeException('TARGET_NOT_REVIEWED');
    if(!str_starts_with((string)($runtime['ONEID_DB_DSN']??''),'mysql:'))throw new RuntimeException('MYSQL_REQUIRED');
    foreach(['dsn'=>'ONEID_DB_DSN','db_user'=>'ONEID_DB_USERNAME','db_password'=>'ONEID_DB_PASSWORD'] as $a=>$b)
        if(($hosted[$a]??null)!==($runtime[$b]??null))throw new RuntimeException('DATABASE_CONFIG_MISMATCH');
    $pdo=new PDO($runtime['ONEID_DB_DSN'],$runtime['ONEID_DB_USERNAME'],$runtime['ONEID_DB_PASSWORD'],
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_TIMEOUT=>5]);
    $pdo->exec('SET SESSION lock_wait_timeout=5');
    $server=$pdo->query('SELECT DATABASE() db,@@hostname host,@@version version')->fetch(PDO::FETCH_ASSOC);
    if($server['db']!==$target['database_name']||$server['host']!==$target['database_hostname']||!str_starts_with($server['version'],'8.0.'))throw new RuntimeException('TARGET_MISMATCH');
    // Serialize preflight as well as DDL. Not a global lock against other tools: maintenance window required.
    $locked=(int)$pdo->query("SELECT GET_LOCK('oneid_mobile_production_migration',0)")->fetchColumn()===1;
    if(!$locked)throw new RuntimeException('MIGRATION_BUSY');
    if($mode==='--disable-observer') {
        $pdo->exec('UPDATE mobile_oidc_control SET observer_enabled=0 WHERE singleton_id=1');
        $value=$pdo->query('SELECT observer_enabled FROM mobile_oidc_control WHERE singleton_id=1')->fetchColumn();
        if($value===false||(int)$value!==0)throw new RuntimeException('DISABLE_FAILED');
        echo "Observer OFF; policy epoch invalidates existing mobile state. Tables/triggers/subjects retained.\n";exit;
    }
    $required=[
        'user_tbl'=>['u_id','avail_status','password_change_required','u_type','u_category','data2','data3','data4','data5','u_password'],
        'user_mfa_factors'=>['u_id','factor_type','factor_status','encrypted_secret','secret_nonce','key_version'],
        'user_external_identity'=>['u_id','source_code','source_active'],
        'user_login_mfa_exemptions'=>['u_id'],'user_login_mfa_pilot_users'=>['u_id'],
        'user_login_mfa_policy'=>[],'user_login_mfa_category_policy'=>[],
        'user_mfa_policy_change_requests'=>[],'external_source'=>[],
    ];
    $before=[];
    foreach($required as $table=>$columns) {
        $actual=$pdo->query('SHOW COLUMNS FROM `'.$table.'`')->fetchAll(PDO::FETCH_COLUMN);
        if(array_diff($columns,$actual))throw new RuntimeException('SOURCE_COLUMNS_MISSING');
        $ddl=$pdo->query('SHOW CREATE TABLE `'.$table.'`')->fetch(PDO::FETCH_NUM)[1];
        if(!str_contains($ddl,'ENGINE=InnoDB'))throw new RuntimeException('SOURCE_ENGINE_UNSUPPORTED');
        $before[$table]=$ddl;
    }
    $existing=$pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME LIKE 'mobile\\_oidc\\_%'")->fetchAll(PDO::FETCH_COLUMN);
    if($existing)throw new RuntimeException('EXISTING_MOBILE_SCHEMA_REVIEW_REQUIRED');
    $triggers=$pdo->query('SELECT TRIGGER_NAME,EVENT_OBJECT_TABLE,ACTION_STATEMENT FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE()')->fetchAll(PDO::FETCH_ASSOC);
    foreach($triggers as $trigger)if(str_starts_with($trigger['TRIGGER_NAME'],'mobile_oidc_'))throw new RuntimeException('EXISTING_MOBILE_TRIGGER');
    $tooLong=(int)$pdo->query('SELECT COUNT(*) FROM user_tbl WHERE CHAR_LENGTH(u_id)>20 OR u_id IS NULL')->fetchColumn();
    if($tooLong)throw new RuntimeException('IDENTITY_KEY_INCOMPATIBLE');
    echo "CHECK PASS: production target/schema, InnoDB and feature OFF. DDL privileges and backup restore must be verified separately.\n";
    if($mode==='--check')exit;
    if(($target['backup_verified']??false)!==true||!is_string($target['backup_reference']??null)||trim($target['backup_reference'])==='')throw new RuntimeException('VERIFIED_BACKUP_REQUIRED');
    $context=stream_context_create(['http'=>['timeout'=>3,'ignore_errors'=>true]]);
    if(@file_get_contents('http://127.0.0.1:24145/health/ready',false,$context)===false||!preg_match('~^HTTP/\S+ 200\b~',$http_response_header[0]??''))throw new RuntimeException('PROVIDER_NOT_READY');
    umask(0077);$evidence=$root.'/.private/mobile-migration-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(4));
    if(!mkdir($evidence,0700))throw new RuntimeException('EVIDENCE_CREATE_FAILED');
    $save=static function(string $name,mixed $value)use($evidence):void {
        if(file_put_contents($evidence.'/'.$name,json_encode($value,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),LOCK_EX)===false)throw new RuntimeException('EVIDENCE_WRITE_FAILED');
    };
    $save('before.json',['server'=>$server,'source_schema'=>$before,'triggers'=>$triggers,'backup_reference'=>$target['backup_reference']]);
    $save('plan.json',$sql);
    foreach($sql as $index=>$statement) {
        $save('progress.json',['next_statement'=>$index,'state'=>'starting']);
        $pdo->exec($statement);
        $save('progress.json',['completed_statement'=>$index,'state'=>'completed']);
    }
    $names=$pdo->query('SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE()')->fetchAll(PDO::FETCH_COLUMN);
    $observer=$pdo->query('SELECT observer_enabled FROM mobile_oidc_control WHERE singleton_id=1')->fetchColumn();
    if($observer===false||(int)$observer!==0||array_diff(LifecycleSchema::triggerNames(),$names))throw new RuntimeException('POSTCHECK_FAILED');
    $save('SUCCESS.json',['observer_enabled'=>false,'trigger_count'=>count(LifecycleSchema::triggerNames())]);
    echo "APPLIED: 4 additive tables and 28 triggers; observer OFF. No public route activated. Evidence: $evidence\n";
} catch(Throwable $error) {
    // Only controlled reason codes; never leak PDO DSN or server error details into terminal/log.
    $safe=$error instanceof PDOException?'DATABASE_OPERATION_FAILED':$error->getMessage();
    if(!preg_match('/\A[A-Z_]+\z/',$safe))$safe='UNEXPECTED_FAILURE';
    fwrite(STDERR,"STOP: $safe. DDL may be partial; inspect private evidence, do not rerun or drop data blindly.\n");exit(1);
} finally {
    if($locked&&$pdo instanceof PDO)$pdo->query("SELECT RELEASE_LOCK('oneid_mobile_production_migration')");
}
