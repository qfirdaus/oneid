<?php

declare(strict_types=1);$source=(string)file_get_contents(dirname(__DIR__,2).'/tools/apel_f4_source_register.php');$checks=[
'registration is source-only'=>str_contains($source,"INSERT INTO external_source")&&!str_contains($source,'INSERT INTO user_tbl'),
'canonical source is dormant'=>str_contains($source,"'STUDENT_APEL','APEL Student','student','dormant',0,1"),
'apply requires change id'=>str_contains($source,'ONEID-APEL-F4-20261029-01'),
'rollback requires zero memberships'=>str_contains($source,'$members!==0'),
'transaction rollback is present'=>str_contains($source,'beginTransaction')&&str_contains($source,'rollBack'),
];$failed=0;foreach($checks as$label=>$ok){echo($ok?'PASS ':'FAIL ').$label.PHP_EOL;$failed+=$ok?0:1;}printf("RESULT checks=%d failures=%d\n",count($checks),$failed);exit($failed===0?0:1);
