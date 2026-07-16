<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Admin/SsoConfigurationException.php';
require_once dirname(__DIR__) . '/app/Admin/SsoConfigurationService.php';

use OneId\App\Admin\SsoConfigurationException;
use OneId\App\Admin\SsoConfigurationService;

final class Sc5FakeOperation
{
    public array $stored = ['id'=>1,'token_timeout'=>24,'multi_session'=>1,'password_reset_email_enabled'=>1];
    public array $impact = ['affected_tokens'=>3,'affected_users'=>2,'timeout_tokens'=>2,'multiple_tokens'=>1];
    public int $scheduled = 3;
    public int $commits = 0;
    public int $rollbacks = 0;
    public array $events = [];

    public function get_system_config(): array { return $this->stored; }
    public function get_system_config_for_update(): array { return $this->stored; }
    public function beginTransaction(): bool { return true; }
    public function commit(): bool { $this->commits++; return true; }
    public function rollback(): bool { $this->rollbacks++; return true; }
    public function preview_policy_revocation(string $timeout, bool $reduced, bool $disable): array { return $this->impact; }
    public function update_configuration_by_id(int $id, string $timeout, int $multi): int
    {
        $this->stored = ['id'=>$id,'token_timeout'=>$timeout,'multi_session'=>$multi,'password_reset_email_enabled'=>1];
        return 1;
    }
    public function schedule_policy_revocation(string $timeout, bool $reduced, bool $disable, string $at, string $correlation): int { return $this->scheduled; }
    public function syslog_record(int $event, string $detail, string $ip): int { $this->events[]=$event; return 1; }
}

$checks=0;$failures=0;
$check=static function(bool $ok,string $description)use(&$checks,&$failures):void{$checks++;if(!$ok)$failures++;printf("%s: %s\n",$ok?'PASS':'FAIL',$description);};
$payload=['update_configuration'=>'','token_timeout'=>'12','sso_settings_multi_session'=>'0'];

$fake=new Sc5FakeOperation();$service=new SsoConfigurationService($fake);
$preview=$service->preview(['preview_configuration_update'=>'','token_timeout'=>'12','sso_settings_multi_session'=>'0']);
$check($preview['impact']===$fake->impact&&$preview['grace_minutes']===15,'preview reports exact impact and 15-minute grace');
$result=$service->update($payload,'admin.test','127.0.0.1',['affected_tokens'=>3,'affected_users'=>2]);
$check($result['enforcement']['scheduled_tokens']===3&&$result['enforcement']['grace_minutes']===15,'approved update schedules every affected token');
$check($fake->events===[19,30]&&$fake->commits===1,'configuration and revocation schedule are audited and committed atomically');

$stale=new Sc5FakeOperation();$staleService=new SsoConfigurationService($stale);
try{$staleService->update($payload,'admin.test','127.0.0.1',['affected_tokens'=>2,'affected_users'=>2]);$check(false,'stale preview is rejected');}
catch(SsoConfigurationException $e){$check($e->reason==='SC5_PREVIEW_STALE'&&$stale->rollbacks===1,'stale preview is rejected and rolled back');}

$mismatch=new Sc5FakeOperation();$mismatch->scheduled=2;$mismatchService=new SsoConfigurationService($mismatch);
try{$mismatchService->update($payload,'admin.test','127.0.0.1',['affected_tokens'=>3,'affected_users'=>2]);$check(false,'schedule count mismatch is rejected');}
catch(SsoConfigurationException $e){$check($e->reason==='SC5_SCHEDULE_COUNT_MISMATCH'&&$mismatch->rollbacks===1,'schedule mismatch rolls back configuration and audit');}

printf("RESULT: checks=%d failures=%d\n",$checks,$failures);
exit($failures===0?0:1);
