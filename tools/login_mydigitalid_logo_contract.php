<?php
declare(strict_types=1);$html=(string)file_get_contents(dirname(__DIR__).'/index.php');$n=0;$f=0;$ok=function($v,$d)use(&$n,&$f){$n++;if(!$v)$f++;printf("%s: %s\n",$v?'PASS':'FAIL',$d);};
$ok(str_contains($html,'https://www.digital-id.my/images/logo/logo_colored.svg')&&str_contains($html,'alt="MyDigital ID"'),'official MyDigital ID logo is visible on login');
$start=strpos($html,'<div class="mydigitalid-preview"');$end=strpos($html,'</div>',$start?:0);$block=$start!==false&&$end!==false?substr($html,$start,$end-$start):'';
$ok(!str_contains($block,'<a ')&&!str_contains($block,'<button')&&!str_contains($block,'onclick=')&&!str_contains($block,'href='),'preview has no link, button or click behavior');
$ok(str_contains($block,'Integrasi belum diaktifkan')&&str_contains($html,'pointer-events: none'),'preview clearly states inactive integration and ignores pointer interaction');
$ok(!str_contains($html,'mydigitalid_auth')&&!str_contains($html,'action_mydigitalid'),'no MyDigital ID authentication action or endpoint is introduced');
printf("RESULT: checks=%d failures=%d\n",$n,$f);exit($f===0?0:1);
