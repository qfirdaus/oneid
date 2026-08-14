<?php
declare(strict_types=1);if(PHP_SAPI!=='cli'){exit(2);}$root=dirname(__DIR__);$page=file_get_contents($root.'/page/dashboard.php')?:'';$shared=file_get_contents($root.'/lib/shared_faq.php')?:'';$css=file_get_contents($root.'/public/dist/css/oneid-user-faq.css')?:'';$checks=0;$failed=0;$report=static function(bool $ok,string $label)use(&$checks,&$failed):void{$checks++;$failed+=$ok?0:1;echo($ok?'PASS ':'FAIL ').$label.PHP_EOL;};
$report(str_contains($page,'oneid-faq-modal')&&str_contains($page,'oneid-user-faq.css?v=20260814-2'),'user dashboard loads the dedicated FAQ dialog design');
$report(str_contains($page,"oneid_translate('faq.eyebrow')")&&str_contains($page,"oneid_translate('faq.intro')")&&str_contains($page,'aria-describedby="faqModalIntro"'),'FAQ header is localized and accessible');
$report(str_contains($shared,'oneid-faq-number')&&str_contains($shared,'oneid-faq-chevron')&&str_contains($shared,'oneid-faq-answer-icon'),'shared dashboard FAQ renders structured question and answer affordances');
$report(str_contains($css,'.oneid-faq-dialog')&&str_contains($css,'.oneid-faq-item .panel-title>a')&&str_contains($css,'@media(max-width:600px)'),'FAQ has professional dialog, accordion and mobile styling');
$report(str_contains($css,'.oneid-faq-modal .modal-dialog .modal-content.oneid-faq-dialog')&&str_contains($css,'border-radius:16px!important')&&str_contains($css,'overflow:hidden!important'),'FAQ modal overrides the square legacy theme at every corner');
$report(str_contains($shared,'htmlspecialchars($entry[\'question\']')&&str_contains($shared,'htmlspecialchars($entry[\'answer\']'),'FAQ content remains safely escaped');
echo"RESULT checks={$checks} failed={$failed}".PHP_EOL;exit($failed===0?0:1);
