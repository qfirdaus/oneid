<?php

declare(strict_types=1);

$root=dirname(__DIR__);
require_once $root.'/app/Admin/AdminReportCatalogue.php';
require_once $root.'/app/Admin/AdminReportReference.php';
require_once $root.'/app/Admin/ReportColumnLayout.php';

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
$checks['all twenty-five approved reports are implemented']=$ready===['executive_summary','security_summary','sync_summary','mydigitalid_overview','users_by_category','access_matrix','access_exceptions','mydigitalid_linked_accounts','application_readiness','application_acl_coverage','credential_rotation','downstream_sso_usage','session_activity','device_summary','mfa_adoption','mydigitalid_authentication','sync_runs','sync_changes','sync_exceptions','sync_health','audit_activity','configuration_changes','content_changes','administrator_activity','mfa_policy_history'];
$checks['admin sidebar and report workspace are installed']=str_contains($dashboard,'id="tab_reports_menu"')&&str_contains($dashboard,'id="tab_reports"');
$checks['report catalogue uses Bootstrap-compatible six-tab navigation']=str_contains($dashboard,'<ul class="nav admin-report-tabs"')&&str_contains($dashboard,'<li class="<?=$report_group_index===0?\'active\':\'\'?>" role="presentation">')&&count($groups)===6;
$checks['every catalogue table has number name and action columns']=str_contains($dashboard,"oneid_translate('admin.reports.number')")&&str_contains($dashboard,"oneid_translate('admin.reports.report_name')")&&str_contains($dashboard,"oneid_translate('admin.reports.action')");
$checks['only implemented reports expose view buttons']=substr_count($dashboard,'class="admin-report-view"')===1&&str_contains($dashboard,"\$report_ready");
$checks['preview reference endpoint is admin guarded']=str_contains($security,"'admin_issue_report_preview'")&&str_contains($qfunc,"isset(\$_POST['admin_issue_report_preview'])");
$checks['browser receives only an opaque report reference']=str_contains($qfunc,"'./report_preview.php?ref='")&&!str_contains($qfunc,'report_preview.php?report_key=');
$checks['preview enforces admin step-up and no-store response']=str_contains($preview,"'ADMIN_ACCESS'")&&str_contains($preview,'Cache-Control: no-store');
$providers=['executive_summary','security_summary','sync_summary','users_by_category','category_access_matrix','access_exceptions','application_readiness','application_acl_coverage','site_api_credential_rotation','session_activity','device_summary','mfa_adoption','sync_runs','sync_changes','sync_exceptions','audit_activity','configuration_changes','content_changes','mydigitalid_overview','mydigitalid_linked_accounts','mydigitalid_authentication','downstream_sso_usage','sync_health','administrator_activity','mfa_policy_history'];
$checks['preview uses allowlisted report keys and twenty-five data providers']=str_contains($preview,'AdminReportReference::resolve')&&array_reduce($providers,static fn(bool $ok,string $provider):bool=>$ok&&str_contains($preview,'admin_report_'.$provider),true);
$checks['database provides twenty-five read-only report queries']=array_reduce($providers,static fn(bool $ok,string $provider):bool=>$ok&&str_contains($database,'function admin_report_'.$provider),true);
$checks['public report preview wrapper is installed']=is_file($root.'/public/admin/report_preview.php')&&str_contains((string)file_get_contents($root.'/public/admin/report_preview.php'),"'/admin/report_preview.php'");
$checks['report preview supports print without exposing credentials']=str_contains($preview,'window.print()')&&!str_contains($preview,'site_api_code_hash')&&!str_contains($preview,'credential_ciphertext');
$checks['report navigation remains one row at desktop width']=str_contains($dashboard,'grid-template-columns:repeat(6,minmax(0,1fr))');
$checks['Bootstrap clearfix cannot consume report tab grid columns']=str_contains($dashboard,'.admin-report-tabs:before')&&str_contains($dashboard,'.admin-report-tabs:after')&&str_contains($dashboard,'content:none');
$checks['report preview loads the shared environment banner stylesheet']=str_contains($preview,'oneid-environment-banner.css');
$checks['all report previews match the OneID content width']=str_contains($preview,'.shell{max-width:1280px');
$checks['report previews use compact fixed columns and single-line cells']=str_contains($preview,'.compact-report{font-size:11px;table-layout:fixed;min-width:0}')&&str_contains($preview,'text-overflow:ellipsis;white-space:nowrap')&&str_contains($preview,'title="<?=$escape($cell)?>"');
$checks['application readiness uses the full production-ready label and a wider status column']=($english['admin.reports.preview.ready']??'')==='Production Ready'&&($malay['admin.reports.preview.ready']??'')==='Sedia Production'&&str_contains($preview,'.application-readiness col:nth-child(7){width:13%}');
$checks['Users and Access previews use compact fixed report columns']=str_contains($preview,'.access-matrix col:nth-child(5)')&&str_contains($preview,'.access-exceptions col:nth-child(7)')&&str_contains($preview,"'access_matrix'=>' access-matrix'")&&str_contains($preview,"'access_exceptions'=>' access-exceptions'");
$checks['all report tables retain compact single-line printable columns']=str_contains($preview,'.table-wrap table{font-size:11px;table-layout:fixed}')&&str_contains($preview,'text-overflow:ellipsis;vertical-align:top;white-space:nowrap}');
$checks['all report headers and cells are consistently left and top aligned']=str_contains($preview,'.table-wrap th,.table-wrap td{overflow:hidden;text-align:left;text-overflow:ellipsis;vertical-align:top;white-space:nowrap}')&&!str_contains($preview,'{text-align:center}');
$checks['credential report exposes safe rotation metadata without credential material']=str_contains($preview,'credential_age_days')&&str_contains($preview,'credential_version')&&str_contains($preview,'rotated_by_staff_no')&&!str_contains($preview,"row['rotated_by']")&&!str_contains($preview,'code_hash')&&!str_contains($preview,'code_ciphertext')&&!str_contains($preview,'code_nonce')&&!str_contains($preview,'key_version');
$checks['all twenty-five reports use the shared content-aware column layout']=str_contains($preview,'ReportColumnLayout::calculate($columns,$rows)')&&str_contains($preview,"style=\"width:100%;min-width:<?=\$columnLayout['minimum_width']?>px\"")&&str_contains($preview,"\$columnIndex===\$columnLayout['flex_index']?'auto'");
$layout=\OneId\App\Admin\ReportColumnLayout::calculate(
    ['No.','Date','Recorded At','Count','Description'],
    [
        ['No.'=>1,'Date'=>'06/10/2026','Recorded At'=>'06/10/2026 15:10:58','Count'=>7,'Description'=>'Short'],
        ['No.'=>2,'Date'=>'07/10/2026','Recorded At'=>'07/10/2026 08:00:00','Count'=>1250,'Description'=>'A substantially longer narrative value for sizing'],
    ]
);
$checks['shared sequence numeric date and datetime columns have one consistent size']=($layout['types']??[])===['sequence','date','datetime','numeric','text']&&($layout['widths']??[])[0]===52&&$layout['widths'][1]===115&&$layout['widths'][2]===155&&$layout['widths'][3]===115;
$checks['variable text column width follows its longest displayed data']=($layout['widths'][4]??0)>105&&($layout['widths'][4]??0)<=360&&($layout['minimum_width']??0)===array_sum($layout['widths']??[])&&($layout['flex_index']??null)===4;
$linkedLayout=\OneId\App\Admin\ReportColumnLayout::calculate(
    ['No.','Staff/Student ID','User','Category','User Status','Link Status','First Verified','Last Login','Login Count'],
    [[
        'No.'=>1,'Staff/Student ID'=>'0530-09','User'=>'A deliberately long representative user name for layout verification',
        'Category'=>'Staf Pentadbiran','User Status'=>'Active','Link Status'=>'Active','First Verified'=>'21/09/2026 14:45',
        'Last Login'=>'06/10/2026 15:23','Login Count'=>123,
    ]]
);
$checks['MyDigital ID linked accounts keeps Login Count visible within the report panel']=count($linkedLayout['widths']??[])===9&&($linkedLayout['types'][8]??null)==='numeric'&&($linkedLayout['widths'][8]??null)===115&&($linkedLayout['minimum_width']??PHP_INT_MAX)<=1160;
$checks['executive summary includes a compact sequential number column']=str_contains($preview,"\$columns=[oneid_translate('admin.reports.number'),oneid_translate('admin.reports.preview.metric')")&&str_contains($preview,'$rowNumber++');
$checks['column sizing remains bounded and supports horizontal overflow']=str_contains($preview,'.table-wrap{border:1px solid var(--line);border-radius:8px;overflow:auto}')&&max($layout['widths']??[0])<=360;
$checks['summary cards retain translated text values instead of coercing them to zero']=str_contains($preview,'is_numeric($value)?number_format((float)$value):$escape($value)');
$checks['session activity report excludes session tokens and limits ended history to 30 days']=str_contains($preview,"\$reportKey==='session_activity'")&&!str_contains($preview,'token_id')&&str_contains($database,'A.ended_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)');
$checks['device summary aggregates recorded device data without exposing session tokens']=str_contains($preview,"\$reportKey==='device_summary'")&&str_contains($database,"GROUP BY device_label")&&!str_contains($preview,'token_id');
$checks['MFA report exposes adoption and outcomes without MFA secrets']=str_contains($preview,"\$reportKey==='mfa_adoption'")&&str_contains($database,'successful_30d')&&!str_contains($preview,'encrypted_secret')&&!str_contains($preview,'otp_hash')&&!str_contains($preview,'session_binding_hash');
$checks['synchronisation reports expose safe summaries without source snapshots']=str_contains($preview,"\$reportKey==='sync_runs'")&&str_contains($preview,"\$reportKey==='sync_changes'")&&str_contains($preview,"\$reportKey==='sync_exceptions'")&&!str_contains($preview,'old_data')&&!str_contains($preview,'new_data')&&str_contains($database,'LIMIT 100')&&str_contains($database,'INTERVAL 90 DAY');
$checks['synchronisation run report identifies cron and system triggers without raw actor IDs']=str_contains($database,"THEN 'CRON'")&&str_contains($database,"THEN 'SCHEDULER'")&&str_contains($database,"ELSE 'SYSTEM'")&&str_contains($preview,"admin.reports.preview.cron_job")&&str_contains($preview,"triggered_by_kind")&&!str_contains($preview,"row['triggered_by']");
$checks['executive security and synchronisation summaries use bounded aggregate data']=str_contains($preview,"\$reportKey==='security_summary'")&&str_contains($preview,"\$reportKey==='sync_summary'")&&str_contains($database,'INTERVAL 29 DAY')&&str_contains($database,'admin_report_sync_summary');
$checks['audit and configuration reports omit IP and configuration snapshots']=str_contains($preview,"\$reportKey==='audit_activity'")&&str_contains($preview,"\$reportKey==='configuration_changes'")&&!str_contains($preview,'ip_address')&&!str_contains($preview,'before_json')&&!str_contains($preview,'after_json')&&str_contains($database,'sanitizeDetail');
$checks['configuration and content reports resolve public staff numbers without exposing raw actor IDs']=substr_count($database,"auditIdentity()->resolve((string)(\$row['actor_id']??''))")===2&&substr_count($database,"unset(\$row['actor_id'])")===2&&!str_contains($preview,"row['actor_id']");
$checks['content history combines safe metadata and banner events']=str_contains($preview,"\$reportKey==='content_changes'")&&str_contains($database,"'METADATA' AS content_type")&&str_contains($database,"'LOGIN_BANNER'")&&!str_contains($preview,'image_filename')&&!str_contains($preview,'sha256_digest');
$checks['MyDigital ID reports use aggregate and safe linked-account fields']=str_contains($preview,"\$reportKey==='mydigitalid_overview'")&&str_contains($preview,"\$reportKey==='mydigitalid_linked_accounts'")&&str_contains($preview,"\$reportKey==='mydigitalid_authentication'")&&!str_contains($preview,'subject_hmac')&&!str_contains($preview,'nric_hmac')&&!str_contains($preview,'session_id_hmac');
$checks['MyDigital ID overview distinguishes current snapshot from 30-day activity']=str_contains($preview,"admin.reports.preview.current_snapshot")&&str_contains($preview,"admin.reports.preview.linked_account_metrics")&&str_contains($preview,"admin.reports.preview.activity_period")&&str_contains($preview,'$scopeNote!==[]')&&($english['admin.reports.preview.last_30_days']??'')==='Last 30 Days'&&($malay['admin.reports.preview.last_30_days']??'')==='30 Hari Terakhir';
$checks['MyDigital ID overview reconciles staff student and other linked accounts to total']=str_contains($database,'AS linked_staff')&&str_contains($database,'AS linked_student')&&str_contains($database,'AS linked_other')&&substr_count($database,"IN ('Pensyarah','Staf Pentadbiran')")===2&&!str_contains($database,"IN ('Penyarah','Staf Pentadbiran')")&&str_contains($preview,"'linked_total','linked_staff','linked_student','linked_other','linked_active'")&&str_contains($preview,"mydid_linked_staff")&&str_contains($preview,"mydid_linked_student");
$checks['MyDigital ID linked accounts orders highest login count first']=str_contains($database,'ORDER BY F.login_count DESC,F.last_login_at DESC,F.identity_id DESC LIMIT 500');
$checks['downstream usage is aggregated without exposing user identifiers']=str_contains($preview,"\$reportKey==='downstream_sso_usage'")&&str_contains($database,'admin_report_downstream_sso_usage')&&str_contains($database,'COUNT(DISTINCT CASE WHEN A.id IS NULL')&&str_contains($preview,"(int)\$row['unique_users']");
$checks['sync health and administrative reports expose bounded safe data']=str_contains($preview,"\$reportKey==='sync_health'")&&str_contains($preview,"\$reportKey==='administrator_activity'")&&str_contains($preview,"\$reportKey==='mfa_policy_history'")&&!str_contains($preview,'previous_policy')&&!str_contains($preview,'resulting_policy');
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
