<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/app/Auth/SsoTokenLifetimePolicy.php';
require_once dirname(__DIR__) . '/lib/config.php';

use OneId\App\Auth\SsoTokenLifetimePolicy;

$policy = new SsoTokenLifetimePolicy();
$checks=0; $failures=0;
$report=static function(bool $ok,string $label)use(&$checks,&$failures):void{$checks++;if(!$ok)$failures++;printf("%s: %s\n",$ok?'PASS':'FAIL',$label);};
$now='2026-07-16 12:00:00';
$state=static fn(string $issued,float $hours):string=>$policy->evaluate($issued,$now,$hours)['state'];
$report($state('2026-07-16 11:30:01',0.5)===SsoTokenLifetimePolicy::ACTIVE,'token is active one second before absolute expiry');
$report($state('2026-07-16 11:30:00',0.5)===SsoTokenLifetimePolicy::ACTIVE,'token is active at exact absolute expiry boundary');
$report($state('2026-07-16 11:29:59',0.5)===SsoTokenLifetimePolicy::LEGACY_REFRESH,'legacy refresh starts one second after absolute expiry');
$report($state('2026-07-16 10:30:01',0.5)===SsoTokenLifetimePolicy::LEGACY_REFRESH,'legacy refresh remains available one second before window closes');
$report($state('2026-07-16 10:30:00',0.5)===SsoTokenLifetimePolicy::EXPIRED,'token is expired at exact end of legacy refresh window');
$report($state('2026-07-16 12:00:01',0.5)===SsoTokenLifetimePolicy::FUTURE_INVALID,'future-issued token is rejected without hidden clock-skew allowance');

$root=dirname(__DIR__);$db=(string)file_get_contents($root.'/lib/Database.php');$api=(string)file_get_contents($root.'/api.php');$q=(string)file_get_contents($root.'/lib/q_func.php');
$report(str_contains($db,'token_datetime,token_issued_at')&&str_contains($db,'NOW(),NOW()'),'new tokens store immutable issuance and mutable activity timestamps');
$report(str_contains($db,'UPDATE token_tbl SET token_datetime = NOW()')&&!str_contains($db,'UPDATE token_tbl SET token_issued_at = NOW()'),'heartbeat updates activity timestamp without extending issuance');
$report(str_contains($api,"['token_issued_at']")&&str_contains($api,'SsoTokenLifetimePolicy::LEGACY_REFRESH'),'integration API uses centralized absolute lifetime and compatibility refresh states');
$report(substr_count($q,"['token_issued_at']")>=1&&substr_count($q,'SsoTokenLifetimePolicy::ACTIVE')>=1&&str_contains($db,'A.token_issued_at<P.active_cutoff')&&str_contains($db,'admin_list_active_sessions'),'user cleanup and read-only admin listing both use absolute issuance time');

$pdo=new PDO(DB_DSN,DB_USERNAME,DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$nulls=(int)$pdo->query('SELECT COUNT(*) FROM token_tbl WHERE token_issued_at IS NULL')->fetchColumn();
$index=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='token_tbl' AND INDEX_NAME='idx_token_issued_at'")->fetchColumn();
$report($nulls===0&&$index===1,'live token issuance backfill is complete and indexed');
printf("RESULT: checks=%d failures=%d\n",$checks,$failures);exit($failures===0?0:1);
