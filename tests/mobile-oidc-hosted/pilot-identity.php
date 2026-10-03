<?php
require_once dirname(__DIR__).'/mobile-oidc/Fixtures.php';
use OneId\Tests\MobileOidc\Fixture;
use OneId\App\Auth\MobileOidc\MobileIdentityAdapter;
foreach([['0530-09'],[]] as $allowed){
 $f=new Fixture();$f->adapter=new MobileIdentityAdapter($f->source,$f->store,$f->provider,$f->delivery,$f->primitive,['fixture-client'=>['http://127.0.0.1/callback']],random_bytes(32),true,'staging',fn()=>$f->time,$allowed);
 $id=$f->begin();$r=$f->password($id,'M123456');
 if(($r['error']??'')!=='MOBILE_CREDENTIALS_INVALID')throw new Exception('Other account accepted');
 $r=$f->password($id);
 if($allowed && ($r['code']??'')!=='AUTHENTICATION_READY')throw new Exception('Allowed staff rejected');
 if(!$allowed && ($r['error']??'')!=='MOBILE_CREDENTIALS_INVALID')throw new Exception('Empty allowlist accepted');
}
echo "PASS: non-pilot identity rejected, approved staff accepted, empty pilot list fails closed\n";
