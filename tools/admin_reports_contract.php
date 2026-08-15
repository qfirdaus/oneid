<?php

declare(strict_types=1);

$root=dirname(__DIR__);
require_once $root.'/app/Admin/AdminReportCatalogue.php';
require_once $root.'/app/Admin/AdminReportReference.php';

$dashboard=(string)file_get_contents($root.'/admin/dashboard.php');
$preview=(string)file_get_contents($root.'/admin/report_preview.php');
$qfunc=(string)file_get_contents($root.'/lib/q_func.php');
$security=(string)file_get_contents($root.'/lib/request_security.php');
$database=(string)file_get_contents($root.'/lib/Database.php');
$english=(array)require $root.'/config/locales/en.php';
$malay=(array)require $root.'/config/locales/ms.php';
$groups=\OneId\App\Admin\AdminReportCatalogue::groups();
$ready=[];
foreach($groups as $group){foreach($group['reports'] as $report){if($report['status']==='ready'){$ready[]=$report['key'];}}}

$checks=[];
$checks['catalogue exposes exactly six report groups']=count($groups)===6;
$checks['all eighteen approved reports are implemented']=$ready===['executive_summary','security_summary','sync_summary','users_by_category','access_matrix','access_exceptions','application_readiness','application_acl_coverage','credential_rotation','session_activity','device_summary','mfa_adoption','sync_runs','sync_changes','sync_exceptions','audit_activity','configuration_changes','content_changes'];
$checks['admin sidebar and report workspace are installed']=str_contains($dashboard,'id="tab_reports_menu"')&&str_contains($dashboard,'id="tab_reports"');
$checks['report catalogue uses Bootstrap-compatible six-tab navigation']=str_contains($dashboard,'<ul class="nav admin-report-tabs"')&&str_contains($dashboard,'<li class="<?=$report_group_index===0?\'active\':\'\'?>" role="presentation">')&&count($groups)===6;
$checks['every catalogue table has number name and action columns']=str_contains($dashboard,"oneid_translate('admin.reports.number')")&&str_contains($dashboard,"oneid_translate('admin.reports.report_name')")&&str_contains($dashboard,"oneid_translate('admin.reports.action')");
$checks['only implemented reports expose view buttons']=substr_count($dashboard,'class="admin-report-view"')===1&&str_contains($dashboard,"\$report_ready");
$checks['preview reference endpoint is admin guarded']=str_contains($security,"'admin_issue_report_preview'")&&str_contains($qfunc,"isset(\$_POST['admin_issue_report_preview'])");
$checks['browser receives only an opaque report reference']=str_contains($qfunc,"'./report_preview.php?ref='")&&!str_contains($qfunc,'report_preview.php?report_key=');
$checks['preview enforces admin step-up and no-store response']=str_contains($preview,"'ADMIN_ACCESS'")&&str_contains($preview,'Cache-Control: no-store');
$providers=['executive_summary','security_summary','sync_summary','users_by_category','category_access_matrix','access_exceptions','application_readiness','application_acl_coverage','site_api_credential_rotation','session_activity','device_summary','mfa_adoption','sync_runs','sync_changes','sync_exceptions','audit_activity','configuration_changes','content_changes'];
$checks['preview uses allowlisted report keys and eighteen data providers']=str_contains($preview,'AdminReportReference::resolve')&&array_reduce($providers,static fn(bool $ok,string $provider):bool=>$ok&&str_contains($preview,'admin_report_'.$provider),true);
$checks['database provides eighteen read-only report queries']=array_reduce($providers,static fn(bool $ok,string $provider):bool=>$ok&&str_contains($database,'function admin_report_'.$provider),true);
$checks['public report preview wrapper is installed']=is_file($root.'/public/admin/report_preview.php')&&str_contains((string)file_get_contents($root.'/public/admin/report_preview.php'),"'/admin/report_preview.php'");
$checks['report preview supports print without exposing credentials']=str_contains($preview,'window.print()')&&!str_contains($preview,'site_api_code_hash')&&!str_contains($preview,'credential_ciphertext');
$checks['report navigation remains one row at desktop width']=str_contains($dashboard,'grid-template-columns:repeat(6,minmax(0,1fr))');
$checks['Bootstrap clearfix cannot consume report tab grid columns']=str_contains($dashboard,'.admin-report-tabs:before')&&str_contains($dashboard,'.admin-report-tabs:after')&&str_contains($dashboard,'content:none');
$checks['report preview loads the shared environment banner stylesheet']=str_contains($preview,'oneid-environment-banner.css');
$checks['all report previews match the OneID content width']=str_contains($preview,'.shell{max-width:1280px');
$checks['application readiness preview uses compact fixed columns and single-line cells']=str_contains($preview,'.compact-report{font-size:11px;table-layout:fixed;min-width:0}')&&str_contains($preview,'text-overflow:ellipsis;white-space:nowrap')&&str_contains($preview,'title="<?=$escape($cell)?>"');
$checks['Users and Access previews use compact fixed report columns']=str_contains($preview,'.access-matrix col:nth-child(5)')&&str_contains($preview,'.access-exceptions col:nth-child(7)')&&str_contains($preview,"'access_matrix'=>' access-matrix'")&&str_contains($preview,"'access_exceptions'=>' access-exceptions'");
$checks['all report tables retain compact single-line printable columns']=str_contains($preview,'.table-wrap table{font-size:11px;table-layout:fixed;min-width:0}')&&str_contains($preview,'text-overflow:ellipsis;vertical-align:top;white-space:nowrap}');
$checks['all report headers and cells are consistently left and top aligned']=str_contains($preview,'.table-wrap th,.table-wrap td{overflow:hidden;text-align:left;text-overflow:ellipsis;vertical-align:top;white-space:nowrap}')&&!str_contains($preview,'{text-align:center}');
$checks['credential report exposes safe rotation metadata without credential material']=str_contains($preview,'credential_age_days')&&str_contains($preview,'credential_version')&&str_contains($preview,'rotated_by_staff_no')&&!str_contains($preview,"row['rotated_by']")&&!str_contains($preview,'code_hash')&&!str_contains($preview,'code_ciphertext')&&!str_contains($preview,'code_nonce')&&!str_contains($preview,'key_version');
$checks['report tables use report-specific proportional column sizing']=str_contains($preview,"'compact-report report-'.str_replace('_','-',\$reportKey)")&&str_contains($preview,'.report-executive-summary col:nth-child(1){width:5%}')&&str_contains($preview,'.report-executive-summary col:nth-child(2){width:73%}')&&str_contains($preview,'.report-device-summary col:nth-child(2){width:34%}')&&str_contains($preview,'.report-audit-activity col:nth-child(4){width:38%}');
$checks['executive summary includes a compact sequential number column']=str_contains($preview,"\$columns=[oneid_translate('admin.reports.number'),oneid_translate('admin.reports.preview.metric')")&&str_contains($preview,'$rowNumber++');
$checks['numeric report columns remain compact while narrative columns receive priority']=str_contains($preview,'.report-session-activity col:nth-child(1){width:4%}')&&str_contains($preview,'.report-mfa-adoption col:nth-child(2){width:26%}')&&str_contains($preview,'.report-configuration-changes col:nth-child(6){width:19%}')&&str_contains($preview,'.report-content-changes col:nth-child(8){width:14%}');
$checks['summary cards retain translated text values instead of coercing them to zero']=str_contains($preview,'is_numeric($value)?number_format((float)$value):$escape($value)');
$checks['session activity report excludes session tokens and limits ended history to 30 days']=str_contains($preview,"\$reportKey==='session_activity'")&&!str_contains($preview,'token_id')&&str_contains($database,'A.ended_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)');
$checks['device summary aggregates recorded device data without exposing session tokens']=str_contains($preview,"\$reportKey==='device_summary'")&&str_contains($database,"GROUP BY device_label")&&!str_contains($preview,'token_id');
$checks['MFA report exposes adoption and outcomes without MFA secrets']=str_contains($preview,"\$reportKey==='mfa_adoption'")&&str_contains($database,'successful_30d')&&!str_contains($preview,'encrypted_secret')&&!str_contains($preview,'otp_hash')&&!str_contains($preview,'session_binding_hash');
$checks['synchronisation reports expose safe summaries without source snapshots']=str_contains($preview,"\$reportKey==='sync_runs'")&&str_contains($preview,"\$reportKey==='sync_changes'")&&str_contains($preview,"\$reportKey==='sync_exceptions'")&&!str_contains($preview,'old_data')&&!str_contains($preview,'new_data')&&str_contains($database,'LIMIT 100')&&str_contains($database,'INTERVAL 90 DAY');
$checks['synchronisation run report identifies cron and system triggers without raw actor IDs']=str_contains($database,"THEN 'CRON'")&&str_contains($database,"THEN 'SCHEDULER'")&&str_contains($database,"ELSE 'SYSTEM'")&&str_contains($preview,"admin.reports.preview.cron_job")&&str_contains($preview,"triggered_by_kind")&&!str_contains($preview,"row['triggered_by']");
$checks['executive security and synchronisation summaries use bounded aggregate data']=str_contains($preview,"\$reportKey==='security_summary'")&&str_contains($preview,"\$reportKey==='sync_summary'")&&str_contains($database,'INTERVAL 29 DAY')&&str_contains($database,'admin_report_sync_summary');
$checks['audit and configuration reports omit IP and configuration snapshots']=str_contains($preview,"\$reportKey==='audit_activity'")&&str_contains($preview,"\$reportKey==='configuration_changes'")&&!str_contains($preview,'ip_address')&&!str_contains($preview,'before_json')&&!str_contains($preview,'after_json')&&str_contains($database,'sanitizeDetail');
$checks['content history combines safe metadata and banner events']=str_contains($preview,"\$reportKey==='content_changes'")&&str_contains($database,"'METADATA' AS content_type")&&str_contains($database,"'LOGIN_BANNER'")&&!str_contains($preview,'image_filename')&&!str_contains($preview,'sha256_digest');
$checks['preview closes its dedicated tab instead of opening another dashboard']=str_contains($preview,'onclick="window.close()"')&&str_contains($preview,"admin.reports.preview.close")&&!str_contains($preview,'href="./dashboard.php"');
$checks['report footer identifies the responsible centre without an administrator ID']=str_contains($preview,"admin.reports.preview.owner")&&!str_contains($preview,"\$_SESSION['login_user']??''");
$reportLocaleKeys=array_values(array_filter(array_keys($english),static fn(string $key):bool=>str_starts_with($key,'admin.reports.')||$key==='admin.menu.reports'));
$checks['English and Malay report translations are complete']=$reportLocaleKeys!==[]&&array_diff($reportLocaleKeys,array_keys($malay))===[];

