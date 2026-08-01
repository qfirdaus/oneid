<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/app/LoginBanner/LoginBannerPersistenceInterface.php';
require_once dirname(__DIR__,2).'/app/LoginBanner/LoginBannerPublicResolver.php';
use OneId\App\LoginBanner\LoginBannerPersistenceInterface;
use OneId\App\LoginBanner\LoginBannerPublicResolver;
final class Lb6Persistence implements LoginBannerPersistenceInterface {
  public array $calls=[];public function __construct(public bool $ready,public array $rows=[]){ }
  public function schemaStatus():array{return['available'=>$this->ready,'tables'=>[]];}
  public function adminList(string $environment):array{return[];}
  public function transactional(callable $operation):mixed{return $operation($this);}
  public function publishedForLocale(string $environment,string $locale,string $effectiveAtUtc):array{$this->calls=[$environment,$locale,$effectiveAtUtc];return $this->rows;}
  public function bannerForUpdate(int $bannerId):?array{return null;}public function localeAssetsForUpdate(int $bannerId,string $environment):array{return[];}public function publishedForUpdate(string $environment):array{return[];}public function latestSuccessfulHistoryForUpdate(int $bannerId,string $environment):?array{return null;}public function insertBanner(array $banner):int{return 0;}public function upsertTranslation(array $translation):int{return 0;}public function insertAsset(array $asset):int{return 0;}public function mapLocaleAsset(int $bannerId,string $environment,string $locale,int $assetId,string $actorId):int{return 0;}public function updateBannerVersioned(int $bannerId,int $expectedVersion,array $changes,string $actorId):int{return 0;}public function recordHistory(array $event):int{return 0;}
}
$name=static fn(int $id):string=>'login_banner_'.str_pad(dechex($id),32,'0',STR_PAD_LEFT).'.webp';$row=static fn(int $id,string $alt):array=>['banner_id'=>$id,'image_filename'=>$name($id),'alt_text'=>$alt,'image_width'=>1600,'image_height'=>800,'byte_size'=>100,'sha256_digest'=>str_repeat('a',64)];$checks=[];
$closedDb=new Lb6Persistence(false,[$row(1,'Banner OneID')]);$closed=(new LoginBannerPublicResolver($closedDb,'staging','/missing','login_banners',static fn()=>true))->resolve('ms','2026-08-01 12:00:00');$checks['dormant schema resolves no public items without querying rows']=$closed===[]&&$closedDb->calls===[];
$db=new Lb6Persistence(true,[$row(1,'Banner Bahasa Melayu'),$row(1,'Duplicate banner'),$row(2,'x'),$row(3,'Banner ketiga')]);$resolver=new LoginBannerPublicResolver($db,'staging','/assets','login_banners',static fn(array $asset):bool=>(int)$asset['banner_id']!==3);$items=$resolver->resolve('ms','2026-08-01 12:00:00');$checks['reader scopes exact environment locale and UTC time']=$db->calls===['staging','ms','2026-08-01 12:00:00'];$checks['invalid alt duplicate and failed asset validation are discarded']=count($items)===1&&$items[0]['banner_id']===1;$checks['immutable URL alt dimensions and layout metadata are deterministic']=$items[0]['src']==='login_banners/'.$name(1)&&$items[0]['alt']==='Banner Bahasa Melayu'&&$items[0]['width']===1600&&$items[0]['height']===800;
$many=[];for($i=1;$i<=7;$i++){$many[]=$row($i,'Approved banner '.$i);}$limited=(new LoginBannerPublicResolver(new Lb6Persistence(true,$many),'production','/assets','login_banners',static fn()=>true))->resolve('en','2026-08-01 12:00:00');$checks['public manifest is capped at five ordered rows']=count($limited)===5&&$limited[0]['banner_id']===1&&$limited[4]['banner_id']===5;
$checks['invalid locale and environment fail closed']=(new LoginBannerPublicResolver(new Lb6Persistence(true,$many),'','/assets'))->resolve('fr','2026-08-01 12:00:00')===[];
$default=(new LoginBannerPublicResolver(new Lb6Persistence(true,[$row(1,'Missing file banner')]),'staging','/definitely-missing'))->resolve('ms','2026-08-01 12:00:00');$checks['default validator rejects missing filesystem assets']=$default===[];
$fail=0;foreach($checks as $label=>$ok){echo($ok?'PASS ':'FAIL ').$label.PHP_EOL;if(!$ok)$fail++;}echo'RESULT checks='.count($checks).' failures='.$fail.PHP_EOL;exit($fail===0?0:1);
