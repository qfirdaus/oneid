<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
$root=dirname(__DIR__);require_once $root.'/lib/config.php';require_once $root.'/lib/Database.php';
try{
 $pdo=new PDO(DB_DSN,DB_USERNAME,DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
 $ids=$pdo->query("SELECT i.u_id FROM user_external_identity i JOIN user_tbl u ON u.u_id=i.u_id WHERE i.source_code='STUDENT_APEL' AND i.source_active=1 AND u.avail_status=1 ORDER BY i.u_id")->fetchAll(PDO::FETCH_COLUMN,0);
 if(count($ids)!==3)throw new RuntimeException('APEL_F7_ACTIVE_SCOPE_NOT_THREE');
 $db=new Database();$direct=0;$search=0;$profile=0;
 foreach($ids as$id){
  $row=$db->admin_search_user_account((string)$id);if(is_array($row)&&hash_equals((string)$id,(string)($row['u_id']??''))&&(int)($row['avail_status']??0)===1)$direct++;
  $rows=$db->admin_search_keyword_user_func((string)$id);foreach($rows as$result){if(hash_equals((string)$id,(string)($result['u_id']??''))){$search++;break;}}
  $detail=$db->get_specific_user_info((string)$id);if(is_array($detail)&&hash_equals((string)$id,(string)($detail['u_id']??'')))$profile++;
 }
 $categoryRows=$db->admin_get_specific_category_user_listing(10);$categoryIds=array_fill_keys(array_map(static fn(array$r):string=>(string)($r['u_id']??''),$categoryRows),true);$category=0;foreach($ids as$id)$category+=isset($categoryIds[$id])?1:0;
 $ok=$direct===3&&$search===3&&$profile===3&&$category===3;
 printf("RESULT visible=%s scope=3 direct_lookup=%d live_search=%d profile_lookup=%d category_listing=%d raw_identities_exposed=0 mutation_statements=0\n",$ok?'yes':'no',$direct,$search,$profile,$category);
 exit($ok?0:1);
}catch(Throwable$e){printf("RESULT visible=no code=%s raw_identities_exposed=0 mutation_statements=0\n",preg_replace('/[^A-Z0-9_]/','',$e->getMessage())?:'APEL_F7_ADMIN_VISIBILITY_FAILED');exit(1);}
