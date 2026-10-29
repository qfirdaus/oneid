<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
$root=dirname(__DIR__);require_once $root.'/lib/config.php';require_once $root.'/lib/Database.php';
try{
 $pdo=new PDO(DB_DSN,DB_USERNAME,DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
 $rows=$pdo->query("SELECT u.u_id,u.u_category,u.avail_status,u.account_source,u.sync_protected
  FROM user_external_identity i JOIN user_tbl u ON u.u_id=i.u_id
  WHERE i.source_code='STUDENT_APEL' AND i.source_active=1 ORDER BY u.u_id")->fetchAll();
 if(count($rows)!==3)throw new RuntimeException('APEL_F7_ACL_SCOPE_NOT_THREE');
 $db=new Database();$category=$db->admin_get_active_user_category(10);
 if(!is_array($category)||(int)($category['uc_id']??0)!==10)throw new RuntimeException('APEL_F7_STUDENT_CATEGORY_INACTIVE');
 $groupApps=$db->specfic_user_get_sp_list_by_group(10);$groupIds=array_values(array_unique(array_map(static fn(array$r):string=>(string)($r['sp_id']??''),$groupApps)));sort($groupIds,SORT_STRING);
 $validProfiles=0;$zeroDirect=0;$zeroDenied=0;$catalogConsistent=0;$effectiveConsistent=0;
 foreach($rows as$row){$id=(string)$row['u_id'];
  if((int)$row['u_category']===10&&(int)$row['avail_status']===1&&$row['account_source']==='external'&&(int)$row['sync_protected']===0)$validProfiles++;
  $direct=$db->admin_get_user_direct_acl_ids($id);$denied=$db->admin_get_user_blacklist_rows($id);if($direct===[])$zeroDirect++;if($denied===[])$zeroDenied++;
  $catalog=$db->admin_get_user_app_access_catalog($id,10);$catalogGroup=[];$catalogDirect=0;$catalogDenied=0;
  foreach($catalog as$app){if((int)($app['category_allow']??0)===1&&(int)($app['avail_status']??0)===1)$catalogGroup[]=(string)$app['sp_id'];$catalogDirect+=(int)($app['direct_allow']??0);$catalogDenied+=(int)($app['denied']??0);}
  $catalogGroup=array_values(array_unique($catalogGroup));sort($catalogGroup,SORT_STRING);if($catalogGroup===$groupIds&&$catalogDirect===0&&$catalogDenied===0)$catalogConsistent++;
  $allEffective=true;foreach($groupIds as$spId){if(!$db->userHasEffectiveAppAccess($id,$spId)){$allEffective=false;break;}}if($allEffective)$effectiveConsistent++;
 }
 $ok=$validProfiles===3&&$zeroDirect===3&&$zeroDenied===3&&$catalogConsistent===3&&$effectiveConsistent===3&&count($groupIds)>0;
 printf("RESULT acl_valid=%s scope=3 student_category=%d inherited_apps=%d valid_profiles=%d zero_direct_grants=%d zero_blacklists=%d admin_catalog_consistent=%d effective_access_consistent=%d raw_identities_exposed=0 mutation_statements=0\n",$ok?'yes':'no',(int)$category['uc_id'],count($groupIds),$validProfiles,$zeroDirect,$zeroDenied,$catalogConsistent,$effectiveConsistent);
 exit($ok?0:1);
}catch(Throwable$e){printf("RESULT acl_valid=no code=%s raw_identities_exposed=0 mutation_statements=0\n",preg_replace('/[^A-Z0-9_]/','',$e->getMessage())?:'APEL_F7_ACL_FAILED');exit(1);}
