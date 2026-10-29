<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once $root . '/app/Sync/Contracts/ExternalUserSourceInterface.php';
require_once $root . '/app/Sync/ExternalRowNormalizer.php';
require_once $root . '/app/Sync/Apel/ApelSourceDefinition.php';
require_once $root . '/app/Sync/Apel/ApelSourceConfig.php';
require_once $root . '/app/Sync/Apel/ApelStudentSource.php';

use OneId\App\Sync\Apel\ApelSourceConfig;
use OneId\App\Sync\Apel\ApelStudentSource;

$checks=0;$failed=0;$r=static function(bool$ok,string$label)use(&$checks,&$failed):void{
    $checks++;$failed+=$ok?0:1;echo($ok?'PASS ':'FAIL ').$label.PHP_EOL;
};
$ca=tempnam(sys_get_temp_dir(),'apel-ca-');file_put_contents($ca,"fixture\n");
try {
    $config=new ApelSourceConfig('127.0.0.1',3306,'apel','viewer','private',$ca,5);
    $captured='';
    $source=new ApelStudentSource($config,static function(ApelSourceConfig$_,string$query)use(&$captured):array{
        $captured=$query;return [[
            'DATA1'=>'Student APEL','DATA2'=>'900101-01-1234','DATA3'=>'','DATA4'=>'APEL-001',
            'DATA5'=>'student@example.test','DATA6'=>'Faculty','DATA7'=>'Programme',
            'EXT_DATA_SOURCE_CATEGORY'=>'Pelajar','EXTERNAL_STATUS_CODE'=>'ACTIVE',
        ]];
    });
    $rows=$source->fetchAll();
    $r($captured===ApelStudentSource::FIXED_QUERY,'only the fixed APEL view query is executed');
    $r(str_contains($captured,'FROM vw_oneid_active_students'),'fixed query targets approved view');
    $r($rows[0]['data2']==='900101011234'&&$rows[0]['data4']==='APEL001','identities are canonicalized');
    $r($rows[0]['source_code']==='STUDENT_APEL','source code is application owned');
    $r($rows[0]['ext_data_source_category']==='Pelajar','category remains Pelajar');
    $r($rows[0]['external_status_code']==='ACTIVE','source status is retained for audit');
    try{(new ApelStudentSource($config,static fn():array=>[]))->fetchAll();$empty=false;}
    catch(RuntimeException$e){$empty=$e->getMessage()==='APEL_SOURCE_EMPTY';}
    $r($empty,'empty source fails closed');
    try{(new ApelStudentSource($config,static fn():array=>['bad']))->fetchAll();$bad=false;}
    catch(RuntimeException$e){$bad=$e->getMessage()==='APEL_SOURCE_ROW_INVALID';}
    $r($bad,'invalid row fails closed');
} finally { if(is_string($ca)&&is_file($ca))unlink($ca); }
printf("RESULT checks=%d failures=%d mutation_statements=0\n",$checks,$failed);
exit($failed===0?0:1);