$session=[];
$token=\OneId\App\Admin\AdminReportReference::issue($session,'530','executive_summary',1000);
$checks['report reference is a random 256-bit opaque value']=(bool)preg_match('/\A[a-f0-9]{64}\z/',$token);
$checks['report reference resolves only for its issuing administrator']=\OneId\App\Admin\AdminReportReference::resolve($session,$token,'530',1001)==='executive_summary';
try{\OneId\App\Admin\AdminReportReference::resolve($session,$token,'999',1001);$wrongAdmin=false;}catch(RuntimeException){$wrongAdmin=true;}
$checks['report reference rejects another administrator']=$wrongAdmin;
try{\OneId\App\Admin\AdminReportReference::resolve($session,$token,'530',2000);$expired=false;}catch(RuntimeException){$expired=true;}
$checks['report reference expires after its short lifetime']=$expired;
try{\OneId\App\Admin\AdminReportReference::issue($session,'530','planned_report',1000);$planned=false;}catch(InvalidArgumentException){$planned=true;}
$checks['planned or unknown reports fail closed']=$planned;

$failed=0;
foreach($checks as $label=>$passed){if(!$passed){$failed++;}echo($passed?'PASS ':'FAIL ').$label.PHP_EOL;}
printf("RESULT checks=%d failed=%d\n",count($checks),$failed);
exit($failed===0?0:1);
