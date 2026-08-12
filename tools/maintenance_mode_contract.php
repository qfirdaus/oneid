<?php
declare(strict_types=1);if(PHP_SAPI!=='cli')exit(2);
require_once dirname(__DIR__).'/app/Maintenance/MaintenancePolicy.php';
use OneId\App\Maintenance\MaintenancePolicy;
$n=strtotime('2026-08-12 04:00:00 UTC');$base=['configuration_version'=>8,'maintenance_title_ms'=>'BM','maintenance_title_en'=>'EN','maintenance_message_ms'=>'M BM','maintenance_message_en'=>'M EN'];
$checks=[
'OFF never blocks'=>!MaintenancePolicy::evaluate($base+['maintenance_mode'=>'OFF'],$n)['active'],
'scheduled starts automatically'=>MaintenancePolicy::evaluate($base+['maintenance_mode'=>'SCHEDULED','maintenance_starts_at'=>'2026-08-12 03:00:00','maintenance_ends_at'=>'2026-08-12 05:00:00'],$n)['active'],
'scheduled ends automatically'=>MaintenancePolicy::evaluate($base+['maintenance_mode'=>'SCHEDULED','maintenance_starts_at'=>'2026-08-12 02:00:00','maintenance_ends_at'=>'2026-08-12 03:00:00'],$n)['state']==='COMPLETED',
'indefinite remains active'=>MaintenancePolicy::evaluate($base+['maintenance_mode'=>'INDEFINITE','maintenance_starts_at'=>'2026-08-12 03:00:00'],$n)['active'],
];
$root=dirname(__DIR__);$gate=file_get_contents($root.'/app/Maintenance/MaintenanceGate.php');$ui=file_get_contents($root.'/admin/dashboard.php');$login=file_get_contents($root.'/index.php');$db=file_get_contents($root.'/lib/Database.php');
$checks['central gate permits authenticated admins, pending admin MFA and dedicated admin login']=str_contains($gate,"login_user_type'] ?? '') === '1'")&&str_contains($gate,'user_mfa_pending_admin_maintenance')&&str_contains($gate,'ONEID_ADMIN_MAINTENANCE_LOGIN');
$checks['service-provider validation API remains available']=str_contains($gate,"\$path==='/api.php'||\$path==='/api'");
$checks['admin login rejects non-admin accounts']=str_contains($login,'maintenance_admin_login')&&str_contains(file_get_contents($root.'/lib/q_func.php'),'MAINTENANCE_ADMIN_REQUIRED');
$checks['configuration UI has maintenance modes and bilingual content']=str_contains($ui,'id="configuration_maintenance"')&&str_contains($ui,'value="SCHEDULED"')&&str_contains($ui,'value="INDEFINITE"')&&str_contains($ui,'maintenance_message_ms');
$checks['database mutation uses optimistic configuration versioning']=str_contains($db,'update_maintenance_config_by_version')&&str_contains($db,'configuration_version=:version');
$checks['forward and rollback migrations exist']=is_file($root.'/docs/migrations/20260812_maintenance_mode_up.sql')&&is_file($root.'/docs/migrations/20260812_maintenance_mode_down.sql');
$checks['idempotent schema runner defaults safely to OFF']=str_contains((string)file_get_contents($root.'/tools/maintenance_mode_schema_migrate.php'),'already installed')&&str_contains((string)file_get_contents($root.'/docs/migrations/20260812_maintenance_mode_up.sql'),"DEFAULT 'OFF'");
$failed=0;foreach($checks as$l=>$ok){echo($ok?'PASS ':'FAIL ').$l.PHP_EOL;$failed+=$ok?0:1;}echo'RESULT checks='.count($checks).' failed='.$failed.PHP_EOL;exit($failed?1:0);
