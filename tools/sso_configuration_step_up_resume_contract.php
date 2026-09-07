<?php

$root=dirname(__DIR__);
$dashboard=file_get_contents($root.'/admin/dashboard.php');
$contexts=file_get_contents($root.'/app/Auth/AdminStepUpReturnContext.php');
$checks=[
    'authentication tab is an approved Step-Up return context'=>str_contains($contexts,"'configuration_authentication'")&&str_contains($contexts,"'secondary'=>'#configuration_authentication'"),
    'authentication draft survives Step-Up redirect'=>str_contains($dashboard,'oneid_sso_config_step_up_draft')&&str_contains($dashboard,'return=configuration_authentication'),
    'preview 403 is identified using the server Step-Up code'=>str_contains($dashboard,"xhr.status===403&&code.indexOf('STEP_UP')===0"),
    'successful save clears the retained draft'=>str_contains($dashboard,'clearSsoConfigDraft();'),
    'return flow restores fields and resumes preview'=>str_contains($dashboard,'restoreSsoConfigDraftAfterStepUp')&&str_contains($dashboard,'setTimeout(update_configuration,120)'),
];
$failed=0;
foreach($checks as $description=>$passed){echo ($passed?'PASS ':'FAIL ').$description.PHP_EOL;if(!$passed)$failed++;}
echo 'RESULT checks='.count($checks).' failed='.$failed.PHP_EOL;
exit($failed===0?0:1);
