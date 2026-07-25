<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/lib/locale.php';
require_once dirname(__DIR__, 2) . '/app/Admin/SystemLocaleConfigurationService.php';

$checks=0;$failed=0;
$report=static function(bool $ok,string $label)use(&$checks,&$failed):void{$checks++;echo($ok?'PASS ':'FAIL ').$label.PHP_EOL;if(!$ok)$failed++;};
$ms=require dirname(__DIR__,2).'/config/locales/ms.php';
$en=require dirname(__DIR__,2).'/config/locales/en.php';
$report(array_keys($ms)===array_keys($en),'BM and English catalogue ordered parity');
$report(count(array_filter(array_keys($ms),static fn(string $key):bool=>str_starts_with($key,'admin.')))>=70,'Administrator catalogue coverage');

$operation=new class{
    public array $stored=['id'=>1,'configuration_version'=>7,'default_locale'=>'ms'];
    public array $history=[];public bool $transaction=false;
    public function get_system_config():array{return $this->stored;}
    public function get_system_config_for_update():array{return $this->stored;}
    public function beginTransaction():void{$this->transaction=true;}
    public function commit():void{$this->transaction=false;}
    public function rollBack():void{$this->transaction=false;}
    public function update_default_locale_by_version(int $id,string $locale,int $version):int{if($version!==$this->stored['configuration_version'])return 0;$this->stored['default_locale']=$locale;$this->stored['configuration_version']++;return 1;}
    public function configuration_history_record(array $entry):int{$this->history[]=$entry;return 1;}
};
$service=new \OneId\App\Admin\SystemLocaleConfigurationService($operation);
$status=$service->status();
$report($status['default_locale']==='ms'&&$status['configuration_version']===7,'default locale status is versioned');
$updated=$service->update('en',7,'Approved local ML5 test','admin','127.0.0.1');
$report($updated['code']==='ML5_DEFAULT_LOCALE_UPDATED'&&$operation->stored['default_locale']==='en','guarded default locale update succeeds');
$report(count($operation->history)===1&&$operation->history[0]['action_name']==='UPDATE_SYSTEM_DEFAULT_LOCALE','default locale update is audited');
try{$service->update('fr',8,'Approved local ML5 test','admin','127.0.0.1');$invalid=false;}catch(\RuntimeException $e){$invalid=$e->getMessage()==='ML5_DEFAULT_LOCALE_INVALID';}
$report($invalid,'unapproved locale is rejected');

echo"RESULT checks={$checks} failed={$failed}\n";exit($failed===0?0:1);
