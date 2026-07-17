<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/lib/upload_security.php';

$checks=[];
$tmpDir=sys_get_temp_dir().'/oneid-wa5-'.bin2hex(random_bytes(6));
mkdir($tmpDir,0700,true);
$sourcePath=$tmpDir.'/source.png';$targetPath=$tmpDir.'/normalized.png';
$source=imagecreatetruecolor(640,320);
$red=imagecolorallocate($source,220,20,60);imagefill($source,0,0,$red);imagepng($source,$sourcePath);imagedestroy($source);
$result=normalize_app_icon_to_png($sourcePath,$targetPath);
$info=@getimagesize($targetPath);
$checks['valid source normalizes successfully']=$result['success']===true&&is_file($targetPath);
$checks['output is canonical 256x256 PNG']=is_array($info)&&$info[0]===256&&$info[1]===256&&($info['mime']??'')==='image/png';
$normalized=imagecreatefrompng($targetPath);
$top=imagecolorsforindex($normalized,imagecolorat($normalized,0,0));
$middle=imagecolorsforindex($normalized,imagecolorat($normalized,128,128));
imagedestroy($normalized);
$checks['contain fit uses transparent padding']=$top['alpha']===127&&$middle['red']>150;
$oversize=normalize_app_icon_to_png($sourcePath,$tmpDir.'/oversize.png',256,100,16000000);
$checks['dimension limit rejects oversized source']=$oversize['success']===false&&$oversize['message']==='Image dimensions exceed the allowed limit';
$checks['animated GIF signature is rejected']=app_icon_is_animated("GIF89a\x00\x21\xF9\x04x\x00\x21\xF9\x04y",'image/gif');
$checks['animated WebP signature is rejected']=app_icon_is_animated("RIFF0000WEBPVP8XANIM",'image/webp');
$checks['APNG signature is rejected']=app_icon_is_animated("\x89PNG\r\n\x1a\nacTL",'image/png');
$checks['static image signature is accepted']=!app_icon_is_animated("\x89PNG\r\n\x1a\nIDAT",'image/png');
$upload=(string)file_get_contents(dirname(__DIR__).'/lib/upload_security.php');
$checks['staging always assigns PNG filename']=str_contains($upload,". '.png'")&&str_contains($upload,'normalize_app_icon_to_png');
$checks['source pixel ceiling is sixteen megapixels']=str_contains($upload,'$maxPixels=16000000');
foreach(glob($tmpDir.'/*')?:[] as $file)unlink($file);rmdir($tmpDir);
$passed=0;foreach($checks as $label=>$ok){echo($ok?'PASS':'FAIL').' '.$label."\n";$passed+=$ok?1:0;}
printf("RESULT %d/%d\n",$passed,count($checks));
exit($passed===count($checks)?0:1);

