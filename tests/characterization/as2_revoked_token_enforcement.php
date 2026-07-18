<?php

if(PHP_SAPI!=='cli'){exit(2);}require_once dirname(__DIR__,2).'/lib/auth_security.php';require_once dirname(__DIR__,2).'/lib/request_security.php';
final class As2TokenStore{public array $active=[];public int $checks=0;public function is_specific_token_active($user,$token):bool{$this->checks++;return isset($this->active[$user])&&hash_equals($this->active[$user],$token);}}
$checks=0;$failed=0;$report=static function(bool $ok,string $label)use(&$checks,&$failed):void{$checks++;if(!$ok)$failed++;printf("%s %s\n",$ok?'PASS':'FAIL',$label);};
$_SESSION=['login_status'=>'true','login_user'=>'USER1','login_user_type'=>'0'];$_COOKIE['sso_cre']='token-a';$store=new As2TokenStore();$store->active=['USER1'=>'token-a'];
$report(oneid_authenticated_sso_token_is_active($store),'current browser token is accepted while active');
$store->active=['USER1'=>'token-b'];
$report(!oneid_authenticated_sso_token_is_active($store),'browser A is rejected after browser B replaces its token');
$_COOKIE['sso_cre']=json_encode(['sso_cre'=>'token-b']);
$report(oneid_authenticated_sso_token_is_active($store),'legacy JSON cookie remains compatible');
unset($_COOKIE['sso_cre']);
$report(!oneid_authenticated_sso_token_is_active($store),'missing SSO cookie fails closed');
$_SESSION=[];$_COOKIE['sso_cre']='token-b';
$report(!oneid_authenticated_sso_token_is_active($store),'unauthenticated PHP session fails closed');
$report($store->checks===3,'token store is queried only for authenticated sessions with a cookie');
printf("RESULT checks=%d failed=%d\n",$checks,$failed);exit($failed===0?0:1);
