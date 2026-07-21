<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { exit(2); }
require_once dirname(__DIR__).'/app/Auth/Totp.php';

use OneId\App\Auth\Totp;

$secret='GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
$vectors=[59=>'94287082',1111111109=>'07081804',1111111111=>'14050471',1234567890=>'89005924',2000000000=>'69279037',20000000000=>'65353130'];
$checks=[];
foreach($vectors as $time=>$expected){$checks["rfc_$time"]=Totp::codeAt($secret,$time,8)===$expected;}
$binary=random_bytes(32);$checks['base32_roundtrip']=hash_equals($binary,Totp::base32Decode(Totp::base32Encode($binary)));
$six=Totp::codeAt($secret,1234567890);$step=Totp::matchTimeStep($secret,$six,1234567890,1,null);
$checks['six_digit_match']=$step===intdiv(1234567890,30);
$checks['replay_rejected']=Totp::matchTimeStep($secret,$six,1234567890,1,$step)===null;
$uri=Totp::provisioningUri('OneID@UPNM','0530-09',$secret);
$checks['provisioning_uri']=str_starts_with($uri,'otpauth://totp/')&&str_contains($uri,'issuer=OneID%40UPNM')&&!str_contains($uri,' ');
$failed=array_keys(array_filter($checks,static fn(bool $ok):bool=>!$ok));
printf("F7_3_TOTP_CONTRACT checks=%d passed=%d\n",count($checks),count($checks)-count($failed));
if($failed){fwrite(STDERR,'FAIL '.implode(',',$failed)."\n");exit(1);} echo "PASS RFC6238_BASE32_ANTI_REPLAY_URI\n";
