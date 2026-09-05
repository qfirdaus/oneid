<?php

declare(strict_types=1);

if(PHP_SAPI!=='cli'){exit(2);}
$root=dirname(__DIR__);$service=(string)file_get_contents($root.'/app/Admin/UserMfaExemptionExpiryService.php');$worker=(string)file_get_contents($root.'/tools/user_mfa_exemption_expiry_worker.php');
$checks=[
 'worker is CLI only and environment scoped'=>str_contains($worker,"PHP_SAPI!=='cli'")&&str_contains($worker,"['local','staging','production']"),
 'batch is bounded'=>str_contains($service,'$limit>500')&&str_contains($service,'$expired<$limit'),
 'only due active exemptions are selected'=>str_contains($service,"exemption_status='ACTIVE' AND expires_at<=NOW(6)"),
 'expiry row is serialized and skip locked'=>str_contains($service,'FOR UPDATE SKIP LOCKED'),
 'status audit and outbox share transaction'=>strpos($service,"exemption_status='EXPIRED'")<strpos($service,'INSERT INTO syslog')
    &&strpos($service,'INSERT INTO syslog')<strpos($service,"'MFA_EXEMPTION_EXPIRED'")
    &&strpos($service,"'MFA_EXEMPTION_EXPIRED'")<strrpos($service,'$this->pdo->commit()'),
 'expiry notification uses reference without credential material'=>str_contains($service,"'Valid until'")&&str_contains($service,"'Reference'")
    &&!str_contains($service,'otp_hash')&&!str_contains($service,'token_id'),
 'failure rolls back'=>str_contains($service,'$this->pdo->rollBack()'),
];
$failed=0;foreach($checks as $label=>$ok){printf("%s %s\n",$ok?'PASS':'FAIL',$label);$failed+=$ok?0:1;}printf("RESULT checks=%d failed=%d\n",count($checks),$failed);exit($failed===0?0:1);
