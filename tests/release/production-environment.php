<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/app/Auth/MobileOidc/bootstrap.php';
require_once dirname(__DIR__,2).'/vendor/autoload.php';
require_once dirname(__DIR__).'/mobile-oidc/Fixtures.php';
use OneId\App\Auth\MobileOidc\RuntimeEnvironment as Env;
use OneId\App\Auth\MyDigitalId\MyDigitalIdConfig;
use OneId\Tests\MobileOidc\Fixture;
$n=0;
function check(bool $ok,string $label):void {global $n;if(!$ok)throw new RuntimeException($label);$n++;echo "PASS $label\n";}
$prod=['environment'=>'production','origin'=>'https://oneid.upnm.edu.my','issuer'=>'https://oneid.upnm.edu.my',
    'database_scope_confirmed'=>true,'production_ready'=>true,'admin_url'=>'http://127.0.0.1:24145',
    'binding_key'=>str_repeat('a',32),'hook_key'=>str_repeat('b',32),
    'clients'=>['synthetic-android-production'=>['com.example.fixture://oneid/callback']]];
check(Env::validate('/var/www/oneid',$prod)==='production','explicit production configuration accepted');
$bad=[
 ['environment'=>'unknown'],['origin'=>'https://oneid-uat.upnm.edu.my'],['issuer'=>'https://oneid-uat.upnm.edu.my'],
 ['database_scope_confirmed'=>false],['production_ready'=>false],['clients'=>[]],['loopback_fixture'=>true],
 ['pilot_identifiers'=>['fixture']],['pilot_until'=>time()+1000],['admin_url'=>'https://external.invalid'],
 ['hook_key'=>''],['hook_key'=>str_repeat('a',32)],['mydigitalid_enabled'=>true],
 ['clients'=>['client-staging'=>['com.example.app://oneid/callback']]],
 ['clients'=>['client'=>['http://example.com/callback']]],['clients'=>['client'=>['https://oneid-uat.upnm.edu.my/callback']]],
 ['clients'=>['client'=>['com.example.app://oneid/*']]],['clients'=>['client'=>['https://localhost/callback']]],
];
foreach($bad as $i=>$change){$blocked=false;try{Env::validate('/var/www/oneid',array_replace($prod,$change));}catch(RuntimeException){$blocked=true;}check($blocked,'invalid production config rejected #'.$i);}
foreach(['/var/www/oneid-uat','/tmp/oneid'] as $root){try{Env::validate($root,$prod);$blocked=false;}catch(RuntimeException){$blocked=true;}check($blocked,'production root pinned '.$root);}
$uat=['environment'=>'staging','origin'=>'https://oneid-uat.upnm.edu.my','issuer'=>'https://oneid-uat.upnm.edu.my/','database_scope_confirmed'=>true];
check(Env::validate('/var/www/oneid-uat',$uat)==='staging','existing staging configuration accepted');
$values=['ONEID_ENVIRONMENT'=>'production','ONEID_MYDID_ENABLED'=>'true','ONEID_MYDID_ISSUER'=>'https://sso.digital-id.my/realms/upnm','ONEID_MYDID_CLIENT_ID'=>'synthetic-client','ONEID_MYDID_REDIRECT_URI'=>'https://oneid.upnm.edu.my/auth/mydigitalid/callback.php','ONEID_MYDID_POST_LOGOUT_REDIRECT_URI'=>'https://oneid.upnm.edu.my/','ONEID_MYDID_SCOPE'=>'openid','ONEID_MYDID_HTTP_TIMEOUT_SECONDS'=>'12','ONEID_MYDID_PKCE_METHOD'=>'S256'];
$c=MyDigitalIdConfig::fromRuntime(fn($k,$d=null)=>$values[$k]??$d,fn()=>'synthetic-private-value-not-real');
check($c->forMobile('production')->redirectUri==='https://oneid.upnm.edu.my/mobile/mydigitalid/callback','production mobile callback');
check($c->redirectUri===$values['ONEID_MYDID_REDIRECT_URI'],'web callback remains unchanged');
try{$c->forMobile('staging');$blocked=false;}catch(Throwable){$blocked=true;}check($blocked,'cross-environment MyDigital ID blocked');
$f=new Fixture(true,'production');$tx=$f->begin();$r=$f->password($tx);check(!isset($r['error']),'production adapter authenticates synthetic eligible account');
check(isset($f->finish($tx)['redirect_to']),'production adapter completes synthetic login');
$f=new Fixture(true,'production');$f->source->rows['STAFF_FIXTURE']['avail_status']=0;$tx=$f->begin();$r=$f->password($tx);
check(isset($r['error'])||($r['ok']??null)===false,'inactive production identity denied');
echo "$n checks passed; no external identity/provider/database requests.\n";
