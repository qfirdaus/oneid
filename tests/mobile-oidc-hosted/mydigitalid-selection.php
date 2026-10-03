<?php
declare(strict_types=1);
// Exercise actual hosted rendering and dispatch with in-memory identities, no network.
ob_start();
require __DIR__.'/../mobile-oidc/mydigitalid.php';
$directory=sys_get_temp_dir().'/oneid-choice-'.bin2hex(random_bytes(8));mkdir($directory,0700);
try {
 [$f,$c,$unused,$unusedQuery]=prepare($accounts,$mobile,$protocol,true);
 $hosted=new \OneId\App\Auth\MobileOidc\HostedLogin($f->adapter,['origin'=>'http://127.0.0.1','session_path'=>$directory],$c);
 $_SERVER['HTTP_USER_AGENT']=$f->agent;$_SERVER['REMOTE_ADDR']='127.0.0.1';$_SERVER['HTTP_ORIGIN']='http://127.0.0.1';
 $_SERVER['REQUEST_URI']='/login';$_GET=['login_challenge'=>bin2hex(random_bytes(16))];$_POST=[];
 ob_start();$hosted->handle('GET');$html=ob_get_clean();
 check(str_contains($html,'value="mydigitalid"') && !str_contains($html,'name="account_type"') && !str_contains($html,'name="choice"'),'hosted initial page has no account selector');
 $_GET=[];$_POST=['csrf'=>$_SESSION['csrf'],'action'=>'mydigitalid'];
 ob_start();$hosted->handle('POST');ob_end_clean();
 check(isset($_SESSION['oneid_mydid_authorization']),'hosted button starts upstream without account type');
 $_GET=['state'=>$_SESSION['oneid_mydid_authorization']['state'],'code'=>'synthetic'];$_POST=[];$_SERVER['REQUEST_URI']='/mobile/mydigitalid/callback';
 ob_start();$hosted->handle('GET');$html=ob_get_clean();
 check($_SESSION['stage']==='ACCOUNT' && substr_count($html,'name="choice"')===2 && !$f->provider->accepted,'hosted callback displays choices without accepting Hydra login');
 $choice=$_SESSION['choices'][1]['id'];$_SERVER['REQUEST_URI']='/login?lang=en';$_GET=['lang'=>'en'];
 ob_start();$hosted->handle('GET');$html=ob_get_clean();
 check(str_contains($html,'Choose an account for the app') && isset($_SESSION['mydid_account_proof']),'language switch preserves server-side verified proof');
 $_GET=[];$_POST=['csrf'=>'wrong','action'=>'mydigitalid_account','choice'=>$choice];
 ob_start();$hosted->handle('POST');ob_end_clean();
 check(http_response_code()===403 && !$f->provider->accepted,'account selection requires CSRF');
 if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
 $_POST=['csrf'=>$_SESSION['csrf'],'action'=>'mydigitalid_account','choice'=>$choice];
 ob_start();$hosted->handle('POST');ob_end_clean();
 check(http_response_code()===303 && count($f->provider->accepted)===1 && $f->provider->accepted[0]['account_type']==='student','hosted POST completes selected account and redirects to Hydra');
} finally {
 if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
 foreach(glob($directory.'/sess_*') as $file)unlink($file);rmdir($directory);
}
$output=ob_get_clean();echo $output;echo "Hosted selection dispatch: 6 checks passed.\n";
