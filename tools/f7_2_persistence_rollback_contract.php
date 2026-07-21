<?php

declare(strict_types=1);

if(PHP_SAPI!=='cli'){exit(2);}require_once dirname(__DIR__).'/lib/config.php';require_once dirname(__DIR__).'/lib/Database.php';
$pdo=new PDO(DB_DSN,DB_USERNAME,DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$admin=$pdo->query("SELECT u_id FROM user_tbl WHERE u_type=1 AND avail_status=1 ORDER BY u_id LIMIT 1")->fetchColumn();if(!is_string($admin)||$admin==='')exit(1);
$challenge=bin2hex(random_bytes(32));$grant=bin2hex(random_bytes(32));$session=hash('sha256','f72-persistence-session');$browser=hash('sha256','f72-persistence-browser');$correlation=bin2hex(random_bytes(8));$op=new Database();$ok=false;
try{$op->beginTransaction();
    $created=$op->admin_step_up_create_email_challenge(['challenge_id'=>$challenge,'admin_user_id'=>$admin,'purpose'=>'ADMIN_ACCESS','otp_hash'=>password_hash('123456',PASSWORD_ARGON2ID),'session_binding_hash'=>$session,'browser_digest'=>$browser,'requesting_ip'=>'127.0.0.1','correlation_id'=>$correlation]);
    $sent=$op->admin_step_up_mark_challenge_sent($challenge);$stats=$op->admin_step_up_request_stats($admin,'ADMIN_ACCESS',$session,'127.0.0.1');$row=$op->admin_step_up_challenge_for_update($challenge);$failed=$op->admin_step_up_record_failed_attempt($challenge);$consumed=$op->admin_step_up_consume_challenge($challenge);
    $granted=$op->admin_step_up_create_grant(['grant_id'=>$grant,'admin_user_id'=>$admin,'session_binding_hash'=>$session,'browser_digest'=>$browser,'purpose'=>'ADMIN_ACCESS','verified_factor'=>'EMAIL_OTP','correlation_id'=>bin2hex(random_bytes(8))]);
    $ok=$created===1&&$sent===1&&is_array($stats)&&(int)$stats['admin_hour']>=1&&is_array($row)&&$row['sent_at']!==null&&$failed===1&&$consumed===1&&$granted===1;
}finally{$op->rollback();}
$q=$pdo->prepare("SELECT (SELECT COUNT(*) FROM admin_step_up_challenges WHERE challenge_id=:challenge)+(SELECT COUNT(*) FROM admin_step_up_grants WHERE grant_id=:grant)");$q->execute([':challenge'=>$challenge,':grant'=>$grant]);$remaining=(int)$q->fetchColumn();
printf("%s persistence_roundtrip=%s rollback_remaining=%d persistent_mutations=0\n",$ok&&$remaining===0?'PASS': 'FAIL',$ok?'yes':'no',$remaining);exit($ok&&$remaining===0?0:1);
