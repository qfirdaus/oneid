<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
$socket=$argv[1]??'';
if(!preg_match('~\A/var/www/oneid-uat/\.private/mobile-oidc-poc/sql-[a-f0-9]{12}/mysql\.sock\z~',$socket)||realpath($socket)!==$socket)exit(2);
require dirname(__DIR__,2).'/lib/auth_security.php';
require dirname(__DIR__,2).'/lib/Database.php';
require dirname(__DIR__,2).'/app/Auth/SsoTokenLifetimePolicy.php';
date_default_timezone_set('Asia/Kuala_Lumpur');
$p=new PDO('mysql:unix_socket='.$socket,'root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$p->exec("SET time_zone='+08:00'");
$p->exec('CREATE DATABASE IF NOT EXISTS legacy_refresh_fixture');$p->exec('USE legacy_refresh_fixture');
class RefreshFixtureDb extends Database {
 public function __construct(PDO $pdo){$this->pdo=$pdo;}
 public function get_system_config(){return ['token_timeout'=>24];}
}
class RefreshFailureDb extends RefreshFixtureDb {
 public function add_new_token($token_id,$user_id,$device){throw new RuntimeException('Synthetic insert failure');}
}
$d=new RefreshFixtureDb($p);$old=str_repeat('a',64);$new=str_repeat('b',64);
if(($argv[2]??'')==='worker'){
 echo json_encode($d->refresh_legacy_token($old,bin2hex(random_bytes(32)),static fn($r)=>true));exit;
}
$p->exec('DROP TABLE IF EXISTS token_tbl,user_tbl');
$p->exec('CREATE TABLE user_tbl(u_id VARCHAR(20) PRIMARY KEY,avail_status INT) ENGINE=InnoDB');
$p->exec('CREATE TABLE token_tbl(token_id VARCHAR(64) PRIMARY KEY,user_id VARCHAR(20),status INT,token_issued_at DATETIME,token_datetime DATETIME,device_info VARCHAR(30),site_id INT,policy_revoke_at DATETIME NULL,ended_at DATETIME NULL,end_reason VARCHAR(40) NULL) ENGINE=InnoDB');
$p->exec("INSERT INTO user_tbl VALUES('fixture',1)");
$reset=static function(int $status=1,int $age=86700,bool $policy=false)use($p,$old){
 $p->exec('DELETE FROM token_tbl');$q=$p->prepare('INSERT INTO token_tbl(token_id,user_id,status,token_issued_at,device_info,policy_revoke_at) VALUES(?,\'fixture\',?,?,\'fixture\',?)');
 $q->execute([oneid_token_hash($old),$status,date('Y-m-d H:i:s',time()-$age),$policy?date('Y-m-d H:i:s',time()-60):null]);
};
$tests=[];$check=static function($name,$ok)use(&$tests){$tests[]=['test'=>$name,'passed'=>$ok];};
$reset();$check('valid refresh commits retirement and replacement',$d->refresh_legacy_token($old,$new,static fn($r)=>true)&&$p->query('SELECT COUNT(*) FROM token_tbl WHERE status=1')->fetchColumn()==1&&$p->query('SELECT COUNT(*) FROM token_tbl')->fetchColumn()==2);
$check('same old token cannot refresh twice',!$d->refresh_legacy_token($old,str_repeat('c',64),static fn($r)=>true));
foreach([['revoked',0,86700,false],['policy_revoked',1,86700,true],['fully_expired',1,90300,false],['still_active',1,300,false],['future',1,-300,false]] as [$name,$status,$age,$policy]){
 $reset($status,$age,$policy);$check($name.' rejected without issuance',!$d->refresh_legacy_token($old,$new,static fn($r)=>true)&&$p->query('SELECT COUNT(*) FROM token_tbl')->fetchColumn()==1);
}
$reset();$check('ACL recheck denies without issuance',!$d->refresh_legacy_token($old,$new,static fn($r)=>false)&&$p->query('SELECT COUNT(*) FROM token_tbl WHERE status=1')->fetchColumn()==1);
$reset();$failed=false;try{(new RefreshFailureDb($p))->refresh_legacy_token($old,$new,static fn($r)=>true);}catch(RuntimeException){$failed=true;}
$check('insert failure rolls back old-token retirement',$failed&&!$p->inTransaction()&&$p->query('SELECT COUNT(*) FROM token_tbl WHERE status=1')->fetchColumn()==1&&$p->query('SELECT COUNT(*) FROM token_tbl')->fetchColumn()==1);
$reset();$children=[];
for($i=0;$i<2;$i++){$pipes=[];$proc=proc_open([PHP_BINARY,__FILE__,$socket,'worker'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fclose($pipes[0]);$children[]=[$proc,$pipes];}
$wins=0;$clean=true;foreach($children as [$proc,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$code=proc_close($proc);$clean=$clean&&$code===0&&$err==='';if(json_decode($out,true)===true)$wins++;}
$check('concurrent refresh issues exactly one replacement',$clean&&$wins===1&&$p->query('SELECT COUNT(*) FROM token_tbl')->fetchColumn()==2&&$p->query('SELECT COUNT(*) FROM token_tbl WHERE status=1')->fetchColumn()==1);
echo json_encode(['runtime'=>PHP_VERSION,'tests'=>$tests]),PHP_EOL;
exit(count(array_filter($tests,static fn($r)=>!$r['passed']))?1:0);
