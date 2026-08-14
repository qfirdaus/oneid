<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){exit(2);}
$root=dirname(__DIR__);$user=file_get_contents($root.'/page/dashboard.php')?:'';$admin=file_get_contents($root.'/admin/dashboard.php')?:'';$css=file_get_contents($root.'/public/dist/css/oneid-sidebar-menu.css')?:'';$ms=require $root.'/config/locales/ms.php';$en=require $root.'/config/locales/en.php';$checks=0;$failed=0;$report=static function(bool $ok,string $label)use(&$checks,&$failed):void{$checks++;$failed+=$ok?0:1;echo($ok?'PASS ':'FAIL ').$label.PHP_EOL;};
$url='https://outlook.cloud.microsoft/mail/';
$report(str_contains($user,$url)&&str_contains($admin,$url),'user and Administrator sidebars expose Outlook email');
$report(substr_count($user,'target="_blank" rel="noopener noreferrer"')>=2&&str_contains($admin,'target="_blank" rel="noopener noreferrer"'),'external sidebar links open safely in a new tab');
$report(str_contains($user,'oneid-sidebar-nav')&&str_contains($admin,'oneid-sidebar-nav')&&str_contains($user,'oneid-sidebar-menu.css?v=20260814-1')&&str_contains($admin,'oneid-sidebar-menu.css?v=20260814-1'),'both account levels load the shared upgraded sidebar');
$report(substr_count($user,'oneid-sidebar-icon')>=7&&substr_count($admin,'oneid-sidebar-icon')>=10,'every visible user and Administrator menu has a contextual icon');
$report(str_contains($css,'.oneid-sidebar-nav>li.active>a')&&str_contains($css,'.oneid-sidebar-nav>li.oneid-sidebar-email>a')&&str_contains($css,'@media(max-width:767px)'),'sidebar styling covers active, email and responsive states');
$report(isset($ms['dashboard.menu.email'],$en['dashboard.menu.email'],$ms['admin.menu.email'],$en['admin.menu.email']),'My Email is localized for user and Administrator menus');
echo"RESULT checks={$checks} failed={$failed}".PHP_EOL;exit($failed===0?0:1);
