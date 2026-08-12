<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit(2);
$source=(string)file_get_contents(__DIR__.'/retire_duplicate_identity_0526_09.php');$checks=[
 'targets exact legacy and canonical identities'=>str_contains($source,"const LEGACY_USER_ID = '0526-09'")&&str_contains($source,"const CANONICAL_USER_ID = '790612146270'"),
 'defaults to read-only audit mode'=>str_contains($source,"in_array('--apply', \$argv, true)")&&str_contains($source,"if (!\$apply)"),
 'requires inactive non-admin legacy and active canonical admin'=>str_contains($source,"legacy identity must already be inactive")&&str_contains($source,"canonical identity does not have administrator access"),
 'retires credential and protects legacy from sync'=>str_contains($source,"account_source='manual',sync_protected=1")&&str_contains($source,'password_hash(bin2hex(random_bytes(32))'),
 'does not migrate canonical tokens or historical references'=>!str_contains($source,'SET user_id=')&&!str_contains($source,'DELETE FROM'),
 'uses a transaction and records an audit event'=>str_contains($source,'beginTransaction()')&&str_contains($source,'DUPLICATE_IDENTITY_RETIRED')&&str_contains($source,'rollBack()'),
];$failed=0;foreach($checks as$label=>$ok){echo($ok?'PASS ':'FAIL ').$label.PHP_EOL;$failed+=$ok?0:1;}echo'RESULT checks='.count($checks).' failed='.$failed.PHP_EOL;exit($failed?1:0);
