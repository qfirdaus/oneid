<?php
declare(strict_types=1);$html=(string)file_get_contents(dirname(__DIR__).'/index.php');$n=0;$f=0;$ok=function($v,$d)use(&$n,&$f){$n++;if(!$v)$f++;printf("%s: %s\n",$v?'PASS':'FAIL',$d);};
$logo=dirname(__DIR__).'/public/img/mydigitalid_logo_colored.svg';
$ok(str_contains($html,'img/mydigitalid_logo_colored.svg')&&str_contains($html,'alt="MyDigital ID"')&&is_file($logo),'local MyDigital ID logo is visible on login');
$ok(!str_contains($html,'https://www.digital-id.my/images/logo/logo_colored.svg'),'login does not request the third-party logo');
$start=strpos($html,'<div class="mydigitalid-preview"');$end=strpos($html,'</div>',$start?:0);$block=$start!==false&&$end!==false?substr($html,$start,$end-$start):'';
$ok(!str_contains($block,'<a ')&&!str_contains($block,'<button')&&!str_contains($block,'onclick=')&&!str_contains($block,'href=')&&str_contains($html,'pointer-events: none'),'disabled preview has no click behavior');
$ok(str_contains($html,'<?php if ($myDigitalIdEnabled): ?>')&&str_contains($html,'class="mydigitalid-button"')&&str_contains($html,'href="auth/mydigitalid/login.php"'),'enabled presentation uses the dedicated MyDigital ID login endpoint');
$ok(str_contains($html,"oneid_config('ONEID_MYDID_ENABLED', 'false')")&&!str_contains($html,'mydigitalid_auth')&&!str_contains($html,'action_mydigitalid'),'presentation remains feature-flagged with no legacy form action');
printf("RESULT: checks=%d failures=%d\n",$n,$f);exit($f===0?0:1);
