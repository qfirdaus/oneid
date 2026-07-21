<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){exit(2);}
if(($argv[1]??'')==='--child'){
 function oneid_sso_cookie_token():string{return'token';} function oneid_clear_local_authenticated_session():void{}
 require_once dirname(__DIR__).'/lib/request_security.php';
 final class F74GuardOp{public function __construct(private array $s){}public function is_specific_token_active($u,$t){return true;}public function get_password_change_requirement($u){return['avail_status'=>1,'password_change_required'=>0];}public function admin_step_up_authorization_state($a,$s,$b,$p){return$this->s;}public function syslog_record($e,$d,$ip){return 1;}}
 session_id('f74childsession');session_start();$_SESSION=['login_status'=>'true','login_user'=>'admin','login_user_type'=>'1','oneid_csrf_token'=>'csrf'];$_SERVER=['REQUEST_METHOD'=>'POST','HTTP_X_CSRF_TOKEN'=>'csrf','HTTP_USER_AGENT'=>'browser','REMOTE_ADDR'=>'127.0.0.1'];$scenario=$argv[2]??'';$base=['admin_2fa_enabled'=>1,'u_type'=>1,'avail_status'=>1,'exact_valid'=>0,'exact_expired'=>0,'other_valid'=>0];
 if($scenario==='ordinary_denied'){$_POST=['admin_search_user_account'=>1];$state=$base;}
 elseif($scenario==='purpose_mismatch'){$_POST=['update_configuration'=>1];$state=array_replace($base,['other_valid'=>1]);}
 elseif($scenario==='disabled_allows'){$_POST=['admin_search_user_account'=>1];$state=array_replace($base,['admin_2fa_enabled'=>0]);}
 elseif($scenario==='exact_allows'){$_POST=['update_configuration'=>1];$state=array_replace($base,['exact_valid'=>1]);}
 elseif($scenario==='challenge_tier'){$_POST=['admin_step_up_request_email'=>1];$state=$base;}
 else{exit(3);} $action=oneid_guard_q_func_request($_POST,new F74GuardOp($state));echo json_encode(['returned'=>$action]);exit;
}
$expected=['ordinary_denied'=>'STEP_UP_REQUIRED','purpose_mismatch'=>'STEP_UP_PURPOSE_MISMATCH','disabled_allows'=>'admin_search_user_account','exact_allows'=>'update_configuration','challenge_tier'=>'admin_step_up_request_email'];$failed=[];
foreach($expected as $scenario=>$value){$cmd=[PHP_BINARY,__FILE__,'--child',$scenario];$pipes=[];$process=proc_open($cmd,[1=>['pipe','w'],2=>['pipe','w']],$pipes);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);$json=json_decode($out,true);$actual=is_array($json)?($json['code']??$json['returned']??''):'';$ok=$exit===0&&$actual===$value;printf("%s %s expected=%s actual=%s\n",$ok?'PASS':'FAIL',$scenario,$value,$actual?:trim($err));if(!$ok)$failed[]=$scenario;}
printf("RESULT checks=%d failed=%d\n",count($expected),count($failed));exit($failed?1:0);
