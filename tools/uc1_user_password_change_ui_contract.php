<?php
declare(strict_types=1);
$root=dirname(__DIR__);$ui=(string)file_get_contents($root.'/page/dashboard.php');$q=(string)file_get_contents($root.'/lib/q_func.php');
$n=0;$f=0;$check=function(bool $ok,string $d)use(&$n,&$f){$n++;if(!$ok)$f++;printf("%s: %s\n",$ok?'PASS':'FAIL',$d);};
$check(!str_contains($ui,'Sekurang-kurangnya 8 aksara')&&str_contains($ui,'Sekurang-kurangnya 12 aksara'),'initial UI copy matches the 12-character server policy');
$check(str_contains($ui,"$('#p_length')")&&str_contains($ui,"$('#p_lowercase')")&&str_contains($ui,"$('#p_special')"),'password requirement reset targets the actual indicator IDs');
$check(str_contains($ui,'autocomplete="current-password"')&&substr_count($ui,'autocomplete="new-password"')>=2,'password inputs expose correct password-manager semantics');
$check(str_contains($ui,'passwordChangeSubmitting')&&str_contains($ui,'setPasswordChangeSubmitting(true)')&&str_contains($ui,"if(passwordChangeSubmitting){return;}"),'submit loading state prevents duplicate requests');
$check(str_contains($ui,"complete: function(){")&&str_contains($ui,"HTTP '+xhr.status"),'transport failure is visible and controls are restored');
$check(str_contains($ui,'passwordChangeFeedback')&&str_contains($ui,'correlation_id')&&str_contains($ui,'UC1_RESPONSE_INVALID'),'UI presents structured code and correlation reference');
$check(str_contains($ui,'password_change_feedback_text')&&str_contains($ui,'showPasswordChangeFeedback')&&str_contains($ui,'copyPasswordChangeFeedback'),'feedback is visible inside the modal and can be copied');
$check(str_contains($ui,'user-select:text')&&str_contains($ui,'navigator.clipboard.writeText'),'feedback text is selectable with clipboard support');
$check(str_contains($q,'UserPasswordChangeService')&&str_contains($q,"'correlation_id'=>\$e->correlationId"),'endpoint preserves structured service outcomes and correlation');
$check(str_contains($q,'outcome=rejected reason=')&&str_contains($q,"correlation='.\$e->correlationId"),'safe rejected-attempt reasons are correlated in audit');
$changeStart=strpos($q,"if(isset( \$_POST['action_change_password']))");$changeBlock=$changeStart===false?'':substr($q,$changeStart,5000);
$check(str_contains($changeBlock,'UserPasswordChangeService')&&str_contains($changeBlock,'session_regenerate_id'),'UC1 feedback is retained after UC2 service and UC4 session rotation');
printf("RESULT: checks=%d failures=%d\n",$n,$f);exit($f===0?0:1);
