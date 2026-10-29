<?php

declare(strict_types=1);
$root=dirname(__DIR__,2);require_once $root.'/app/Sync/Provenance/ProvenanceBackfillPreview.php';
require_once $root.'/app/Sync/Apel/ApelSourceDefinition.php';require_once $root.'/app/Sync/Apel/ApelDataQualityAudit.php';
use OneId\App\Sync\Apel\ApelDataQualityAudit;
$rows=[['data1'=>'Student','data2'=>'900101011234','data4'=>'APEL001','data5'=>'a@example.test','data6'=>'F','data7'=>'P','external_status_code'=>'ACTIVE','ext_data_source_category'=>'Pelajar','source_code'=>'STUDENT_APEL']];
$users=[['u_id'=>'APEL001','data2'=>'900101011234','avail_status'=>1,'account_source'=>'external','sync_protected'=>0]];
$members=[['u_id'=>'APEL001','source_code'=>'STUDENT_ODL_PG','external_user_id'=>'APEL001','source_active'=>1]];
$result=(new ApelDataQualityAudit())->audit($rows,$users,$members);$checks=[
'audit remains read only'=>($result['can_apply']??true)===false&&($result['mutation_statements']??1)===0,
'source is isolated'=>($result['source_code']??'')==='STUDENT_APEL',
'valid identity counted'=>($result['valid_student_identities']??0)===1,
'ODL overlap is visible'=>($result['active_membership_overlap']['STUDENT_ODL_PG']??0)===1,
'personal data absent from output'=>!str_contains(json_encode($result),'900101011234')&&!str_contains(json_encode($result),'APEL001'),
'snapshot digest exists'=>preg_match('/^[a-f0-9]{64}$/',(string)($result['snapshot_digest']??''))===1,
];$failed=0;foreach($checks as$label=>$ok){echo($ok?'PASS ':'FAIL ').$label.PHP_EOL;$failed+=$ok?0:1;}
printf("RESULT checks=%d failures=%d mutation_statements=0\n",count($checks),$failed);exit($failed===0?0:1);
