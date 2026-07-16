<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/config.php';
$mode=$argv[1]??'--check';if(!in_array($mode,['--check','--apply'],true)){fwrite(STDERR,"Usage: php tools/sc5_policy_revocation_runner.php [--check|--apply]\n");exit(2);}
$pdo=new PDO(DB_DSN,DB_USERNAME,DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$due=(int)$pdo->query("SELECT COUNT(*) FROM token_tbl WHERE status=1 AND policy_revoke_at IS NOT NULL AND policy_revoke_at<=NOW()")->fetchColumn();
printf("SC5 due_tokens=%d mode=%s\n",$due,$mode);if($mode==='--check'||$due===0)exit(0);
$pdo->beginTransaction();
try{
  $groups=$pdo->query("SELECT COALESCE(policy_revoke_correlation,'unknown') correlation,COUNT(*) tokens,COUNT(DISTINCT user_id) users FROM token_tbl WHERE status=1 AND policy_revoke_at IS NOT NULL AND policy_revoke_at<=NOW() GROUP BY policy_revoke_correlation FOR UPDATE")->fetchAll(PDO::FETCH_ASSOC);
  $changed=$pdo->exec("UPDATE token_tbl SET status=0 WHERE status=1 AND policy_revoke_at IS NOT NULL AND policy_revoke_at<=NOW()");
  $audit=$pdo->prepare("INSERT INTO syslog(log_type,log_detail,ip_addr,datetime) VALUES(31,:detail,'CLI',NOW())");
  foreach($groups as $group){$audit->execute([':detail'=>sprintf('action=batch_policy_revoke tokens=%d users=%d correlation=%s',(int)$group['tokens'],(int)$group['users'],$group['correlation'])]);}
  $pdo->commit();printf("PASS revoked=%d groups=%d\n",$changed,count($groups));
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();fwrite(STDERR,"FAIL runner transaction rolled back\n");exit(1);}
