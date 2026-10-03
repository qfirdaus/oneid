<?php
declare(strict_types=1);
require_once __DIR__.'/Fixtures.php';
require_once dirname(__DIR__,2).'/vendor/autoload.php';
use OneId\Tests\MobileOidc\Fixture;
use OneId\App\Auth\MobileOidc\MobileMyDigitalId;
use OneId\App\Auth\MyDigitalId\{MyDigitalIdConfig,MyDigitalIdProtocolGatewayInterface,MyDigitalIdAccountAuthorizerInterface,MyDigitalIdCallbackRequest,MyDigitalIdAuthorizationTransaction,MyDigitalIdVerifiedIdentity,MyDigitalIdAuthenticationDecision};
$n=0;
function check(bool $ok,string $name):void {global $n; if(!$ok)throw new RuntimeException($name);$n++;echo "PASS $name\n";}
$values=['ONEID_ENVIRONMENT'=>'staging','ONEID_MYDID_ENABLED'=>'true','ONEID_MYDID_ISSUER'=>'https://sso.digital-id.my/realms/upnm','ONEID_MYDID_CLIENT_ID'=>'fixture-client','ONEID_MYDID_REDIRECT_URI'=>'https://oneid-uat.upnm.edu.my/auth/mydigitalid/callback.php','ONEID_MYDID_POST_LOGOUT_REDIRECT_URI'=>'https://oneid-uat.upnm.edu.my/','ONEID_MYDID_SCOPE'=>'openid','ONEID_MYDID_HTTP_TIMEOUT_SECONDS'=>'12','ONEID_MYDID_PKCE_METHOD'=>'S256'];
$web=MyDigitalIdConfig::fromRuntime(fn($k,$d=null)=>$values[$k]??$d,fn()=>'synthetic-secret-not-real-123');$mobile=$web->forMobileStaging();
check($web->redirectUri===$values['ONEID_MYDID_REDIRECT_URI'] && $mobile->redirectUri==='https://oneid-uat.upnm.edu.my/mobile/mydigitalid/callback','web callback unchanged; mobile copy distinct');
check($web->clientSecret===$mobile->clientSecret && $web->clientId===$mobile->clientId,'same client and key');
$prod=$values;$prod['ONEID_ENVIRONMENT']='production';foreach(['ONEID_MYDID_REDIRECT_URI','ONEID_MYDID_POST_LOGOUT_REDIRECT_URI'] as $k)$prod[$k]=str_replace('oneid-uat.','oneid.',$prod[$k]);
try{MyDigitalIdConfig::fromRuntime(fn($k,$d=null)=>$prod[$k]??$d,fn()=>'synthetic-secret-not-real-123')->forMobileStaging();$blocked=false;}catch(Throwable){$blocked=true;}check($blocked,'production config rejected');
$protocol=new class implements MyDigitalIdProtocolGatewayInterface {
 public int $calls=0;
 public function complete(MyDigitalIdCallbackRequest $r,MyDigitalIdAuthorizationTransaction $t):MyDigitalIdVerifiedIdentity {$this->calls++;return new MyDigitalIdVerifiedIdentity('synthetic','Synthetic','900101010101','a.b.c');}
};
$accounts=new class implements \OneId\App\Auth\MobileOidc\MobileMyDigitalIdAccounts {
 public array $ids=['STAFF_FIXTURE'];public bool $allowed=true;
 public function resolve(MyDigitalIdVerifiedIdentity $v):array{return ['ids'=>$this->ids,'proof'=>['fixture'=>true]];}
 public function allows(string $uid,array $proof):bool{return $this->allowed && in_array($uid,$this->ids,true);}
};
function prepare($accounts,$mobile,$protocol,bool $dual=false): array {
 $f=new Fixture();$f->source->rows['STAFF_FIXTURE']['data4']='900101010101';
 $f->source->rows['STUDENT_FIXTURE']['data2']='900101010101';
 $accounts->ids=$dual?['STAFF_FIXTURE','STUDENT_FIXTURE']:['STAFF_FIXTURE'];$accounts->allowed=true;
 $c=new MobileMyDigitalId($f->adapter,$mobile,$protocol,$accounts);
 $s=['tx'=>$f->begin(),'binding'=>$f->binding];parse_str(parse_url($c->start($s,$f->agent),PHP_URL_QUERY),$q);
 return [$f,$c,$s,$q];
}
[$f,$c,$s,$q]=prepare($accounts,$mobile,$protocol);
check($q['redirect_uri']===$mobile->redirectUri && $q['code_challenge_method']==='S256','dedicated callback and PKCE without account type input');
check(isset($f->password($s['tx'])['error']),'password cannot reuse federated transaction');
$f->source->rows['STAFF_FIXTURE']['u_type']=1;$f->source->forceMfa=true;
$r=$c->finish($s,$f->agent,'127.0.0.1',['state'=>$q['state'],'code'=>'synthetic']);
check(($r['code']??'')==='AUTHENTICATION_READY' && isset($f->finish($s['tx'])['redirect_to']),'staff automatically selected, including admin as ordinary mobile user');
check($f->provider->accepted[0]['amr']===['mydigitalid'],'upstream assurance preserved');
$before=$protocol->calls;try{$c->finish($s,$f->agent,'127.0.0.1',['state'=>$q['state'],'code'=>'synthetic']);$blocked=false;}catch(Throwable){$blocked=true;}
check($blocked && $protocol->calls===$before,'callback replay blocked before exchange');
[$f,$c,$s,$q]=prepare($accounts,$mobile,$protocol);$accounts->ids=['STUDENT_FIXTURE'];
$r=$c->finish($s,$f->agent,'127.0.0.1',['state'=>$q['state'],'code'=>'synthetic']);
check(($r['code']??'')==='AUTHENTICATION_READY' && isset($f->finish($s['tx'])['redirect_to']) && $f->provider->accepted[0]['account_type']==='student','student automatically selected');
foreach(['staff','student'] as $kind){
 [$f,$c,$s,$q]=prepare($accounts,$mobile,$protocol,true);
 $r=$c->finish($s,$f->agent,'127.0.0.1',['state'=>$q['state'],'code'=>'synthetic']);
 check(($r['code']??'')==='ACCOUNT_REQUIRED' && count($r['choices'])===2 && count($f->provider->accepted)===0,'two distinct accounts require selection before Hydra acceptance');
 check(!str_contains(json_encode($r),'900101010101') && !str_contains(json_encode($r),'FIXTURE'),'choice response contains opaque IDs only');
 $choice=array_values(array_filter($r['choices'],fn($v)=>$v['kind']===$kind))[0]['id'];
 $result=$c->select($s,$f->agent,$choice);
 check(($result['code']??'')==='AUTHENTICATION_READY' && isset($f->finish($s['tx'])['redirect_to']) && $f->provider->accepted[0]['account_type']===$kind,'selected '.$kind.' accepted');
 $blocked=isset($c->select($s,$f->agent,$choice)['error']);
 check($blocked,'selection proof consumed once');
}
foreach(['invalid_choice','expired','wrong_browser','disabled','nric_changed','policy_changed','link_changed','cancelled'] as $case){
 [$f,$c,$s,$q]=prepare($accounts,$mobile,$protocol,true);
 $r=$c->finish($s,$f->agent,'127.0.0.1',['state'=>$q['state'],'code'=>'synthetic']);$choice=$r['choices'][1]['id'];
 if($case==='expired')$f->time+=301;
 if($case==='disabled')$f->source->rows['STUDENT_FIXTURE']['avail_status']=0;
 if($case==='nric_changed')$f->source->rows['STUDENT_FIXTURE']['data2']='800101010101';
 if($case==='policy_changed')$f->source->emailEnabled=false;
 if($case==='link_changed')$accounts->allowed=false;
 if($case==='cancelled')$f->store->rows['tx:'.$s['tx']]['state']='CANCELLED';
 $r=$c->select($s,$case==='wrong_browser'?'other':$f->agent,$case==='invalid_choice'?'student':$choice);
 check(isset($r['error']) && !isset($f->finish($s['tx'])['redirect_to']),'selection rejects '.$case);
}
foreach(['wrong_state','wrong_browser','expired','nric_changed','inactive','ambiguous','upstream_expired'] as $case){
 [$f,$c,$s,$q]=prepare($accounts,$mobile,$protocol);
 if($case==='expired')$f->time+=901;
 if($case==='nric_changed')$f->source->rows['STAFF_FIXTURE']['data4']='800101010101';
 if($case==='inactive')$f->source->rows['STAFF_FIXTURE']['avail_status']=0;
 if($case==='upstream_expired')$s['oneid_mydid_authorization']['created_at']=time()-301;
 if($case==='ambiguous'){$f->source->rows['DUPLICATE']=$f->source->rows['STAFF_FIXTURE'];$f->source->rows['DUPLICATE']['u_id']='DUPLICATE';$accounts->ids[]='DUPLICATE';}
 try{$r=$c->finish($s,$case==='wrong_browser'?'other':$f->agent,'127.0.0.1',['state'=>$case==='wrong_state'?str_repeat('0',64):$q['state'],'code'=>'synthetic']);$blocked=isset($r['error']);}catch(Throwable){$blocked=true;}
 check($blocked && count($f->provider->accepted)===0,'callback rejects '.$case);
}
[$f,$c,$s,$q]=prepare($accounts,$mobile,$protocol);$f->source->rows['STAFF_FIXTURE']['families']=['staff','student'];
$r=$c->finish($s,$f->agent,'127.0.0.1',['state'=>$q['state'],'code'=>'synthetic']);
check(($r['code']??'')==='ACCOUNT_REQUIRED' && count($r['choices'])===2,'single record with both families prompts after verification');
[$f,$c,$s,$q]=prepare($accounts,$mobile,$protocol);$f->source->rows['STAFF_FIXTURE']['password_change_required']=1;
$r=$c->finish($s,$f->agent,'127.0.0.1',['state'=>$q['state'],'code'=>'synthetic']);
check(($r['error']??'')==='MOBILE_PASSWORD_CHANGE_REQUIRED','mandatory password change preserved');
[$f,$c,$s,$q]=prepare($accounts,$mobile,$protocol,true);
$f->adapter=new \OneId\App\Auth\MobileOidc\MobileIdentityAdapter($f->source,$f->store,$f->provider,$f->delivery,$f->primitive,
 ['fixture-client'=>['http://127.0.0.1/callback']],random_bytes(32),true,'staging',fn()=>$f->time,['0530-09']);
$c=new MobileMyDigitalId($f->adapter,$mobile,$protocol,$accounts);$s=['tx'=>$f->begin(),'binding'=>$f->binding];
parse_str(parse_url($c->start($s,$f->agent),PHP_URL_QUERY),$q);
$r=$c->finish($s,$f->agent,'127.0.0.1',['state'=>$q['state'],'code'=>'synthetic']);
check(($r['code']??'')==='AUTHENTICATION_READY' && isset($f->finish($s['tx'])['redirect_to']) && $f->provider->accepted[0]['account_type']==='staff','pilot allowlist excludes unapproved student account before automatic selection');
[$f,$c,$s,$q]=prepare($accounts,$mobile,$protocol);$accounts->ids=[];
$r=$c->finish($s,$f->agent,'127.0.0.1',['state'=>$q['state'],'code'=>'synthetic']);
check(isset($r['error']) && !$f->provider->accepted,'no eligible account cannot authenticate');
echo "$n checks passed; synthetic only, no provider requests.\n";
