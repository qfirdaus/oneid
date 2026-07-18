<?php

if(PHP_SAPI!=='cli'){exit(2);}require_once dirname(__DIR__,2).'/app/Admin/SessionHousekeepingPolicy.php';
use OneId\App\Admin\SessionHousekeepingPolicy;
$checks=0;$failed=0;$report=static function(bool $ok,string $label)use(&$checks,&$failed):void{$checks++;if(!$ok)$failed++;printf("%s %s\n",$ok?'PASS':'FAIL',$label);};
$report(SessionHousekeepingPolicy::expiryCutoff('2026-07-18 17:30:00',0.5)==='2026-07-18 16:00:00','30-minute lifetime includes the 60-minute refresh window');
$report(SessionHousekeepingPolicy::expiryCutoff('2026-07-18 17:30:00',1)==='2026-07-18 15:30:00','one-hour lifetime includes the 60-minute refresh window');
$report(SessionHousekeepingPolicy::confirmationPhrase(12)==='HOUSEKEEP SESSIONS 12','typed confirmation binds the candidate count');
$report(SessionHousekeepingPolicy::BATCH_SIZE===500,'mutation batch has a fixed upper bound');
$report(SessionHousekeepingPolicy::EXCESSIVE_SESSION_THRESHOLD===5,'excessive sessions are visibility-only at the agreed threshold');
$thrown=false;try{SessionHousekeepingPolicy::expiryCutoff('2026-07-18 17:30:00',0);}catch(InvalidArgumentException){$thrown=true;}
$report($thrown,'invalid token lifetime fails closed');
printf("RESULT checks=%d failed=%d\n",$checks,$failed);exit($failed===0?0:1);
