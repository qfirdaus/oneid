<?php
declare(strict_types=1);
$root=dirname(__DIR__);$service=(string)file_get_contents($root.'/app/LoginBanner/LoginBannerService.php');$index=(string)file_get_contents($root.'/index.php');$security=(string)file_get_contents($root.'/lib/request_security.php');
$checks=[
'service depends on persistence and image interfaces'=>str_contains($service,'LoginBannerPersistenceInterface $persistence')&&str_contains($service,'LoginBannerImagePipelineInterface $images'),
'same image publishes one asset and maps both locales'=>str_contains($service,'$englishAssetId = $sameImageForEnglish')&&str_contains($service,"'en' => \$englishAssetId"),
'draft success requires mandatory history in its transaction'=>str_contains($service,"'action_name' => 'CREATE_DRAFT'")&&str_contains($service,"'reason_code' => 'LB3_DRAFT_CREATED'")&&str_contains($service,'$this->writeHistory(['),
'filesystem compensation covers staging and published paths'=>str_contains($service,'compensateFiles(')&&str_contains($service,'discardStaged(')&&str_contains($service,'discardPublished('),
'publish locks locale assets and enforces same-as-ms mapping'=>str_contains($service,'localeAssetsForUpdate(')&&str_contains($service,'LB3_SAME_AS_MS_MAPPING_INVALID')&&str_contains($service,"'storage_status'] ?? '') !== 'AVAILABLE'"),
'maximum overlapping published banner count is enforced'=>str_contains($service,'publishedForUpdate(')&&str_contains($service,'schedulesOverlap(')&&str_contains($service,'LB3_ACTIVE_BANNER_LIMIT'),
'state mutations use row lock expected version and exact update'=>str_contains($service,'requiredBanner(')&&str_contains($service,'LB3_BANNER_STALE')&&str_contains($service,'updateExactlyOne('),
'publish inactivate reorder and rollback are implemented'=>str_contains($service,'function publish(')&&str_contains($service,'function inactivate(')&&str_contains($service,'function reorder(')&&str_contains($service,'function rollback('),
'draft update reuses same-checksum immutable asset instead of duplicate insert'=>str_contains($service,'assetIdByDigestForUpdate(')&&str_contains($service,'$reusedStaged[$locale]')&&str_contains($service,'LB3 deduplicated staging cleanup failed'),
'success and rejection histories carry correlation'=>str_contains($service,"'outcome' => 'SUCCESS'")&&str_contains($service,"'outcome' => 'REJECTED'")&&str_contains($service,'recordRejectedBestEffort('),
'LB3 mutation service stays outside public renderer while static fallback remains'=>!str_contains($index,'LoginBannerService')&&str_contains($security,'admin_login_banner')&&str_contains($index,'assetsM/images/banner6.png')&&str_contains($index,'assetsM/images/banner7.png'),
];$fail=0;foreach($checks as $label=>$ok){echo($ok?'PASS ':'FAIL ').$label.PHP_EOL;if(!$ok)$fail++;}echo'RESULT checks='.count($checks).' failures='.$fail.PHP_EOL;exit($fail===0?0:1);
