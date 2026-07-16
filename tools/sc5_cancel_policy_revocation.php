<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/config.php';
$correlation=(string)($argv[1]??'');$mode=$argv[2]??'--check';
if(!preg_match('/^[a-f0-9]{16}$/',$correlation)||!in_array($mode,['--check','--apply'],true)){fwrite(STDERR,"Usage: php tools/sc5_cancel_policy_revocation.php <16-char-correlation> [--check|--apply]\n");exit(2);}
$pdo=new PDO(DB_DSN,DB_USERNAME,DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$q=$pdo->prepare("SELECT COUNT(*) FROM token_tbl WHERE status=1 AND policy_revoke_correlation=:c AND policy_revoke_at>NOW()");$q->execute([':c'=>$correlation]);$count=(int)$q->fetchColumn();printf("SC5 cancellable=%d correlation=%s mode=%s\n",$count,$correlation,$mode);if($mode==='--check'||$count===0)exit(0);
$pdo->beginTransaction();try{$u=$pdo->prepare("UPDATE token_tbl SET policy_revoke_at=NULL,policy_revoke_correlation=NULL WHERE status=1 AND policy_revoke_correlation=:c AND policy_revoke_at>NOW()");$u->execute([':c'=>$correlation]);if($u->rowCount()!==$count)throw new RuntimeException('count mismatch');$a=$pdo->prepare("INSERT INTO syslog(log_type,log_detail,ip_addr,datetime) VALUES(32,:d,'CLI',NOW())");$a->execute([':d'=>"action=cancel_policy_revocation tokens={$count} correlation={$correlation}"]);$pdo->commit();echo "PASS cancellation applied\n";}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();fwrite(STDERR,"FAIL cancellation rolled back\n");exit(1);}
