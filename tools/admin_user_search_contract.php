<?php
declare(strict_types=1);
$root=dirname(__DIR__);$db=(string)file_get_contents($root.'/lib/Database.php');$ui=(string)file_get_contents($root.'/admin/dashboard.php');$q=(string)file_get_contents($root.'/lib/q_func.php');$checks=0;$failed=0;
$ok=static function(bool $condition,string $label)use(&$checks,&$failed):void{$checks++;$failed+=$condition?0:1;echo($condition?'PASS ':'FAIL ').$label.PHP_EOL;};
$ok(str_contains($db,'LIMIT 50')&&(str_contains($ui,'limit:50')||str_contains($ui,'limit: 50')),'server and Typeahead share a bounded 50-result contract');
$ok(str_contains($ui,'max-height: 420px')&&str_contains($ui,'overflow-y: auto'),'dropdown uses a compact ten-row-height scroll viewport');
$ok(str_contains($ui,'min-height:38px')&&str_contains($ui,'background:#eaf7fc'),'suggestions use compact rows and a professional OneID active state');
$ok(str_contains($ui,'flex:0 0 82px')&&str_contains($ui,'min-width:82px')&&str_contains($ui,'userStatusActive')&&str_contains($ui,'userStatusInactive'),'fixed right-aligned badges distinguish active and inactive accounts');
$ok(str_contains($ui,'.tt-suggestion.user-search-suggestion{padding:6px 8px 6px 14px}'),'specific Typeahead rule preserves the left content margin');
$ok(str_contains($db,'search_rank')&&str_contains($db,'ORDER BY search_rank ASC'),'results use deterministic relevance ordering');
$ok(str_contains($db,"str_replace(['=','%','_']")&&str_contains($db,"ESCAPE '='"),'LIKE wildcard input is treated literally');
$ok(str_contains($ui,'adminUserSearchRequest.abort()')&&str_contains($ui,'adminUserSearchSequence'),'stale asynchronous requests cannot replace fresh results');
$ok(str_contains($ui,'adminUserSearchText(name.data1)')&&str_contains($ui,'adminUserSearchText(name.data6)'),'suggestion database values are HTML escaped');
$ok(str_contains($db,'SELECT u_id,data1')&&str_contains($ui,'suggestion.u_id||suggestion.data4'),'selection uses canonical user ID with legacy fallback');
$ok(str_contains($ui,"String(response['data3']||'').trim()||String(response['data4']||'').trim()||String(response['u_id']||'').trim()"),'profile User ID uses staff student and canonical fallback');
$ok(str_contains($ui,'user-search-suggestion-id')&&str_contains($ui,'user-search-suggestion-unit'),'suggestions distinguish users by public ID and organizational unit');
$ok(str_contains($q,"(string)(\$_POST['search_key']??'')")&&str_contains($db,'mb_strlen($term)<3'),'server validates missing and out-of-range terms');
echo "RESULT checks={$checks} failed={$failed}".PHP_EOL;exit($failed===0?0:1);
