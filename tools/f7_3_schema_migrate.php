<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){exit(2);} require_once dirname(__DIR__).'/lib/config.php';
$mode=$argv[1]??'--check';if(!in_array($mode,['--check','--apply'],true)){fwrite(STDERR,"Usage: php tools/f7_3_schema_migrate.php [--check|--apply]\n");exit(2);}
$pdo=new PDO(DB_DSN,DB_USERNAME,DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$columns=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='admin_mfa_factors' AND COLUMN_NAME IN ('enrollment_session_hash','enrollment_browser_digest','last_used_time_step')")->fetchColumn();
$indexes=(int)$pdo->query("SELECT COUNT(DISTINCT INDEX_NAME) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='admin_mfa_factors' AND INDEX_NAME='idx_admin_mfa_factor_replay'")->fetchColumn();
$events=(int)$pdo->query("SELECT COUNT(*) FROM syslog_event_conf WHERE (syslog_event_id=44 AND syslog_event_name='ADMIN_TOTP_ENROLLED') OR (syslog_event_id=45 AND syslog_event_name='ADMIN_TOTP_CONFIRMED') OR (syslog_event_id=46 AND syslog_event_name='ADMIN_TOTP_VERIFIED') OR (syslog_event_id=47 AND syslog_event_name='ADMIN_TOTP_FAILED') OR (syslog_event_id=48 AND syslog_event_name='ADMIN_TOTP_REVOKED') OR (syslog_event_id=49 AND syslog_event_name='ADMIN_TOTP_RECOVERY_USED')")->fetchColumn();
$complete=$columns===3&&$indexes===1&&$events===6;printf("F7_3_SCHEMA columns=%d/3 indexes=%d/1 events=%d/6 mode=%s\n",$columns,$indexes,$events,$mode);
if($mode==='--check'){exit($complete?0:1);} if($complete){exit(0);} if($columns!==0||$indexes!==0||$events!==0){fwrite(STDERR,"FAIL F7_3_PARTIAL_SCHEMA_REQUIRES_MANUAL_RECONCILIATION\n");exit(1);}
if((int)$pdo->query('SELECT admin_2fa_enabled FROM sys_config WHERE singleton_key=1')->fetchColumn()!==0){fwrite(STDERR,"FAIL F7_3_REQUIRES_FEATURE_OFF\n");exit(1);}
if((getenv('ONEID_F7_CHANGE_ID')?:'')!=='ONEID-F7-2FA-20260720-01'){fwrite(STDERR,"FAIL F7_CHANGE_ID_REQUIRED\n");exit(1);}
$evidencePath=getenv('ONEID_F7_BACKUP_EVIDENCE')?:'';$e=is_file($evidencePath)?parse_ini_file($evidencePath,false,INI_SCANNER_RAW):false;$file=is_array($e)?(string)($e['backup_file']??''):'';$hash=is_array($e)?(string)($e['backup_sha256']??''):'';
if(!is_array($e)||($e['restore_completed']??'')!=='yes'||($e['exact_row_count_reconciliation']??'')!=='pass'||($e['restore_target_dropped']??'')!=='yes'||preg_match('/\A[a-f0-9]{64}\z/',$hash)!==1||!is_file($file)||!hash_equals($hash,(string)hash_file('sha256',$file))){fwrite(STDERR,"FAIL F7_BACKUP_EVIDENCE_INVALID\n");exit(1);}
$pdo->exec((string)file_get_contents(dirname(__DIR__).'/docs/migrations/20260720_f7_3_totp_lifecycle_up.sql'));echo "PASS F7.3 TOTP lifecycle schema installed feature_off=1\n";
