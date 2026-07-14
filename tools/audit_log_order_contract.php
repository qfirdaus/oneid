<?php

declare(strict_types=1);

$root=dirname(__DIR__);$checks=0;$failed=0;
$report=static function(bool $ok,string $label)use(&$checks,&$failed):void{$checks++;if(!$ok)$failed++;printf("%s %s\n",$ok?'PASS':'FAIL',$label);};
$database=(string)file_get_contents($root.'/lib/Database.php');
$dashboard=(string)file_get_contents($root.'/admin/dashboard.php');

$report(str_contains($database,'A.id AS audit_id'),'audit response exposes stable row identifier');
$report(str_contains($database,'ORDER BY A.datetime DESC,A.id DESC'),'database orders newest timestamp and ID first');
$report(str_contains($database,'A.datetime < DATE_ADD(:date_end, INTERVAL 1 DAY)'),'selected end date includes its complete day');
$report(str_contains($database,'LIMIT 50'),'audit query retains bounded result size');
$report(str_contains($dashboard,'response.sort(function(a, b)')&&str_contains($dashboard,'Number(b.audit_id || 0)'),'UI applies defensive newest-first ordering');

require_once $root.'/lib/config.php';
$operation=new Database();
$rows=$operation->admin_get_audit_range(date('Y-m-d',strtotime('-30 days')),date('Y-m-d'));
$ordered=true;
for($i=1,$total=count($rows);$i<$total;$i++){
    $previous=[(string)$rows[$i-1]['datetime'],(int)$rows[$i-1]['audit_id']];
    $current=[(string)$rows[$i]['datetime'],(int)$rows[$i]['audit_id']];
    if($previous[0]<$current[0]||($previous[0]===$current[0]&&$previous[1]<$current[1])){$ordered=false;break;}
}
$report($ordered,'live read-only audit result is newest-first');
$report(count($rows)<=50,'live read-only audit result respects maximum 50 rows');

printf("RESULT checks=%d failed=%d\n",$checks,$failed);exit($failed===0?0:1);
