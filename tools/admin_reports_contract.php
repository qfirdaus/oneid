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
$checks['Users Access and Applications reports are fully implemented']=$ready===['executive_summary','users_by_category','access_matrix','access_exceptions','application_readiness','application_acl_coverage','credential_rotation'];
$checks['admin sidebar and report workspace are installed']=str_contains($dashboard,'id="tab_reports_menu"')&&str_contains($dashboard,'id="tab_reports"');
$checks['report catalogue uses Bootstrap-compatible six-tab navigation']=str_contains($dashboard,'<ul class="nav admin-report-tabs"')&&str_contains($dashboard,'<li class="<?=$report_group_index===0?\'active\':\'\'?>" role="presentation">')&&count($groups)===6;
$checks['every catalogue table has number name and action columns']=str_contains($dashboard,"oneid_translate('admin.reports.number')")&&str_contains($dashboard,"oneid_translate('admin.reports.report_name')")&&str_contains($dashboard,"oneid_translate('admin.reports.action')");
$checks['only implemented reports expose view buttons']=substr_count($dashboard,'class="admin-report-view"')===1&&str_contains($dashboard,"\$report_ready");
$checks['preview reference endpoint is admin guarded']=str_contains($security,"'admin_issue_report_preview'")&&str_contains($qfunc,"isset(\$_POST['admin_issue_report_preview'])");
$checks['browser receives only an opaque report reference']=str_contains($qfunc,"'./report_preview.php?ref='")&&!str_contains($qfunc,'report_preview.php?report_key=');
$checks['preview enforces admin step-up and no-store response']=str_contains($preview,"'ADMIN_ACCESS'")&&str_contains($preview,'Cache-Control: no-store');
$checks['preview uses allowlisted report keys and seven data providers']=str_contains($preview,'AdminReportReference::resolve')&&str_contains($preview,'admin_report_executive_summary')&&str_contains($preview,'admin_report_users_by_category')&&str_contains($preview,'admin_report_category_access_matrix')&&str_contains($preview,'admin_report_access_exceptions')&&str_contains($preview,'admin_report_application_readiness')&&str_contains($preview,'admin_report_application_acl_coverage')&&str_contains($preview,'admin_report_site_api_credential_rotation');
$checks['database provides the seven read-only report queries']=str_contains($database,'function admin_report_executive_summary')&&str_contains($database,'function admin_report_users_by_category')&&str_contains($database,'function admin_report_category_access_matrix')&&str_contains($database,'function admin_report_access_exceptions')&&str_contains($database,'function admin_report_application_readiness')&&str_contains($database,'function admin_report_application_acl_coverage')&&str_contains($database,'function admin_report_site_api_credential_rotation');
$checks['public report preview wrapper is installed']=is_file($root.'/public/admin/report_preview.php')&&str_contains((string)file_get_contents($root.'/public/admin/report_preview.php'),"'/admin/report_preview.php'");
$checks['report preview supports print without exposing credentials']=str_contains($preview,'window.print()')&&!str_contains($preview,'site_api_code_hash')&&!str_contains($preview,'credential_ciphertext');
$checks['report navigation remains one row at desktop width']=str_contains($dashboard,'grid-template-columns:repeat(6,minmax(0,1fr))');
$checks['Bootstrap clearfix cannot consume report tab grid columns']=str_contains($dashboard,'.admin-report-tabs:before')&&str_contains($dashboard,'.admin-report-tabs:after')&&str_contains($dashboard,'content:none');
$checks['report preview loads the shared environment banner stylesheet']=str_contains($preview,'oneid-environment-banner.css');
$checks['all report previews match the OneID content width']=str_contains($preview,'.shell{max-width:1280px');
$checks['application readiness preview uses compact fixed columns and single-line cells']=str_contains($preview,'.compact-report{font-size:11px;table-layout:fixed;min-width:0}')&&str_contains($preview,'text-overflow:ellipsis;white-space:nowrap')&&str_contains($preview,'title="<?=$escape($cell)?>"');
$checks['Users and Access previews use compact fixed report columns']=str_contains($preview,'.access-matrix col:nth-child(5)')&&str_contains($preview,'.access-exceptions col:nth-child(7)')&&str_contains($preview,"'access_matrix'=>'compact-report access-matrix'")&&str_contains($preview,"'access_exceptions'=>'compact-report access-exceptions'");
$checks['all report tables retain compact single-line printable columns']=str_contains($preview,'.table-wrap table{font-size:11px;table-layout:fixed;min-width:0}')&&str_contains($preview,'.table-wrap th,.table-wrap td{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}');
$checks['credential report exposes rotation metadata without credential material']=str_contains($preview,'credential_age_days')&&str_contains($preview,'credential_version')&&str_contains($preview,'rotated_by')&&!str_contains($preview,'code_hash')&&!str_contains($preview,'code_ciphertext')&&!str_contains($preview,'code_nonce')&&!str_contains($preview,'key_version');
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
